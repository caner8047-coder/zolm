<?php

namespace App\Services\Marketplace\Connectors;

class GenericMarketplaceConnector extends AbstractMarketplaceConnector
{
    /**
     * @param  array<string, mixed>  $definition
     */
    public function __construct(
        protected string $provider,
        protected array $definition = [],
    ) {
    }

    public function providerKey(): string
    {
        return $this->provider;
    }

    public function displayName(): string
    {
        return (string) ($this->definition['label'] ?? $this->provider);
    }

    public function defaultApiBaseUrl(): ?string
    {
        return $this->definition['default_api_base_url'] ?? null;
    }

    /**
     * @return array<string, bool>
     */
    public function capabilities(): array
    {
        return $this->definition['supports'] ?? parent::capabilities();
    }
}
