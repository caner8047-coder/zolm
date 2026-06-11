<?php

namespace App\Services\Marketplace;

use App\Models\ChannelClaim;
use App\Services\Marketplace\Contracts\ManagesClaims;

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
        $claim->loadMissing(['store.connection', 'items']);
        $connector = $this->connectorManager->resolve($claim->store->marketplace);

        if (!$connector instanceof ManagesClaims || !(bool) ($connector->capabilities()['claim_approve'] ?? false)) {
            throw new \RuntimeException('Bu kanal iade onayını desteklemiyor.');
        }

        $result = $connector->approveClaim($claim->store, $claim->external_claim_id, $this->actionContext($claim, $context));

        $claim->update([
            'status' => $result['status'] ?? 'approved',
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
        $claim->loadMissing(['store.connection', 'items']);
        $connector = $this->connectorManager->resolve($claim->store->marketplace);

        if (!$connector instanceof ManagesClaims || !(bool) ($connector->capabilities()['claim_reject'] ?? false)) {
            throw new \RuntimeException('Bu kanal iade reddetme servisini desteklemiyor.');
        }

        $result = $connector->rejectClaim($claim->store, $claim->external_claim_id, $reason, $this->actionContext($claim, $context));

        $claim->update([
            'status' => $result['status'] ?? 'rejected',
        ]);

        return [
            'success' => true,
            'message' => $result['message'] ?? 'İade başarıyla reddedildi.',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function actionContext(ChannelClaim $claim, array $context): array
    {
        $items = $claim->items
            ->map(fn ($item) => [
                'external_item_id' => $item->external_item_id,
                'external_order_line_id' => $item->external_order_line_id,
                'quantity' => $item->quantity,
                'status' => $item->status,
                'raw_payload' => $item->raw_payload,
            ])
            ->values()
            ->all();

        return array_replace([
            'claim' => $claim->toArray(),
            'items' => $items,
            'external_item_ids' => $claim->items->pluck('external_item_id')->filter()->values()->all(),
            'claim_item_ids' => $claim->items->pluck('external_item_id')->filter()->values()->all(),
            'external_order_line_ids' => $claim->items->pluck('external_order_line_id')->filter()->values()->all(),
        ], $context);
    }
}
