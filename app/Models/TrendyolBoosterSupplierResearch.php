<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrendyolBoosterSupplierResearch extends Model
{
    use HasFactory;

    protected $table = 'trendyol_booster_supplier_researches';

    protected $fillable = [
        'user_id',
        'source_url',
        'source_url_hash',
        'trendyol_product_id',
        'title',
        'brand',
        'category_name',
        'image_url',
        'source_price',
        'currency',
        'scan_count',
        'platform_count',
        'seller_count',
        'offer_count',
        'verified_offer_count',
        'min_price',
        'median_price',
        'max_price',
        'price_spread_percent',
        'market_fit_score',
        'confidence_score',
        'risk_level',
        'verdict',
        'search_query',
        'search_url',
        'last_scan_uuid',
        'raw_payload',
        'is_active',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'source_price' => 'decimal:2',
            'scan_count' => 'integer',
            'platform_count' => 'integer',
            'seller_count' => 'integer',
            'offer_count' => 'integer',
            'verified_offer_count' => 'integer',
            'min_price' => 'decimal:2',
            'median_price' => 'decimal:2',
            'max_price' => 'decimal:2',
            'price_spread_percent' => 'decimal:2',
            'market_fit_score' => 'integer',
            'confidence_score' => 'integer',
            'raw_payload' => 'array',
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(TrendyolBoosterSupplierOffer::class);
    }
}
