<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationWebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'provider',
        'event_type',
        'external_event_id',
        'signature_valid',
        'payload_json',
        'received_at',
        'processed_at',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'payload_json' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }
}
