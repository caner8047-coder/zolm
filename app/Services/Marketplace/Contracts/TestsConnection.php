<?php

namespace App\Services\Marketplace\Contracts;

use App\Models\MarketplaceStore;

interface TestsConnection
{
    /**
     * @return array<string, mixed>
     */
    public function testConnection(MarketplaceStore $store): array;
}
