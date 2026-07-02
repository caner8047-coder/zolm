<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TrendyolBestsellerReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'query',
        'normalized_query',
        'matched_label',
        'source_url',
        'min_price',
        'max_price',
        'fingerprint',
        'status',
        'run_count',
        'latest_product_count',
        'first_captured_at',
        'last_captured_at',
    ];

    protected function casts(): array
    {
        return [
            'min_price' => 'decimal:2',
            'max_price' => 'decimal:2',
            'run_count' => 'integer',
            'latest_product_count' => 'integer',
            'first_captured_at' => 'datetime',
            'last_captured_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(TrendyolBestsellerReportRun::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TrendyolBestsellerReportItem::class);
    }

    public function latestRun(): HasOne
    {
        return $this->hasOne(TrendyolBestsellerReportRun::class)->latestOfMany('captured_at');
    }
}
