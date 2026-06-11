<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentCost extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'cost_date' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(CargoInvoiceLine::class, 'cargo_invoice_line_id');
    }
}
