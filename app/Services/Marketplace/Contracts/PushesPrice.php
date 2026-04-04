<?php

namespace App\Services\Marketplace\Contracts;

use App\Models\ChannelListing;

interface PushesPrice
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function pushPrice(ChannelListing $listing, float $price, array $context = []): array;
}
