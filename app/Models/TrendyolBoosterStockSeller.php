<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendyolBoosterStockSeller extends Model
{
    use HasFactory;

    protected $fillable = [
        'trendyol_booster_stock_check_id',
        'user_id',
        'seller_name',
        'seller_id',
        'stock',
        'previous_stock',
        'stock_delta',
        'estimated_sales',
        'sale_price',
        'seller_score',
        'shipping_note',
    ];

    protected function casts(): array
    {
        return [
            'stock' => 'integer',
            'previous_stock' => 'integer',
            'stock_delta' => 'integer',
            'estimated_sales' => 'integer',
            'sale_price' => 'decimal:2',
            'seller_score' => 'decimal:2',
        ];
    }

    public function stockCheck(): BelongsTo
    {
        return $this->belongsTo(TrendyolBoosterStockCheck::class, 'trendyol_booster_stock_check_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
