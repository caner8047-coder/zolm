<?php

namespace App\Services\Marketplace\Contracts;

interface MarketplaceConnector
{
    public function providerKey(): string;

    public function displayName(): string;

    public function defaultApiBaseUrl(): ?string;

    /**
     * @return array<string, bool>
     */
    public function capabilities(): array;
}
