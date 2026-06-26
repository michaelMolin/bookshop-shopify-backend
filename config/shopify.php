<?php

return [
    'public_token' => env('SHOPIFY_PUBLIC_TOKEN'),
    'private_token' => env('SHOPIFY_PRIVATE_TOKEN'),
    'admin_token' => env('SHOPIFY_ADMIN_TOKEN'),
    'api_key' => env('SHOPIFY_API_KEY'),
    'api_secret_key' => env('SHOPIFY_API_SECRET'),
    'api_version' => env('SHOPIFY_API_VERSION','2026-01'),
    'shop_domain' => env('SHOPIFY_SHOP_DOMAIN')
];
