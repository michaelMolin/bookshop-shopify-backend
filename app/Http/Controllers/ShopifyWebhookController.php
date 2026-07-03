<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Shopify\Webhooks\Registry;

class ShopifyWebhookController
{
    public function handle(Request $request): Response
    {
        try {
            $result = Registry::process($request->header(), $request->getContent());

            if (! $result->isSuccess()) {
                Log::warning('Webhook processato con errori', [
                    'message' => $result->getErrorMessage(),
                ]);
            }

            return response('OK', 200);
        } catch (\Throwable $e) {
            Log::error('Webhook non valido o non gestito', ['error' => $e->getMessage()]);
            return response('Unauthorized', 401);
        }
    }
}
