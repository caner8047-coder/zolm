<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OptimizationReport extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'total_products',
        'opportunity_count',
        'total_current_profit',
        'total_optimized_profit',
        'total_extra_profit',
        'unmatched_count',
        'original_filename',
        'status',
        'ai_analysis',
        'loss_analysis',
    ];

    protected $casts = [
        'total_current_profit' => 'decimal:2',
        'total_optimized_profit' => 'decimal:2',
        'total_extra_profit' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OptimizationReportItem::class, 'report_id');
    }

    /**
     * Fırsat bulunan ürünler
     */
    public function opportunities(): HasMany
    {
        return $this->items()->where('action', 'update');
    }

    /**
     * Kâr artış yüzdesi
     */
    public function getProfitIncreasePercentAttribute(): float
    {
        if ($this->total_current_profit == 0) return 0;
        return round(($this->total_extra_profit / $this->total_current_profit) * 100, 1);
    }
}
