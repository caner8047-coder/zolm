<?php

namespace App\Services\Marketplace\Contracts;

use App\Models\MarketplaceStore;

interface PullsCustomerQuestions
{
    /**
     * @param  array<string, mixed>  $options
     * @return array{items: array<int, array<string, mixed>>, meta?: array<string, mixed>}
     */
    public function pullCustomerQuestions(MarketplaceStore $store, array $options = []): array;
}
