<?php

namespace App\Jobs;

use App\Models\SyncLog;
use App\Services\Shopify\ProductSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncProducts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 3;
    public int $timeout = 300;

    public function handle(ProductSyncService $service): void
    {
        $service->syncAll();
    }

    public function failed(Throwable $exception): void
    {

       SyncLog::where('status', 'running')->latest('started_at')->first()
        ?->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'finished_at' => now(),
        ]);
    }
}
