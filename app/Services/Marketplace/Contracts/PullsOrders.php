<?php

namespace App\Services\Marketplace\Contracts;

use App\Models\MarketplaceStore;

interface PullsOrders
{
    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function pullOrders(MarketplaceStore $store, array $options = []): array;
}
