<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCost extends Model
{
    protected $fillable = [
        'stock_code',
        'barcode',
        'product_name',
        'production_cost',
        'shipping_cost',
    ];

    protected $casts = [
        'production_cost' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
    ];

    /**
     * Toplam sabit maliyet (üretim + kargo)
     */
    public function totalCost(): float
    {
        return (float) $this->production_cost + (float) $this->shipping_cost;
    }

    public function scopeByStockCode($query, string $code)
    {
        return $query->where('stock_code', $code);
    }
}
