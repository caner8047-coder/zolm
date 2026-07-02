<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TrendyolBoosterProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'mp_product_id',
        'channel_listing_id',
        'source_url',
        'source_url_hash',
        'trendyol_product_id',
        'title',
        'brand',
        'category_name',
        'image_url',
        'sale_price',
        'currency',
        'commission_rate',
        'cogs',
        'packaging_cost',
        'cargo_cost',
        'return_rate',
        'vat_rate',
        'cost_vat_rate',
        'net_profit',
        'profit_margin_percent',
        'break_even_price',
        'target_price',
        'opportunity_score',
        'decision_status',
        'decision_reasons',
        'simulation_json',
        'watch_price',
        'watch_stock',
        'watch_keyword',
        'is_favorite',
        'tracking_status',
        'tracking_sources',
        'tracking_group_key',
        'tracking_started_at',
        'tracking_paused_at',
        'data_quality_score',
        'interest_score',
        'competition_score',
        'risk_score',
        'estimated_daily_sales',
        'estimated_daily_revenue',
        'metrics_calculated_at',
        'analysis_auto_refresh_enabled',
        'analysis_refresh_interval_minutes',
        'next_analysis_refresh_at',
        'last_analysis_refresh_at',
        'last_analysis_refresh_status',
        'last_analysis_refresh_error',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'cogs' => 'decimal:2',
            'packaging_cost' => 'decimal:2',
            'cargo_cost' => 'decimal:2',
            'return_rate' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'cost_vat_rate' => 'decimal:2',
            'net_profit' => 'decimal:2',
            'profit_margin_percent' => 'decimal:2',
            'break_even_price' => 'decimal:2',
            'target_price' => 'decimal:2',
            'opportunity_score' => 'integer',
            'decision_reasons' => 'array',
            'simulation_json' => 'array',
            'watch_price' => 'boolean',
            'watch_stock' => 'boolean',
            'watch_keyword' => 'boolean',
            'is_favorite' => 'boolean',
            'tracking_sources' => 'array',
            'tracking_started_at' => 'datetime',
            'tracking_paused_at' => 'datetime',
            'data_quality_score' => 'integer',
            'interest_score' => 'integer',
            'competition_score' => 'integer',
            'risk_score' => 'integer',
            'estimated_daily_sales' => 'decimal:2',
            'estimated_daily_revenue' => 'decimal:2',
            'metrics_calculated_at' => 'datetime',
            'analysis_auto_refresh_enabled' => 'boolean',
            'analysis_refresh_interval_minutes' => 'integer',
            'next_analysis_refresh_at' => 'datetime',
            'last_analysis_refresh_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MpProduct::class, 'mp_product_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(ChannelListing::class, 'channel_listing_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(TrendyolBoosterSnapshot::class);
    }

    public function analysisSnapshots(): HasMany
    {
        return $this->snapshots()->whereNotNull('analysis_source');
    }

    public function competitors(): HasMany
    {
        return $this->hasMany(TrendyolBoosterCompetitor::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(TrendyolBoosterKeyword::class);
    }

    public function campaignScenarios(): HasMany
    {
        return $this->hasMany(TrendyolBoosterCampaignScenario::class);
    }

    public function stockChecks(): HasMany
    {
        return $this->hasMany(TrendyolBoosterStockCheck::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(TrendyolBoosterActivityLog::class);
    }

    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(TrendyolBoosterSnapshot::class)->latestOfMany('checked_at');
    }

    public function latestStockCheck(): HasOne
    {
        return $this->hasOne(TrendyolBoosterStockCheck::class)->latestOfMany('checked_at');
    }

    public function costRecommendations(): HasMany
    {
        return $this->hasMany(TrendyolBoosterCostRecommendation::class);
    }

    public function latestCostRecommendation(): HasOne
    {
        return $this->hasOne(TrendyolBoosterCostRecommendation::class)->latestOfMany('estimated_at');
    }
}
