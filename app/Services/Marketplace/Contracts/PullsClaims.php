<?php

namespace App\Services\Marketplace\Contracts;

use App\Models\MarketplaceStore;

interface PullsClaims
{
    /**
     * @param  array<string, mixed>  $options
     * @return array{items: array<int, array<string, mixed>>, meta?: array<string, mixed>}
     */
    public function pullClaims(MarketplaceStore $store, array $options = []): array;
}
