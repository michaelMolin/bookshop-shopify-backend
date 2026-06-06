<?php

namespace App\Services\Shopify;

use Shopify\Clients\Graphql;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ShopifyAdminService
{
    private Graphql $client;

    public function __construct()
    {
        $this->client = new Graphql(
            config('shopify.shop_domain'),
            config('shopify.admin_token')
        );
    }

    /**
     * Esegue una query GraphQL verso l'Admin API.
     */
    public function query(string $query, array $variables = []): array
    {
        $response = $this->client->query([
            'query' => $query,
            'variables' => $variables,
        ]);

        $body = $response->getDecodedBody();

        // Errori a livello GraphQL (query malformata, campo inesistente, ecc.)
        if (isset($body['errors'])) {
            Log::error('Shopify Admin GraphQL error', [
                'errors' => $body['errors'],
                'query' => $query,
            ]);
            throw new RuntimeException('Shopify Admin API ha restituito errori GraphQL.');
        }

        // Errori a livello utente (es. validazione mutation)
        $userErrors = $body['data'][array_key_first($body['data'] ?? [])]['userErrors'] ?? null;
        if (! empty($userErrors)) {
            Log::warning('Shopify Admin userErrors', ['userErrors' => $userErrors]);
        }

        return $body['data'] ?? [];
    }

    /**
     * Recupera una pagina di prodotti dall'Admin API.
     * Usa la paginazione a cursore di Shopify.
     */
    public function fetchProducts(int $first = 50, ?string $after = null): array
    {

        $query = <<<'GRAPHQL'
        query GetProducts($first: Int!, $after: String) {
            products(first: $first, after: $after) {
                pageInfo {
                    hasNextPage
                    endCursor
                }
                edges {
                    node {
                        id
                        title
                        handle
                        descriptionHtml
                        vendor
                        productType
                        status
                        tags
                        totalInventory
                        variants(first: 1) {
                            edges {
                                node {
                                    price
                                    sku
                                    inventoryQuantity
                                }
                            }
                        }
                    }
                }
            }
        }
        GRAPHQL;

        return $this->query($query, [
            'first' => $first,
            'after' => $after,
        ]);
    }
}