<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendyolBestsellerReportItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'trendyol_bestseller_report_run_id',
        'trendyol_bestseller_report_id',
        'trendyol_booster_product_id',
        'user_id',
        'trendyol_product_id',
        'source_url',
        'title',
        'brand',
        'image_url',
        'rank_position',
        'previous_rank',
        'rank_delta',
        'price',
        'seller_name',
        'seller_id',
        'seller_score',
        'stock_quantity',
        'stock_status',
        'campaign_count',
        'campaigns_json',
        'estimated_sales_3d',
        'estimated_revenue_3d',
        'rating',
        'rating_count',
        'favorite_count',
        'basket_count',
        'view_count_24h',
        'data_quality_score',
        'raw_payload',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'rank_position' => 'integer',
            'previous_rank' => 'integer',
            'rank_delta' => 'integer',
            'price' => 'decimal:2',
            'seller_score' => 'decimal:2',
            'stock_quantity' => 'integer',
            'campaign_count' => 'integer',
            'campaigns_json' => 'array',
            'estimated_sales_3d' => 'integer',
            'estimated_revenue_3d' => 'decimal:2',
            'rating' => 'decimal:2',
            'rating_count' => 'integer',
            'favorite_count' => 'integer',
            'basket_count' => 'integer',
            'view_count_24h' => 'integer',
            'data_quality_score' => 'integer',
            'raw_payload' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(TrendyolBestsellerReport::class, 'trendyol_bestseller_report_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(TrendyolBestsellerReportRun::class, 'trendyol_bestseller_report_run_id');
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
