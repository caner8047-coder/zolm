<?php

namespace App\Services\Marketplace\Contracts;

use App\Models\ChannelListing;

interface PushesStock
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function pushStock(ChannelListing $listing, int $quantity, array $context = []): array;
}
