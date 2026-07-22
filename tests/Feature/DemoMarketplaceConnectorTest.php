<?php

namespace Tests\Feature;

use App\Models\ChannelListing;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderPackage;
use App\Models\IntegrationConnection;
use App\Models\IntegrationOrderActionRun;
use App\Models\IntegrationSyncProfile;
use App\Models\IntegrationSyncRun;
use App\Models\LegalEntity;
use App\Models\MarketplaceQuestion;
use App\Models\MarketplaceStore;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Marketplace\Connectors\DemoMarketplaceConnector;
use App\Services\Marketplace\Connectors\TrendyolConnector;
use App\Services\Marketplace\Contracts\AnswersCustomerQuestions;
use App\Services\Marketplace\Contracts\ManagesClaims;
use App\Services\Marketplace\Contracts\ManagesCommonLabels;
use App\Services\Marketplace\Contracts\MarketplaceConnector;
use App\Services\Marketplace\Contracts\PullsClaims;
use App\Services\Marketplace\Contracts\PullsCustomerQuestions;
use App\Services\Marketplace\Contracts\PullsFinancials;
use App\Services\Marketplace\Contracts\PullsOrders;
use App\Services\Marketplace\Contracts\PullsProducts;
use App\Services\Marketplace\Contracts\PushesPrice;
use App\Services\Marketplace\Contracts\PushesStock;
use App\Services\Marketplace\Contracts\ReceivesWebhooks;
use App\Services\Marketplace\Contracts\SendsInvoiceLinks;
use App\Services\Marketplace\Contracts\TestsConnection;
use App\Services\Marketplace\Contracts\UpdatesPackageStatus;
use App\Services\Marketplace\MarketplaceConnectorManager;
use App\Services\Marketplace\MarketplaceOrderActionService;
use App\Services\Marketplace\MarketplaceSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DemoMarketplaceConnectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_resolves_demo_per_store_without_changing_provider_only_resolution(): void
    {
        $manager = app(MarketplaceConnectorManager::class);
        $store = new MarketplaceStore(['marketplace' => 'trendyol']);

        $store->setRelation('connection', new IntegrationConnection(['status' => 'configured']));

        $this->assertInstanceOf(TrendyolConnector::class, $manager->resolve('trendyol'));
        $this->assertInstanceOf(TrendyolConnector::class, $manager->resolveForStore($store));

        $store->setRelation('connection', new IntegrationConnection([
            'status' => IntegrationConnection::STATUS_DEMO,
        ]));

        $connector = $manager->resolveForStore($store);

        $this->assertInstanceOf(DemoMarketplaceConnector::class, $connector);
        $this->assertSame('trendyol', $connector->providerKey());
        $this->assertSame('Trendyol (Demo)', $connector->displayName());
    }

    public function test_demo_connector_implements_every_marketplace_contract_and_returns_deterministic_success(): void
    {
        Http::preventStrayRequests();

        $store = new MarketplaceStore(['marketplace' => 'trendyol']);
        $store->setRelation('connection', new IntegrationConnection([
            'status' => IntegrationConnection::STATUS_DEMO,
        ]));

        $connector = app(MarketplaceConnectorManager::class)->resolveForStore($store);

        foreach ([
            MarketplaceConnector::class,
            TestsConnection::class,
            ReceivesWebhooks::class,
            PullsOrders::class,
            PullsProducts::class,
            PullsFinancials::class,
            PullsCustomerQuestions::class,
            PullsClaims::class,
            PushesPrice::class,
            PushesStock::class,
            AnswersCustomerQuestions::class,
            ManagesClaims::class,
            UpdatesPackageStatus::class,
            ManagesCommonLabels::class,
            SendsInvoiceLinks::class,
        ] as $contract) {
            $this->assertInstanceOf($contract, $connector);
        }

        $this->assertNotEmpty($connector->capabilities());
        $this->assertNotContains(false, $connector->capabilities());
        $this->assertTrue($connector->testConnection($store)['ok']);

        $options = ['end_date' => '2026-07-19T12:00:00+03:00'];

        foreach (['pullOrders', 'pullProducts', 'pullFinancialEvents', 'pullCustomerQuestions', 'pullClaims'] as $method) {
            $response = $connector->{$method}($store, $options);

            $this->assertSame([], $response['items']);
            $this->assertSame('demo', $response['meta']['mode']);
            $this->assertSame($options['end_date'], $response['meta']['cursor_after']);
        }

        $listing = new ChannelListing(['listing_id' => 'DEMO-LISTING-1']);
        $firstPricePush = $connector->pushPrice($listing, 1499.90);
        $secondPricePush = $connector->pushPrice($listing, 1499.90);

        $this->assertTrue($firstPricePush['success']);
        $this->assertSame($firstPricePush['external_action_id'], $secondPricePush['external_action_id']);
        $this->assertSame('demo', $connector->pushStock($listing, 12)['mode']);

        $question = new MarketplaceQuestion(['external_question_id' => 'DEMO-QUESTION-1']);
        $this->assertStringStartsWith(
            'demo-trendyol-question-answer-',
            $connector->answerCustomerQuestion($question, 'Demo soru cevabı')['external_answer_id'],
        );

        $package = new ChannelOrderPackage([
            'external_package_id' => 'DEMO-PACKAGE-1',
            'cargo_barcode' => 'DEMO-BARCODE-1',
        ]);

        $this->assertSame('Picking', $connector->notifyPackagePicking($package)['status']);
        $this->assertSame('Invoiced', $connector->notifyPackageInvoiced($package)['status']);
        $this->assertSame('DEMO-BARCODE-1', $connector->createCommonLabel($package)['cargo_barcode']);
        $this->assertSame('completed', $connector->getCommonLabel($package)['status']);
        $this->assertSame('https://example.test/invoice/1', $connector->sendInvoiceLink(
            $package,
            'https://example.test/invoice/1',
        )['invoice_link']);

        $this->assertSame('approved', $connector->approveClaim($store, 'DEMO-CLAIM-1')['status']);
        $this->assertSame('rejected', $connector->rejectClaim($store, 'DEMO-CLAIM-1', 'Demo red nedeni')['status']);

        Http::assertNothingSent();
    }

    public function test_demo_sync_completes_without_http_and_preserves_demo_connection_status(): void
    {
        Http::preventStrayRequests();

        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);
        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'ZOLM Demo Connector Ltd.',
            'tax_number' => '5'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZOLM DEMO CONNECTOR',
            'store_code' => 'DEMO-CONNECTOR-'.$suffix,
            'seller_id' => 'DEMO-SELLER-'.$suffix,
            'status' => 'connected',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $connection = IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'auth_type' => 'demo',
            'credentials_encrypted' => [],
            'api_base_url' => null,
            'status' => IntegrationConnection::STATUS_DEMO,
        ]);
        IntegrationSyncProfile::query()->create(array_merge(
            ['store_id' => $store->id],
            IntegrationSyncProfile::defaultsForMarketplace('trendyol'),
        ));
        $run = IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'orders',
            'trigger_type' => 'manual',
            'status' => 'queued',
            'notes_json' => [
                'options' => [
                    'start_date' => '2026-07-18T12:00:00+03:00',
                    'end_date' => '2026-07-19T12:00:00+03:00',
                ],
            ],
        ]);

        app(MarketplaceSyncService::class)->run($run->id);

        $this->assertSame('completed', $run->fresh()->status);
        $this->assertSame(0, $run->fresh()->items_received);
        $this->assertSame('demo', data_get($run->fresh()->notes_json, 'meta.mode'));
        $this->assertSame(IntegrationConnection::STATUS_DEMO, $connection->fresh()->status);
        $this->assertNull($connection->fresh()->last_verified_at);

        $order = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $legalEntity->id,
            'external_order_id' => 'DEMO-ORDER-'.$suffix,
            'order_number' => 'DEMO-ORDER-'.$suffix,
            'order_status' => 'new',
        ]);
        $package = ChannelOrderPackage::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'external_package_id' => 'DEMO-PACKAGE-'.$suffix,
            'package_status' => 'new',
        ]);
        $actionRun = IntegrationOrderActionRun::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'channel_order_package_id' => $package->id,
            'triggered_by' => $user->id,
            'action_type' => 'cargo_create_surat_shipment',
            'status' => 'queued',
            'request_context_json' => ['carrier' => 'surat'],
        ]);

        app(MarketplaceOrderActionService::class)->run($actionRun->id, 1);

        $this->assertSame('completed', $actionRun->fresh()->status);
        $this->assertSame('demo', data_get($actionRun->fresh()->response_json, 'mode'));
        $this->assertSame('create_shipment', data_get($actionRun->fresh()->response_json, 'action'));
        $this->assertFalse(Shipment::query()->where('channel_order_package_id', $package->id)->exists());

        Http::assertNothingSent();
    }
}
