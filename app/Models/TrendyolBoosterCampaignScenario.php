<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendyolBoosterCampaignScenario extends Model
{
    use HasFactory;

    protected $fillable = [
        'trendyol_booster_product_id',
        'user_id',
        'name',
        'campaign_type',
        'discount_rate',
        'campaign_price',
        'commission_discount_rate',
        'advertising_rate',
        'expected_units',
        'current_net_profit',
        'campaign_net_profit',
        'profit_delta_per_unit',
        'total_profit_delta',
        'campaign_margin_percent',
        'decision_status',
        'decision_note',
        'simulation_json',
    ];

    protected function casts(): array
    {
        return [
            'discount_rate' => 'decimal:2',
            'campaign_price' => 'decimal:2',
            'commission_discount_rate' => 'decimal:2',
            'advertising_rate' => 'decimal:2',
            'expected_units' => 'integer',
            'current_net_profit' => 'decimal:2',
            'campaign_net_profit' => 'decimal:2',
            'profit_delta_per_unit' => 'decimal:2',
            'total_profit_delta' => 'decimal:2',
            'campaign_margin_percent' => 'decimal:2',
            'simulation_json' => 'array',
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
