<?php

namespace Tests\Feature;

use App\Models\IntegrationSyncProfile;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApplyShopifySafeProfileCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', 'mysql');
        config()->set('database.connections.mysql.port', '3306');
        config()->set('database.connections.mysql.database', 'zolm');
        config()->set('database.connections.mysql.username', 'sail');
        config()->set('database.connections.mysql.password', 'password');
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }

    public function test_it_can_apply_safe_profile_to_single_shopify_store(): void
    {
        $store = $this->createShopifyStore('SHOP-CMD-ONE');

        IntegrationSyncProfile::query()->create([
            'store_id' => $store->id,
            'orders_poll_minutes' => 8,
            'finance_poll_minutes' => 30,
            'products_poll_minutes' => 120,
            'backfill_mode' => 'custom',
            'backfill_custom_from' => now()->subDays(2),
            'backfill_custom_to' => now(),
            'orders_enabled' => true,
            'finance_enabled' => true,
            'products_enabled' => true,
            'webhook_enabled' => false,
            'price_push_enabled' => true,
            'stock_push_enabled' => true,
            'auto_match_enabled' => true,
            'barcode_fallback_enabled' => true,
            'strict_unique_match_enabled' => true,
            'nightly_repair_sync_enabled' => false,
            'max_parallel_jobs' => 4,
            'request_jitter_seconds' => 1,
        ]);

        $this->artisan('marketplace:apply-shopify-safe-profile', [
            '--store' => $store->id,
        ])->assertExitCode(0);

        $profile = $store->fresh()->syncProfile;

        $this->assertNotNull($profile);
        $this->assertSame(20, $profile->orders_poll_minutes);
        $this->assertSame(240, $profile->finance_poll_minutes);
        $this->assertSame(720, $profile->products_poll_minutes);
        $this->assertSame('7_days', $profile->backfill_mode);
        $this->assertSame(
            IntegrationSyncProfile::recommendedShopifyWebhookTopics(),
            data_get($profile->extra_settings ?? [], 'webhook_topics', [])
        );
        $this->assertTrue($profile->finance_enabled);
        $this->assertFalse($profile->price_push_enabled);
        $this->assertFalse($profile->stock_push_enabled);
        $this->assertTrue($profile->webhook_enabled);
        $this->assertSame(1, $profile->max_parallel_jobs);
        $this->assertSame(10, $profile->request_jitter_seconds);
    }

    public function test_it_does_not_write_in_dry_run_mode(): void
    {
        $store = $this->createShopifyStore('SHOP-CMD-DRY');

        IntegrationSyncProfile::query()->create([
            'store_id' => $store->id,
            'orders_poll_minutes' => 12,
            'finance_poll_minutes' => 60,
            'products_poll_minutes' => 180,
            'backfill_mode' => '30_days',
            'backfill_days' => 30,
            'orders_enabled' => true,
            'finance_enabled' => true,
            'products_enabled' => true,
            'webhook_enabled' => true,
            'price_push_enabled' => true,
            'stock_push_enabled' => true,
            'auto_match_enabled' => true,
            'barcode_fallback_enabled' => true,
            'strict_unique_match_enabled' => true,
            'nightly_repair_sync_enabled' => true,
            'max_parallel_jobs' => 3,
            'request_jitter_seconds' => 4,
        ]);

        $this->artisan('marketplace:apply-shopify-safe-profile', [
            '--store' => $store->id,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $profile = $store->fresh()->syncProfile;

        $this->assertSame(12, $profile->orders_poll_minutes);
        $this->assertTrue($profile->price_push_enabled);
        $this->assertSame(3, $profile->max_parallel_jobs);
    }

    protected function createShopifyStore(string $prefix): MarketplaceStore
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Shopify Command Ltd.',
            'tax_number' => '1' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        return MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'shopify',
            'store_name' => $prefix,
            'store_code' => $prefix . '-' . $suffix,
            'seller_id' => $prefix . '-' . $suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
    }
}
