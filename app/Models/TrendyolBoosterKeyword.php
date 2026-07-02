<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendyolBoosterKeyword extends Model
{
    use HasFactory;

    protected $fillable = [
        'trendyol_booster_product_id',
        'user_id',
        'keyword',
        'keyword_hash',
        'target_rank',
        'observed_rank',
        'result_count',
        'checked_result_count',
        'visibility_status',
        'visibility_note',
        'is_active',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'target_rank' => 'integer',
            'observed_rank' => 'integer',
            'result_count' => 'integer',
            'checked_result_count' => 'integer',
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    public function trackedProduct(): BelongsTo
    {
        return $this->belongsTo(TrendyolBoosterProduct::class, 'trendyol_booster_product_id');
    }

    public function observations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TrendyolBoosterKeywordObservation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
