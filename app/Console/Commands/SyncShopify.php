<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use \App\Services\Shopify\ProductSyncService;
use \App\Services\Shopify\ShopifyAdminService;

#[Signature('app:sync-shopify')]
#[Description('Command description')]
class SyncShopify extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $shopifyInstance = new ProductSyncService(new ShopifyAdminService());
        $shopifyInstance->syncAll();
    }
}
