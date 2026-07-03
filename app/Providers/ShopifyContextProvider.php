<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Shopify\Context;
use Shopify\Auth\FileSessionStorage;
use App\Shopify\Handlers\ProductWebhookHandler;
use Shopify\Webhooks\Registry;
use Shopify\Webhooks\Topics;

class ShopifyContextProvider extends ServiceProvider
{
    public function boot(): void
    {
        Context::initialize(
            apiKey: config('shopify.api_key'),
            apiSecretKey: config('shopify.api_secret_key'),
            scopes: ['read_products', 'read_inventory', 'read_orders'],
            hostName: config('shopify.app_url'),
            sessionStorage: new FileSessionStorage(storage_path('shopify_sessions')),
            apiVersion: config('shopify.api_version'),
            isEmbeddedApp: false,
        );

        $productHandler = new ProductWebhookHandler();

        Registry::addHandler(Topics::PRODUCTS_CREATE, $productHandler);
        Registry::addHandler(Topics::PRODUCTS_UPDATE, $productHandler);
        Registry::addHandler(Topics::PRODUCTS_DELETE, $productHandler);
    }
}
