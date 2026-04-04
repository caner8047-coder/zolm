<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChannelOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'channel_order_id',
        'channel_order_package_id',
        'channel_listing_id',
        'mp_product_id',
        'external_line_id',
        'stock_code',
        'barcode',
        'product_name',
        'quantity',
        'unit_price',
        'gross_amount',
        'discount_amount',
        'marketplace_discount_amount',
        'billable_amount',
        'commission_rate',
        'vat_rate',
        'line_status',
        'is_matched',
        'match_source',
        'last_synced_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'marketplace_discount_amount' => 'decimal:2',
            'billable_amount' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'is_matched' => 'boolean',
            'last_synced_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ChannelOrder::class, 'channel_order_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ChannelOrderPackage::class, 'channel_order_package_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(ChannelListing::class, 'channel_listing_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MpProduct::class, 'mp_product_id');
    }

    public function financialEvents(): HasMany
    {
        return $this->hasMany(OrderFinancialEvent::class, 'channel_order_item_id');
    }
}
