<?php

namespace App\Events;

use App\Models\Shipment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShipmentStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Shipment $shipment,
        public readonly string $oldStatus,
        public readonly string $newStatus,
    ) {}
}
