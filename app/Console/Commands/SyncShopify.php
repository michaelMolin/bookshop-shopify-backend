<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Jobs\SyncProducts;

#[Signature('app:sync-shopify')]
#[Description('Avvia il job di allineamento prodotto BE - Shopify')]
class SyncShopify extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        SyncProducts::dispatch();
        $this->info('Job di sincronizzazione BE - Shopify avviato');
    }
}
