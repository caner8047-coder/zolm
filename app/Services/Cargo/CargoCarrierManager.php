<?php

namespace App\Services\Cargo;

use App\Models\CargoCarrierAccount;
use App\Models\Shipment;
use App\Services\Cargo\Contracts\CargoCarrierConnector;
use Illuminate\Contracts\Container\Container;

class CargoCarrierManager
{
    public function __construct(
        protected Container $container,
        protected CargoCarrierRegistry $registry,
    ) {}

    public function forCode(string $code): CargoCarrierConnector
    {
        $carrier = $this->registry->get($code);
        $connectorClass = $carrier['connector'] ?? null;

        if (! is_string($connectorClass) || $connectorClass === '') {
            throw new \RuntimeException($this->registry->unavailableMessage($code));
        }

        $connector = $this->container->make($connectorClass);

        if (! $connector instanceof CargoCarrierConnector) {
            throw new \LogicException("{$connectorClass} sınıfı CargoCarrierConnector sözleşmesini uygulamalı.");
        }

        return $connector;
    }

    public function forAccount(CargoCarrierAccount $account): CargoCarrierConnector
    {
        return $this->forCode($account->carrier_code);
    }

    public function forShipment(Shipment $shipment): CargoCarrierConnector
    {
        return $this->forCode($shipment->carrier_code);
    }

    /**
     * @return list<string>
     */
    public function connectorCodes(): array
    {
        return $this->registry->connectorCodes();
    }
}
