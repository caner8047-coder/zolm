<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MpPriceShadowRecord extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'simulated_at' => 'datetime',
            'current_price' => 'decimal:2',
            'buybox_price' => 'decimal:2',
            'recommended_price' => 'decimal:2',
            'minimum_safe_price' => 'decimal:2',
            'expected_profit' => 'decimal:2',
            'expected_profit_margin' => 'decimal:2',
            'is_actionable' => 'boolean',
            'blocking_reasons' => 'array',
            'buybox_snapshot' => 'array',
            'cost_snapshot' => 'array',
            'policy_snapshot' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(MpPriceShadowEvaluation::class, 'shadow_record_id');
    }
}
