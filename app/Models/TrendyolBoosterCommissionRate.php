<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendyolBoosterCommissionRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'marketplace',
        'category_name',
        'sub_category_name',
        'product_group',
        'maturity_days',
        'commission_rate',
        'level_5_rate',
        'level_4_rate',
        'level_3_rate',
        'level_2_rate',
        'level_1_rate',
        'special_group',
        'source',
        'effective_from',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'maturity_days' => 'integer',
            'commission_rate' => 'decimal:2',
            'level_5_rate' => 'decimal:2',
            'level_4_rate' => 'decimal:2',
            'level_3_rate' => 'decimal:2',
            'level_2_rate' => 'decimal:2',
            'level_1_rate' => 'decimal:2',
            'effective_from' => 'date',
            'imported_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
