<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportInboundWebhookReceipt extends Model
{
    protected $fillable = [
        'store_id', 'integration_connection_id', 'provider', 'event_id', 'payload_hash',
        'status', 'last_error', 'received_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'processed_at' => 'datetime'];
    }
}
