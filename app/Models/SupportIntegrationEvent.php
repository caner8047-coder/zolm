<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportIntegrationEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'event_id', 'event_type', 'payload_json', 'idempotency_key',
    ];

    protected $casts = [
        'payload_json' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(SupportIntegrationDelivery::class, 'support_integration_event_id');
    }
}
