<?php

namespace Tests\Feature;

use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Connectors\PazaramaConnector;
use App\Services\Marketplace\MarketplaceConnectorManager;
use Tests\TestCase;

class PazaramaConnectorTest extends TestCase
{
    public function test_manager_resolves_pazarama_connector(): void
    {
        $manager = app(MarketplaceConnectorManager::class);
        $connector = $manager->resolve('pazarama');

        $this->assertInstanceOf(PazaramaConnector::class, $connector);
        $this->assertSame('Pazarama', $connector->displayName());
        $this->assertFalse($connector->capabilities()['orders']);
        $this->assertFalse($connector->capabilities()['products']);
        $this->assertFalse($connector->capabilities()['finance']);
    }

    public function test_it_returns_skeleton_message_for_pazarama_connection_test(): void
    {
        $store = new MarketplaceStore([
            'marketplace' => 'pazarama',
            'store_name' => 'Pazarama Test',
            'seller_id' => 'pazarama-demo',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
        ]);

        $store->setRelation('connection', new IntegrationConnection([
            'provider' => 'pazarama',
            'auth_type' => 'api_key_secret',
            'credentials_encrypted' => [
                'api_key' => 'pazarama-key',
                'api_secret' => 'pazarama-secret',
            ],
            'api_base_url' => '',
            'status' => 'configured',
        ]));

        $result = app(PazaramaConnector::class)->testConnection($store);

        $this->assertFalse($result['ok']);
        $this->assertSame('skeleton', data_get($result, 'meta.mode'));
        $this->assertStringContainsString('skeleton', mb_strtolower((string) $result['message']));
    }
}
