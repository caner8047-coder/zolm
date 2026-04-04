<?php

namespace Tests\Feature;

use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Connectors\AmazonConnector;
use App\Services\Marketplace\MarketplaceConnectorManager;
use Tests\TestCase;

class AmazonConnectorTest extends TestCase
{
    public function test_manager_resolves_amazon_connector(): void
    {
        $manager = app(MarketplaceConnectorManager::class);
        $connector = $manager->resolve('amazon');

        $this->assertInstanceOf(AmazonConnector::class, $connector);
        $this->assertSame('Amazon', $connector->displayName());
        $this->assertFalse($connector->capabilities()['orders']);
        $this->assertFalse($connector->capabilities()['products']);
        $this->assertFalse($connector->capabilities()['finance']);
    }

    public function test_it_returns_skeleton_message_for_amazon_connection_test(): void
    {
        $store = new MarketplaceStore([
            'marketplace' => 'amazon',
            'store_name' => 'Amazon Test',
            'seller_id' => 'amazon-demo',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
        ]);

        $store->setRelation('connection', new IntegrationConnection([
            'provider' => 'amazon',
            'auth_type' => 'api_key_secret',
            'credentials_encrypted' => [
                'api_key' => 'amazon-key',
                'api_secret' => 'amazon-secret',
            ],
            'api_base_url' => '',
            'status' => 'configured',
        ]));

        $result = app(AmazonConnector::class)->testConnection($store);

        $this->assertFalse($result['ok']);
        $this->assertSame('skeleton', data_get($result, 'meta.mode'));
        $this->assertStringContainsString('skeleton', mb_strtolower((string) $result['message']));
    }
}
