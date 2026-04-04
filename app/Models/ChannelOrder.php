<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChannelOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'legal_entity_id',
        'external_order_id',
        'order_number',
        'order_status',
        'color_label_key',
        'commercial_type',
        'customer_name',
        'customer_email',
        'customer_phone',
        'billing_name',
        'billing_tax_number',
        'shipment_country',
        'shipment_city',
        'shipment_district',
        'ordered_at',
        'approved_at',
        'delivered_at',
        'cancelled_at',
        'returned_at',
        'last_synced_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'ordered_at' => 'datetime',
            'approved_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'returned_at' => 'datetime',
            'last_synced_at' => 'datetime',
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

    public function packages(): HasMany
    {
        return $this->hasMany(ChannelOrderPackage::class, 'channel_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChannelOrderItem::class, 'channel_order_id');
    }

    public function financialEvents(): HasMany
    {
        return $this->hasMany(OrderFinancialEvent::class, 'channel_order_id');
    }

    public function profitSnapshots(): HasMany
    {
        return $this->hasMany(OrderProfitSnapshot::class, 'channel_order_id');
    }

    /**
     * Sipariş bazlı tekil profit snapshot (order-level, item = null).
     */
    public function profitSnapshot(): HasOne
    {
        return $this->hasOne(OrderProfitSnapshot::class, 'channel_order_id')
            ->whereNull('channel_order_item_id')
            ->latestOfMany('version');
    }

    public function actionRuns(): HasMany
    {
        return $this->hasMany(IntegrationOrderActionRun::class, 'channel_order_id');
    }
}
