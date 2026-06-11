<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'event_at' => 'datetime',
            'received_at' => 'datetime',
            'is_terminal' => 'boolean',
            'raw_payload' => 'array',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
