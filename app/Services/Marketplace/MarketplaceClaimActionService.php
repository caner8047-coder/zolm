<?php

namespace App\Services\Marketplace;

use App\Models\ChannelClaim;
use App\Services\Marketplace\Contracts\PullsClaims;

class MarketplaceClaimActionService
{
    public function __construct(
        protected MarketplaceConnectorManager $connectorManager,
    ) {
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{success: bool, message: string}
     */
    public function approveClaim(ChannelClaim $claim, array $context = []): array
    {
        $connector = $this->connectorManager->resolve($claim->store->marketplace);

        if (!$connector instanceof PullsClaims) {
            throw new \RuntimeException('Bu kanal iade servisini desteklemiyor.');
        }

        $result = $connector->approveClaim($claim->store, $claim->external_claim_id, $context);

        $claim->update([
            'status' => 'approved',
        ]);

        return [
            'success' => true,
            'message' => $result['message'] ?? 'İade başarıyla onaylandı.',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{success: bool, message: string}
     */
    public function rejectClaim(ChannelClaim $claim, string $reason, array $context = []): array
    {
        $connector = $this->connectorManager->resolve($claim->store->marketplace);

        if (!$connector instanceof PullsClaims) {
            throw new \RuntimeException('Bu kanal iade reddetme servisini desteklemiyor.');
        }

        $result = $connector->rejectClaim($claim->store, $claim->external_claim_id, $reason, $context);

        $claim->update([
            'status' => 'rejected',
        ]);

        return [
            'success' => true,
            'message' => $result['message'] ?? 'İade başarıyla reddedildi.',
        ];
    }
}
