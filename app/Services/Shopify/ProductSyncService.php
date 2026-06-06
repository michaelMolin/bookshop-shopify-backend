<?php

namespace App\Services\Shopify;

use App\Models\Product;
use App\Models\Category;
use App\Models\SyncLog;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProductSyncService
{
    public function __construct(
        private readonly ShopifyAdminService $admin,
    ) {}


    public function syncAll(): SyncLog
    {
        $log = SyncLog::create([
            'type' => 'products',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $processed = 0;
        $failed = 0;
        $after = null;

        try {
            do {
                $data = $this->admin->fetchProducts(50, $after);
                $connection = $data['products'] ?? [];
                $edges = $connection['edges'] ?? [];

                foreach ($edges as $edge) {
                    try {
                        $this->syncOne($edge['node']);
                        $processed++;
                    } catch (Throwable $e) {
                        $failed++;
                        Log::error('Errore sync singolo prodotto', [
                            'shopify_node' => $edge['node']['id'] ?? null,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }

                $pageInfo = $connection['pageInfo'] ?? [];
                $hasNext = $pageInfo['hasNextPage'] ?? false;
                $after = $pageInfo['endCursor'] ?? null;

            } while ($hasNext);

            $log->update([
                'status' => 'completed',
                'products_processed' => $processed,
                'products_failed' => $failed,
                'finished_at' => now(),
                'duration_seconds' => now()->diffInSeconds($log->started_at),
            ]);
        } catch (Throwable $e) {
            $log->update([
                'status' => 'failed',
                'products_processed' => $processed,
                'products_failed' => $failed,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
                'duration_seconds' => now()->diffInSeconds($log->started_at),
            ]);
            throw $e;
        }

        return $log->fresh();
    }

    /**
     * Mappa e salva un singolo prodotto Shopify sul modello locale.
     */
    private function syncOne(array $node): void
    {
        // L'id Shopify arriva come GID: "gid://shopify/Product/123456"
        $shopifyId = (int) Str::afterLast($node['id'], '/');

        // Prima variante (per price, sku, inventory)
        $variant = $node['variants']['edges'][0]['node'] ?? [];

        $product = Product::updateOrCreate(
            ['shopify_id' => $shopifyId],
            [
                'title' => $node['title'],
                'slug' => $node['handle'],
                'publisher' => $node['vendor'] ?? null,
                'isbn' => $variant['sku'] ?? null,
                'price' => $variant['price'] ?? 0,
                'inventory_quantity' => $variant['inventoryQuantity'] ?? 0,
                'tags' => $node['tags'] ?? [],
                'data' => [
                    'description' => $node['descriptionHtml'] ?? null,
                ],
                'status' => $this->mapStatus($node['status'] ?? 'ACTIVE'),
                'synced_at' => now(),
            ]
        );

        // Categoria: deriva da productType, collega via pivot
        $this->syncCategory($product, $node['productType'] ?? null);
    }

    /**
     * Trova o crea la categoria dal productType Shopify e la collega al prodotto.
     */
    private function syncCategory(Product $product, ?string $productType): void
    {
        if (blank($productType)) {
            return;
        }

        $category = Category::firstOrCreate(
            ['slug' => Str::slug($productType)],
            ['name' => $productType]
        );

        // syncWithoutDetaching: aggiunge il legame senza rimuovere altre categorie
        $product->categories()->syncWithoutDetaching([$category->id]);
    }

    private function mapStatus(string $shopifyStatus): string
    {
        return match (strtoupper($shopifyStatus)) {
            'ACTIVE' => 'active',
            'DRAFT' => 'draft',
            'ARCHIVED' => 'archived',
            default => 'active',
        };
    }
}