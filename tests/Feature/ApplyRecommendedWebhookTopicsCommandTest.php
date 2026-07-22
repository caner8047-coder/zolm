<?php

namespace Tests\Feature;

use App\Models\IntegrationSyncProfile;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApplyRecommendedWebhookTopicsCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', 'mysql');
        config()->set('database.connections.mysql.port', '3306');
        config()->set('database.connections.mysql.database', $this->mysqlTestDatabaseName());
        config()->set('database.connections.mysql.username', 'sail');
        config()->set('database.connections.mysql.password', 'password');
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }

    public function test_it_can_apply_recommended_webhook_topics_to_single_shopify_store(): void
    {
        $store = $this->createStore('shopify', 'SHOP-TOPIC-CMD');

        IntegrationSyncProfile::query()->create([
            'store_id' => $store->id,
            'orders_poll_minutes' => 18,
            'finance_poll_minutes' => 180,
            'products_poll_minutes' => 300,
            'backfill_mode' => '7_days',
            'backfill_days' => 7,
            'orders_enabled' => true,
            'finance_enabled' => true,
            'products_enabled' => true,
            'webhook_enabled' => false,
            'price_push_enabled' => false,
            'stock_push_enabled' => false,
            'auto_match_enabled' => true,
            'barcode_fallback_enabled' => true,
            'strict_unique_match_enabled' => true,
            'nightly_repair_sync_enabled' => true,
            'max_parallel_jobs' => 1,
            'request_jitter_seconds' => 8,
            'extra_settings' => [
                'webhook_topics' => ['orders/create'],
            ],
        ]);

        $this->artisan('marketplace:apply-recommended-webhook-topics', [
            '--store' => $store->id,
        ])->assertExitCode(0);

        $profile = $store->fresh()->syncProfile;

        $this->assertNotNull($profile);
        $this->assertTrue($profile->webhook_enabled);
        $this->assertSame(
            IntegrationSyncProfile::recommendedShopifyWebhookTopics(),
            data_get($profile->extra_settings ?? [], 'webhook_topics', [])
        );
        $this->assertSame(18, $profile->orders_poll_minutes);
        $this->assertSame(180, $profile->finance_poll_minutes);
    }

    public function test_it_can_apply_recommended_webhook_topics_to_all_woocommerce_stores_in_dry_run_mode(): void
    {
        $store = $this->createStore('woocommerce', 'WOO-TOPIC-CMD');

        IntegrationSyncProfile::query()->create([
            'store_id' => $store->id,
            'orders_poll_minutes' => 30,
            'finance_poll_minutes' => 360,
            'products_poll_minutes' => 720,
            'backfill_mode' => '7_days',
            'backfill_days' => 7,
            'orders_enabled' => true,
            'finance_enabled' => false,
            'products_enabled' => true,
            'webhook_enabled' => false,
            'price_push_enabled' => false,
            'stock_push_enabled' => false,
            'auto_match_enabled' => true,
            'barcode_fallback_enabled' => true,
            'strict_unique_match_enabled' => true,
            'nightly_repair_sync_enabled' => true,
            'max_parallel_jobs' => 1,
            'request_jitter_seconds' => 15,
            'extra_settings' => [
                'webhook_topics' => ['order.updated'],
            ],
        ]);

        $this->artisan('marketplace:apply-recommended-webhook-topics', [
            '--all' => true,
            '--marketplace' => 'woocommerce',
            '--dry-run' => true,
        ])->assertExitCode(0);

        $profile = $store->fresh()->syncProfile;

        $this->assertFalse($profile->webhook_enabled);
        $this->assertSame(['order.updated'], data_get($profile->extra_settings ?? [], 'webhook_topics', []));
    }

    public function test_it_skips_unsupported_marketplaces_when_selected_by_store(): void
    {
        $store = $this->createStore('n11', 'N11-TOPIC-CMD');

        $this->artisan('marketplace:apply-recommended-webhook-topics', [
            '--store' => $store->id,
        ])->assertExitCode(0);

        $this->assertNull($store->fresh()->syncProfile);
    }

    protected function createStore(string $marketplace, string $prefix): MarketplaceStore
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Topic Command Ltd.',
            'tax_number' => '9'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        return MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => $marketplace,
            'store_name' => $prefix,
            'store_code' => $prefix.'-'.$suffix,
            'seller_id' => $prefix.'-'.$suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
    }
}
