<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSetItem extends Model
{
    protected $fillable = [
        'product_set_id',
        'component_mp_product_id',
        'quantity',
        'include_cost',
        'include_packaging',
        'include_logistics',
        'cost_override',
        'cargo_cost_override',
        'desi_override',
        'pieces_override',
        'sort_order',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'include_cost' => 'boolean',
            'include_packaging' => 'boolean',
            'include_logistics' => 'boolean',
            'cost_override' => 'decimal:2',
            'cargo_cost_override' => 'decimal:2',
            'desi_override' => 'decimal:2',
            'pieces_override' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function productSet(): BelongsTo
    {
        return $this->belongsTo(ProductSet::class);
    }

    public function componentProduct(): BelongsTo
    {
        return $this->belongsTo(MpProduct::class, 'component_mp_product_id');
    }
}
