<?php

namespace App\Services\WhatsApp;

use App\Models\WaExternalIntegration;

class IntegrationAdapterRegistry
{
    private array $adapters = [];

    public function __construct()
    {
        $this->register(new WooCommerceIntegrationAdapter());
        $this->register(new TrendyolIntegrationAdapter());
        $this->register(new NullIntegrationAdapter());
    }

    private function register(IntegrationAdapterInterface $adapter): void
    {
        $this->adapters[$adapter->key()] = $adapter;
    }

    public function resolve(string $key): IntegrationAdapterInterface
    {
        return $this->adapters[$key] ?? new NullIntegrationAdapter();
    }

    public function resolveForIntegration(WaExternalIntegration $integration): IntegrationAdapterInterface
    {
        return $this->resolve($integration->provider);
    }
}
