<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpOperationalOrderItem extends Model
{
    protected $fillable = [
        'operational_order_id', 'order_number',
        'barcode', 'stock_code', 'product_name', 'brand', 'quantity',
        'unit_price', 'sale_price', 'discount_amount',
        'trendyol_discount', 'billable_amount', 'commission_rate',
        'boutique_number',
        'cargo_desi', 'calculated_desi', 'invoiced_cargo_amount',
        'synced_cogs_unit', 'synced_vat_rate',
    ];

    protected $casts = [
        'quantity'              => 'integer',
        'unit_price'            => 'decimal:2',
        'sale_price'            => 'decimal:2',
        'discount_amount'       => 'decimal:2',
        'trendyol_discount'     => 'decimal:2',
        'billable_amount'       => 'decimal:2',
        'commission_rate'       => 'decimal:2',
        'cargo_desi'            => 'decimal:2',
        'calculated_desi'       => 'decimal:2',
        'synced_cogs_unit'      => 'decimal:2',
        'synced_vat_rate'       => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(MpOperationalOrder::class, 'operational_order_id');
    }

    /**
     * Ürün kütüphanesindeki eşleşen ürün (stock_code bazlı)
     * COGS, packaging_cost, cargo_cost gibi maliyet verilerini çeker
     * NOT: Sipariş barkodları Trendyol'un sayısal barkodlarıdır,
     *      ürün barkodları ise satıcı SKU'sudur. Ortak alan stock_code'dur.
     */
    public function product()
    {
        return $this->belongsTo(MpProduct::class, 'stock_code', 'stock_code');
    }

    /**
     * Muhasebe modülündeki eşleşen finansal kayıt (barkod bazlı)
     */
    public function financialOrder()
    {
        return $this->hasOne(MpOrder::class, 'barcode', 'barcode')
                    ->where('order_number', $this->order_number);
    }
}
