<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaCoupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'contact_id', 'cart_id', 'automation_key',
        'wc_coupon_id', 'code', 'discount_type', 'discount_value',
        'minimum_spend', 'expires_at', 'used_at', 'related_order_id',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'minimum_spend' => 'decimal:2',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WaContact::class, 'contact_id');
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(WaAbandonedCart::class, 'cart_id');
    }
}
