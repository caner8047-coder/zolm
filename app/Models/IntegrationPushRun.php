<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationPushRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'channel_listing_id',
        'mp_product_id',
        'triggered_by',
        'push_type',
        'status',
        'target_price',
        'target_quantity',
        'currency',
        'request_context_json',
        'response_json',
        'external_batch_id',
        'attempt_count',
        'started_at',
        'finished_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'target_price' => 'decimal:2',
            'target_quantity' => 'integer',
            'request_context_json' => 'array',
            'response_json' => 'array',
            'attempt_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(ChannelListing::class, 'channel_listing_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MpProduct::class, 'mp_product_id');
    }

    public function triggerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
