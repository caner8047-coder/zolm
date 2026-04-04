<?php

namespace App\Services\Marketplace\Contracts;

use App\Models\MarketplaceStore;

interface PullsFinancials
{
    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function pullFinancialEvents(MarketplaceStore $store, array $options = []): array;
}
