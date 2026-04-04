<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderFinancialEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'legal_entity_id',
        'channel_order_id',
        'channel_order_package_id',
        'channel_order_item_id',
        'event_source',
        'event_type',
        'external_event_id',
        'reference_number',
        'event_date',
        'due_date',
        'settlement_date',
        'amount',
        'currency',
        'direction',
        'status',
        'notes',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'datetime',
            'due_date' => 'datetime',
            'settlement_date' => 'datetime',
            'amount' => 'decimal:2',
            'raw_payload' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class, 'legal_entity_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ChannelOrder::class, 'channel_order_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ChannelOrderPackage::class, 'channel_order_package_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ChannelOrderItem::class, 'channel_order_item_id');
    }
}
