<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'products_processed' => 'integer',
        'products_failed' => 'integer',
        'duration_seconds' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    // SCOPES

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeLatest($query)
    {
        return $query->orderByDesc('started_at');
    }
}