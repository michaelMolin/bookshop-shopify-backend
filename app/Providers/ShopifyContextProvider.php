<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Shopify\Context;
use Shopify\Auth\FileSessionStorage;

class ShopifyContextProvider extends ServiceProvider
{
    public function boot(): void
    {
        Context::initialize(
            apiKey: config('shopify.api_key'),
            apiSecretKey: config('shopify.api_secret_key'),
            scopes: ['read_products', 'read_inventory', 'read_orders'],
            hostName: config('shopify.shop_domain'),
            sessionStorage: new FileSessionStorage(storage_path('shopify_sessions')),
            apiVersion: config('shopify.api_version'),
            isEmbeddedApp: false,
            isPrivateApp: false,
        );
    }
}