<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendyolBoosterActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'trendyol_booster_product_id',
        'activity_type',
        'title',
        'subject',
        'summary',
        'result_label',
        'result_value',
        'payload',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'result_value' => 'decimal:2',
            'payload' => 'array',
            'recorded_at' => 'datetime',
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
