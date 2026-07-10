<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity'        => 'integer',
            'unit_price'      => 'decimal:2',
            'vat_rate'        => 'decimal:2',
            'discount_rate'   => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount'    => 'decimal:2',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MpProduct::class, 'product_id');
    }
}
