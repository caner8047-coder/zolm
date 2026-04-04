<?php

namespace App\Services\Marketplace;

use App\Services\Marketplace\Connectors\GenericMarketplaceConnector;
use App\Services\Marketplace\Connectors\AmazonConnector;
use App\Services\Marketplace\Connectors\CiceksepetiConnector;
use App\Services\Marketplace\Connectors\HepsiburadaConnector;
use App\Services\Marketplace\Connectors\KoctasConnector;
use App\Services\Marketplace\Connectors\N11Connector;
use App\Services\Marketplace\Connectors\PazaramaConnector;
use App\Services\Marketplace\Connectors\ShopifyConnector;
use App\Services\Marketplace\Connectors\TrendyolConnector;
use App\Services\Marketplace\Connectors\WooCommerceConnector;
use App\Services\Marketplace\Contracts\MarketplaceConnector;

class MarketplaceConnectorManager
{
    public function resolve(string $provider): MarketplaceConnector
    {
        $normalizedProvider = MarketplaceProviderRegistry::normalize($provider);

        return match ($normalizedProvider) {
            'trendyol' => app(TrendyolConnector::class),
            'hepsiburada' => app(HepsiburadaConnector::class),
            'n11' => app(N11Connector::class),
            'koctas' => app(KoctasConnector::class),
            'pazarama' => app(PazaramaConnector::class),
            'amazon' => app(AmazonConnector::class),
            'ciceksepeti' => app(CiceksepetiConnector::class),
            'woocommerce' => app(WooCommerceConnector::class),
            'shopify' => app(ShopifyConnector::class),
            default => new GenericMarketplaceConnector($normalizedProvider, MarketplaceProviderRegistry::get($normalizedProvider)),
        };
    }
}
