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
        'status', 'payload_hash', 'error_message', 'processing_time_ms',
    ];

    protected function casts(): array
    {
        return [
            'payload_hash' => 'array',
            'processing_time_ms' => 'decimal:2',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WaWebhookEndpoint::class, 'endpoint_id');
    }
}
