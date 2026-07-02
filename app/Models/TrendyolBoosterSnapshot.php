<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendyolBoosterSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'trendyol_booster_product_id',
        'user_id',
        'sale_price',
        'previous_sale_price',
        'price_delta',
        'price_delta_percent',
        'stock_status',
        'availability',
        'stock_quantity',
        'evaluation_count',
        'review_count',
        'average_rating',
        'favorite_count',
        'favorite_precision',
        'basket_count',
        'view_count_24h',
        'question_count',
        'category_rank',
        'seller_score',
        'seller_follower_count',
        'campaign_count',
        'recent_reviews',
        'analysis_source',
        'data_sources',
        'data_quality_score',
        'confidence_score',
        'estimated_hourly_sales',
        'estimated_daily_sales',
        'estimated_days_of_stock',
        'estimated_daily_revenue',
        'estimated_conversion_rate',
        'sentiment_score',
        'positive_topics',
        'negative_topics',
        'interest_score',
        'competition_score',
        'risk_score',
        'metrics_json',
        'opportunity_score',
        'decision_status',
        'net_profit',
        'profit_margin_percent',
        'raw_payload',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'previous_sale_price' => 'decimal:2',
            'price_delta' => 'decimal:2',
            'price_delta_percent' => 'decimal:2',
            'evaluation_count' => 'integer',
            'stock_quantity' => 'integer',
            'review_count' => 'integer',
            'average_rating' => 'decimal:2',
            'favorite_count' => 'integer',
            'basket_count' => 'integer',
            'view_count_24h' => 'integer',
            'question_count' => 'integer',
            'category_rank' => 'integer',
            'seller_score' => 'decimal:2',
            'seller_follower_count' => 'integer',
            'campaign_count' => 'integer',
            'recent_reviews' => 'array',
            'data_sources' => 'array',
            'data_quality_score' => 'integer',
            'confidence_score' => 'integer',
            'estimated_hourly_sales' => 'decimal:3',
            'estimated_daily_sales' => 'decimal:2',
            'estimated_days_of_stock' => 'decimal:2',
            'estimated_daily_revenue' => 'decimal:2',
            'estimated_conversion_rate' => 'decimal:2',
            'sentiment_score' => 'decimal:2',
            'positive_topics' => 'array',
            'negative_topics' => 'array',
            'interest_score' => 'integer',
            'competition_score' => 'integer',
            'risk_score' => 'integer',
            'metrics_json' => 'array',
            'opportunity_score' => 'integer',
            'net_profit' => 'decimal:2',
            'profit_margin_percent' => 'decimal:2',
            'raw_payload' => 'array',
            'checked_at' => 'datetime',
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
