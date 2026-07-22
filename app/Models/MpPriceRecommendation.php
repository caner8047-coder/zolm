<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MpPriceRecommendation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'current_price' => 'decimal:2',
            'buybox_price' => 'decimal:2',
            'second_price' => 'decimal:2',
            'third_price' => 'decimal:2',
            'recommended_price' => 'decimal:2',
            'minimum_safe_price' => 'decimal:2',
            'maximum_allowed_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'cargo_cost' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'service_cost' => 'decimal:2',
            'other_cost' => 'decimal:2',
            'expected_profit' => 'decimal:2',
            'expected_profit_margin' => 'decimal:2',
            'current_profit' => 'decimal:2',
            'current_profit_margin' => 'decimal:2',
            'price_difference' => 'decimal:2',
            'reason_codes' => 'array',
            'calculation_snapshot' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MpProduct::class, 'marketplace_product_id');
    }

    public function buyboxListing(): BelongsTo
    {
        return $this->belongsTo(MpBuyboxListing::class, 'mp_buybox_listing_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(MpPriceAction::class, 'recommendation_id');
    }

    public function isActionable(): bool
    {
        if ($this->risk_level === 'blocked') {
            return false;
        }

        if ($this->recommended_price === null) {
            return false;
        }

        if ($this->recommended_price < $this->minimum_safe_price) {
            return false;
        }

        return in_array($this->status, ['new', 'reviewed', 'approved'], true);
    }
}
