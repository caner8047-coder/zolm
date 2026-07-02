<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderProfitSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'channel_order_id',
        'channel_order_item_id',
        'profit_state',
        'gross_revenue',
        'net_receivable',
        'commission_total',
        'cargo_total',
        'service_fee_total',
        'advertising_total',
        'penalty_total',
        'early_payment_total',
        'discount_total',
        'other_cost_total',
        'withholding_total',
        'packaging_cost',
        'own_cargo_cost',
        'cogs_cost',
        'return_effect',
        'vat_effect',
        'estimated_profit',
        'confirmed_profit',
        'margin_percent',
        'calculated_at',
        'version',
        'currency',
        'exchange_rate',
        'profit_try',
    ];

    protected function casts(): array
    {
        return [
            'gross_revenue' => 'decimal:2',
            'net_receivable' => 'decimal:2',
            'commission_total' => 'decimal:2',
            'cargo_total' => 'decimal:2',
            'service_fee_total' => 'decimal:2',
            'advertising_total' => 'decimal:2',
            'penalty_total' => 'decimal:2',
            'early_payment_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'other_cost_total' => 'decimal:2',
            'withholding_total' => 'decimal:2',
            'packaging_cost' => 'decimal:2',
            'own_cargo_cost' => 'decimal:2',
            'cogs_cost' => 'decimal:2',
            'return_effect' => 'decimal:2',
            'vat_effect' => 'decimal:2',
            'estimated_profit' => 'decimal:2',
            'confirmed_profit' => 'decimal:2',
            'margin_percent' => 'decimal:2',
            'profit_try' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'calculated_at' => 'datetime',
            'version' => 'integer',
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

    public function item(): BelongsTo
    {
        return $this->belongsTo(ChannelOrderItem::class, 'channel_order_item_id');
    }
}
