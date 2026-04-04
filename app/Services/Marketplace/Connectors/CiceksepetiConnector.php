<?php

namespace App\Services\Marketplace\Connectors;

use App\Models\MarketplaceStore;

class CiceksepetiConnector extends AbstractMarketplaceConnector
{
    public function providerKey(): string
    {
        return 'ciceksepeti';
    }

    public function displayName(): string
    {
        return 'Çiçeksepeti';
    }

    public function defaultApiBaseUrl(): ?string
    {
        return config('marketplace.ciceksepeti.base_url');
    }

    /**
     * @return array<string, bool>
     */
    public function capabilities(): array
    {
        return [
            'orders' => false,
            'products' => false,
            'finance' => false,
            'webhooks' => false,
            'price_push' => false,
            'stock_push' => false,
            'package_status' => false,
            'package_picking' => false,
            'package_invoiced' => false,
            'common_label' => false,
            'package_common_label_create' => false,
            'package_common_label_get' => false,
            'invoice_link' => false,
            'package_invoice_link' => false,
        ];
    }

    public function testConnection(MarketplaceStore $store): array
    {
        return [
            'ok' => false,
            'message' => 'Çiçeksepeti bağlayıcısı şimdilik güvenli skeleton aşamasında. Resmi endpoint ve credential modeli netleşmeden canlı doğrulama yapılmaz.',
            'meta' => [
                'provider' => $this->providerKey(),
                'mode' => 'skeleton',
                'store_id' => $store->id,
            ],
        ];
    }
}
