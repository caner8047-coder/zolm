<?php

namespace App\Services\Marketplace\Contracts;

use App\Models\MarketplaceStore;

interface ManagesClaims
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function approveClaim(MarketplaceStore $store, string $externalClaimId, array $context = []): array;

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function rejectClaim(MarketplaceStore $store, string $externalClaimId, string $reason, array $context = []): array;
}
