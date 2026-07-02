<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendyolBoosterCostRecommendation extends Model
{
    protected $fillable = [
        'user_id',
        'trendyol_booster_product_id',
        'source_url_hash',
        'category_name',
        'seller_score',
        'seller_level',
        'commission_rate',
        'commission_source',
        'commission_confidence',
        'estimated_desi',
        'billable_desi',
        'desi_source',
        'desi_confidence',
        'cargo_company',
        'cargo_cost_net',
        'cargo_cost_gross',
        'cargo_source',
        'cargo_confidence',
        'evidence',
        'scenarios',
        'estimated_at',
    ];

    protected function casts(): array
    {
        return [
            'seller_score' => 'decimal:2',
            'seller_level' => 'integer',
            'commission_rate' => 'decimal:2',
            'commission_confidence' => 'decimal:2',
            'estimated_desi' => 'decimal:2',
            'billable_desi' => 'integer',
            'desi_confidence' => 'decimal:2',
            'cargo_cost_net' => 'decimal:2',
            'cargo_cost_gross' => 'decimal:2',
            'cargo_confidence' => 'decimal:2',
            'evidence' => 'array',
            'scenarios' => 'array',
            'estimated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trackedProduct(): BelongsTo
    {
        return $this->belongsTo(TrendyolBoosterProduct::class, 'trendyol_booster_product_id');
    }
}
