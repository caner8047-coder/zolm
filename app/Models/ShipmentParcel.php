<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentParcel extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'parcel_index' => 'integer',
            'desi' => 'decimal:2',
            'weight' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'length' => 'decimal:2',
            'piece_count' => 'integer',
            'raw_payload' => 'array',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
