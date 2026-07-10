<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity'      => 'integer',
            'unit_cost'     => 'decimal:2',
            'movement_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Signed quantity (positive for in, negative for out)
     */
    public function signedQuantity(): int
    {
        return $this->direction === 'in' ? $this->quantity : -$this->quantity;
    }
}
