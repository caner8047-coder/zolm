<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendyolBoosterCostPreset extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'category_name',
        'commission_rate',
        'cargo_cost',
        'return_cargo_cost',
        'packaging_cost',
        'service_fee_rate',
        'advertising_rate',
        'return_rate',
        'vat_rate',
        'cost_vat_rate',
        'expense_vat_rate',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'cargo_cost' => 'decimal:2',
            'return_cargo_cost' => 'decimal:2',
            'packaging_cost' => 'decimal:2',
            'service_fee_rate' => 'decimal:2',
            'advertising_rate' => 'decimal:2',
            'return_rate' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'cost_vat_rate' => 'decimal:2',
            'expense_vat_rate' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
