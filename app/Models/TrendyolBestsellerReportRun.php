<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrendyolBestsellerReportRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'trendyol_bestseller_report_id',
        'user_id',
        'source',
        'source_url',
        'item_count',
        'priced_item_count',
        'in_stock_item_count',
        'campaign_item_count',
        'average_price',
        'metadata_json',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'item_count' => 'integer',
            'priced_item_count' => 'integer',
            'in_stock_item_count' => 'integer',
            'campaign_item_count' => 'integer',
            'average_price' => 'decimal:2',
            'metadata_json' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(TrendyolBestsellerReport::class, 'trendyol_bestseller_report_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TrendyolBestsellerReportItem::class);
    }
}
