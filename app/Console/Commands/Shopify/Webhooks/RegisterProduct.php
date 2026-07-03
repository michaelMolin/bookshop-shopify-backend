<?php

namespace App\Console\Commands\Shopify\Webhooks;

use Illuminate\Console\Command;
use Shopify\Webhooks\Registry;
use Shopify\Webhooks\Topics;

class RegisterProduct extends Command
{
    protected $signature = 'shopify:webhooks:register-product';
    protected $description = 'Registra i webhook su Shopify per il negozio - prodotti';

    public function handle(): int
    {
        $shop = config('shopify.shop_domain');
        $accessToken = config('shopify.admin_token');
        $path = '/api/webhooks/shopify';
        $hasErrors = false;
        $topics = [
            'create' => Topics::PRODUCTS_CREATE,
            'update' => Topics::PRODUCTS_UPDATE,
            'delete' => Topics::PRODUCTS_DELETE,
        ];

        $this->info("Registrazione webhook per {$shop}");

        foreach ($topics as $i => $topic) {
            $response = Registry::register(
                path: $path,
                topic: $topic,
                shop: $shop,
                accessToken: $accessToken,
            );

            if (!$response->isSuccess()) {
                $this->error("✗ Registrazione product {$i} fallita.");
                $this->line(var_export($response->getBody(), true));
                $hasErrors = true;
                continue;
            }
            $this->info("✓ Webhook 'products/update' registrato correttamente.");
        }

        return (!$hasErrors) ? self::SUCCESS : self::FAILURE;
    }
}
