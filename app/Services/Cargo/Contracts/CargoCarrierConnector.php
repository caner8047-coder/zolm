<?php

namespace App\Services\Cargo\Contracts;

use App\Models\CargoCarrierAccount;
use App\Models\Shipment;

interface CargoCarrierConnector
{
    /**
     * @return array<string, mixed>
     */
    public function testConnection(CargoCarrierAccount $account): array;

    /**
     * @return array<string, mixed>
     */
    public function createShipment(CargoCarrierAccount $account, Shipment $shipment): array;

    /**
     * @return array<string, mixed>
     */
    public function cancelShipment(CargoCarrierAccount $account, Shipment $shipment, array $context = []): array;

    /**
     * @return array<string, mixed>
     */
    public function trackShipment(CargoCarrierAccount $account, Shipment $shipment): array;
}
