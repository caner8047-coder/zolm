<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendyolBoosterCompetitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'trendyol_booster_product_id',
        'user_id',
        'source_url',
        'source_url_hash',
        'trendyol_product_id',
        'title',
        'brand',
        'sale_price',
        'currency',
        'stock_status',
        'availability',
        'price_delta_vs_own',
        'price_gap_percent',
        'opportunity_type',
        'opportunity_note',
        'is_active',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'price_delta_vs_own' => 'decimal:2',
            'price_gap_percent' => 'decimal:2',
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    public function trackedProduct(): BelongsTo
    {
        return $this->belongsTo(TrendyolBoosterProduct::class, 'trendyol_booster_product_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
