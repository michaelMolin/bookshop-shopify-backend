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

        $this->info("Registrazione webhook per {$shop}");

        $response = Registry::register(
            path: $path,
            topic: Topics::PRODUCTS_UPDATE,
            shop: $shop,
            accessToken: $accessToken,
        );

        if ($response->isSuccess()) {
            $this->info("✓ Webhook 'products/update' registrato correttamente.");
            return self::SUCCESS;
        }

        $this->error("✗ Registrazione fallita.");
        $this->line(var_export($response->getBody(), true));
        return self::FAILURE;
    }
}
