<?php

namespace App\Shopify\Handlers;

use Shopify\Webhooks\Handler;
use Shopify\Webhooks\Topics;
use App\Jobs\ProcessProductWebhookJob;
use App\Models\WebhookLog;

class ProductWebhookHandler implements Handler
{
    public function handle(string $topic, string $shop, array $body): void
    {
        $webhookId = $body['id'] ?? null;

        $log = WebhookLog::create([
            'topic' => $topic,
            'shopify_webhook_id' => $webhookId,
            'payload' => $body,
            'status' => 'received',
            'received_at' => now(),
        ]);

        ProcessProductWebhookJob::dispatch($topic, $body, $log->id);
    }
}
