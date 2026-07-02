<?php

namespace App\Services\Shopify;

use App\Models\Product;
use App\Models\Category;
use App\Models\SyncLog;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

readonly class ProductSyncService
{
    public function __construct(
        private ShopifyAdminService $admin,
    ) {}


    public function syncAll(): SyncLog
    {
        $log = SyncLog::create([
            'type' => 'products',
            'status' => 'running',
            'started_at' => now(),
        ]);
        $logStartedAt = now();

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
                'duration_seconds' => $logStartedAt->diffInSeconds(now()),
            ]);
        } catch (Throwable $e) {
            $log->update([
                'status' => 'failed',
                'products_processed' => $processed,
                'products_failed' => $failed,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
                'duration_seconds' => $logStartedAt->diffInSeconds(now()),
            ]);
            throw $e;
        }

        return $log->fresh();
    }

    /**
     * Mappa e salva un singolo prodotto Shopify
     */
    private function syncOne(array $node): void
    {
        // L'id Shopify arriva come GID, esempio: "gid://shopify/Product/123456"
        $shopifyId = (int) Str::afterLast($node['id'], '/');

        $shopifyProduct = $node['variants']['edges'][0]['node'] ?? [];
        $product = Product::updateOrCreate(
            ['id_shopify' => $shopifyId],
            [
                'title' => $node['title'],
                'slug' => $node['handle'],
                'publisher' => $node['vendor'] ?? null,
                'isbn' => $shopifyProduct['sku'] ?? null,
                'price' => $shopifyProduct['price'] ?? 0,
                'inventory_quantity' => $shopifyProduct['inventoryQuantity'] ?? 0,
                'tags' => $node['tags'] ?? [],
                'data' => [
                    'description' => $node['descriptionHtml'] ?? null,
                ],
                'status' => $this->mapStatus($node['status'] ?? 'ACTIVE'),
                'synced_at' => now(),
            ]
        );

        $this->syncCategory($product, $node['productType'] ?? null);
    }

    /**
     * Trova o crea la categoria dal productType Shopify e la collega al prodotto.
     */
    private function syncCategory(Product $product, ?string $productType): void
    {
        if (empty($productType)) {
            return;
        }

        $category = Category::firstOrCreate(
            ['slug' => Str::slug($productType)],
            ['name' => $productType]
        );

        $product->categories()->syncWithoutDetaching([$category->id]);
    }

    private function mapStatus(string $shopifyStatus): string
    {
        return match (strtoupper($shopifyStatus)) {
            'DRAFT' => 'draft',
            'ARCHIVED' => 'archived',
            default => 'active',
        };
    }
}
