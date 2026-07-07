<?php

namespace App\Services\Shopify;

use App\Models\Product;
use App\Models\Category;
use App\Models\SyncLog;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Throwable;

readonly class ProductSyncService
{
    public function __construct(
        private ShopifyAdminService $admin,
    ) {}

    /**
     * Sincronizza tutti i prodotti da Shopify per la locale indicata.
     * Chiamare una volta per ogni locale supportata per popolare tutte le traduzioni.
     */
    public function syncAll(string $locale = ''): SyncLog
    {
        $locale = $locale ?: config('app.locale', 'it');

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
                        $this->syncOne($edge['node'], $locale);
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
     * Entry point per i webhook: sincronizza un singolo nodo prodotto
     * nella locale specificata (default: locale dell'app).
     */
    public function syncFromWebhook(array $node, string $locale = ''): void
    {
        $locale = $locale ?: config('app.locale', 'it');
        $this->syncOne($node, $locale);
    }

    /**
     * Mappa e salva un singolo prodotto Shopify.
     *
     * I campi scalari (price, isbn, ecc.) vengono aggiornati a ogni sync.
     * I campi tradotti (title, slug, book_data) vengono impostati per la locale
     * corrente senza sovrascrivere le altre lingue già salvate.
     */
    private function syncOne(array $node, string $locale): void
    {
        // L'id Shopify arriva come GID, esempio: "gid://shopify/Product/123456"
        $shopifyId = (int) Str::afterLast($node['id'], '/');

        $shopifyVariant = $node['variants']['edges'][0]['node'] ?? [];

        // Campi scalari (non tradotti): aggiornati indipendentemente dalla locale
        $product = Product::updateOrCreate(
            ['id_shopify' => $shopifyId],
            [
                'publisher' => $node['vendor'] ?? null,
                'isbn' => $shopifyVariant['sku'] ?? null,
                'price' => $shopifyVariant['price'] ?? 0,
                'inventory_quantity' => $shopifyVariant['inventoryQuantity'] ?? 0,
                'tags' => $node['tags'] ?? [],
                'status' => $this->mapStatus($node['status'] ?? 'ACTIVE'),
                'synced_at' => now(),
            ]
        );

        // Campi tradotti: salvati per la locale corrente, le altre rimangono intatte
        $product
            ->setTranslation('title', $locale, $node['title'])
            ->setTranslation('slug', $locale, $node['handle'])
            ->setTranslation('book_data', $locale, [
                'description' => $node['descriptionHtml'] ?? null,
            ])
            ->save();

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
