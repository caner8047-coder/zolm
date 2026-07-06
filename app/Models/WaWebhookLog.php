<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaWebhookLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'endpoint_id', 'provider', 'event_type', 'direction',
        'status', 'request_id', 'payload_hash', 'error_message',
        'processing_time_ms', 'retry_count', 'next_retry_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_hash' => 'array',
            'processing_time_ms' => 'decimal:2',
            'retry_count' => 'integer',
            'next_retry_at' => 'datetime',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WaWebhookEndpoint::class, 'endpoint_id');
    }
}
