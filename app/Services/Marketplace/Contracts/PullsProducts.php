<?php

namespace App\Services\Marketplace\Contracts;

use App\Models\MarketplaceStore;

interface PullsProducts
{
    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function pullProducts(MarketplaceStore $store, array $options = []): array;
}
