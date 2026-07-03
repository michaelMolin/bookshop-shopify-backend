<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeForTopic($query, string $topic)
    {
        return $query->where('topic', $topic);
    }
}
