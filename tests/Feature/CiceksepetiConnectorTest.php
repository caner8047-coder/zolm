<?php

namespace Tests\Feature;

use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Connectors\CiceksepetiConnector;
use App\Services\Marketplace\MarketplaceConnectorManager;
use Tests\TestCase;

class CiceksepetiConnectorTest extends TestCase
{
    public function test_manager_resolves_ciceksepeti_connector(): void
    {
        $manager = app(MarketplaceConnectorManager::class);
        $connector = $manager->resolve('ciceksepeti');

        $this->assertInstanceOf(CiceksepetiConnector::class, $connector);
        $this->assertSame('Çiçeksepeti', $connector->displayName());
        $this->assertFalse($connector->capabilities()['orders']);
        $this->assertFalse($connector->capabilities()['products']);
        $this->assertFalse($connector->capabilities()['finance']);
    }

    public function test_it_returns_skeleton_message_for_ciceksepeti_connection_test(): void
    {
        $store = new MarketplaceStore([
            'marketplace' => 'ciceksepeti',
            'store_name' => 'Çiçeksepeti Test',
            'seller_id' => 'ciceksepeti-demo',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
        ]);

        $store->setRelation('connection', new IntegrationConnection([
            'provider' => 'ciceksepeti',
            'auth_type' => 'api_key_secret',
            'credentials_encrypted' => [
                'api_key' => 'ciceksepeti-key',
                'api_secret' => 'ciceksepeti-secret',
            ],
            'api_base_url' => '',
            'status' => 'configured',
        ]));

        $result = app(CiceksepetiConnector::class)->testConnection($store);

        $this->assertFalse($result['ok']);
        $this->assertSame('skeleton', data_get($result, 'meta.mode'));
        $this->assertStringContainsString('skeleton', mb_strtolower((string) $result['message']));
    }
}
