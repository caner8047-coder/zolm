<?php

namespace App\Services\WhatsApp\Tools;

use App\Models\ChannelClaim;
use App\Models\MarketplaceStore;
use App\Models\WaContact;

class ReturnStatusTool implements AiTool
{
    public function name(): string { return 'return_status'; }

    public function description(): string
    {
        return 'Müşterinin iade durumunu güvenli biçimde sorgular.';
    }

    public function execute(array $params, int $storeId, ?int $contactId = null): array
    {
        if (!$contactId) {
            return ['found' => false, 'message' => 'Müşteri doğrulaması gerekli.'];
        }

        $store = MarketplaceStore::find($storeId);
        if (!$store || $store->marketplace !== 'woocommerce') {
            return ['found' => false, 'message' => 'WooCommerce mağazası değil.'];
        }

        $claims = ChannelClaim::where('store_id', $storeId)
            ->with('items')
            ->orderByDesc('created_date')
            ->limit(5)
            ->get();

        if ($claims->isEmpty()) {
            return ['found' => false, 'message' => 'İade talebi bulunamadı.'];
        }

        $results = $claims->map(function ($claim) {
            return [
                'claim_id' => $claim->id,
                'status' => $claim->status,
                'status_label' => ChannelClaim::STATUS_LABELS[$claim->status] ?? $claim->status,
                'created_date' => $claim->created_date?->format('d.m.Y'),
            ];
        })->toArray();

        return ['found' => true, 'returns' => $results];
    }
}
