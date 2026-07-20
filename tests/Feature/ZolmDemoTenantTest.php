<?php

namespace Tests\Feature;

use App\Models\AdAccount;
use App\Models\ChannelOrder;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncProfile;
use App\Models\MarketplaceStore;
use App\Models\SupportArtifactVersion;
use App\Models\SupportChannel;
use App\Models\User;
use App\Models\WaAccount;
use App\Models\WaKnowledgeArticle;
use App\Services\Marketplace\MarketplaceProviderRegistry;
use App\Services\Support\CustomerCareKnowledgeGroundingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ZolmDemoTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Queue::fake();
        Mail::fake();
    }

    public function test_full_demo_tenant_is_seeded_without_external_http_and_with_safe_automation_defaults(): void
    {
        $exitCode = Artisan::call('zolm:demo:seed');

        $this->assertSame(0, $exitCode, Artisan::output());

        $user = User::where('email', 'mockdata1@zolm.test')->firstOrFail();
        $stores = MarketplaceStore::where('user_id', $user->id)->get();
        $storeIds = $stores->pluck('id');

        $this->assertSame('admin', $user->roleSlug());
        $this->assertTrue($user->is_active);
        $this->assertCount(count(MarketplaceProviderRegistry::providers()), $stores);
        $this->assertSame(1, $user->legalEntities()->count());

        $this->assertSame($stores->count(), IntegrationConnection::whereIn('store_id', $storeIds)->where('status', 'demo')->count());
        $this->assertSame($stores->count(), IntegrationSyncProfile::whereIn('store_id', $storeIds)->count());
        $this->assertSame(0, IntegrationSyncProfile::whereIn('store_id', $storeIds)
            ->where(function ($query): void {
                $query->where('orders_enabled', true)
                    ->orWhere('finance_enabled', true)
                    ->orWhere('products_enabled', true)
                    ->orWhere('claims_enabled', true)
                    ->orWhere('questions_enabled', true)
                    ->orWhere('webhook_enabled', true)
                    ->orWhere('price_push_enabled', true)
                    ->orWhere('stock_push_enabled', true)
                    ->orWhere('nightly_repair_sync_enabled', true);
            })->count());

        $this->assertGreaterThanOrEqual($stores->count(), ChannelOrder::whereIn('store_id', $storeIds)->count());
        $this->assertDatabaseHas('profiles', ['user_id' => $user->id, 'type' => 'production']);
        $this->assertDatabaseHas('profiles', ['user_id' => $user->id, 'type' => 'operation']);
        $this->assertDatabaseHas('materials', ['user_id' => $user->id, 'code' => 'ZOLM-DEMO-KUMAS']);
        $this->assertDatabaseHas('crm_contacts', ['user_id' => $user->id, 'primary_email' => 'musteri1@example.test']);
        $this->assertDatabaseHas('return_intake_batches', ['user_id' => $user->id, 'source' => 'mockdata1_full_v1']);
        $this->assertDatabaseHas('shipments', ['user_id' => $user->id, 'status' => 'delivered']);

        $this->assertSame(0, AdAccount::where('user_id', $user->id)->where('is_active', true)->count());
        $this->assertSame(0, WaAccount::whereIn('store_id', $storeIds)->where('is_active', true)->count());
        $this->assertSame(0, SupportChannel::whereIn('store_id', $storeIds)->where('is_enabled', true)->count());
        $this->assertDatabaseCount('wa_outbox', 0);

        $trendyolStore = $stores->firstWhere('marketplace', 'trendyol');
        $this->assertNotNull($trendyolStore);
        $knowledgeArticle = WaKnowledgeArticle::where('store_id', $trendyolStore->id)
            ->where('slug', 'zolm-demo-kargo-ve-iade-rehberi')
            ->firstOrFail();
        $artifactVersion = SupportArtifactVersion::where('store_id', $trendyolStore->id)
            ->where('artifact_type', 'knowledge_article')
            ->where('artifact_id', $knowledgeArticle->id)
            ->where('is_current', true)
            ->firstOrFail();

        $this->assertSame('published', $knowledgeArticle->status);
        $this->assertNotNull($artifactVersion->releasePackage);
        $this->assertSame('published', $artifactVersion->releasePackage->status);

        Config::set('customer-care.release_center_enabled', true);
        $grounding = app(CustomerCareKnowledgeGroundingService::class)
            ->ground($trendyolStore->id, 'Demo ürün kargo süresi ve iade');
        $this->assertStringContainsString('iki iş günü', $grounding['kb']);
        $this->assertContains('knowledge_article', array_column($grounding['citations'], 'type'));
    }

    public function test_full_demo_seed_is_idempotent_and_auditable(): void
    {
        $this->assertSame(0, Artisan::call('zolm:demo:seed'));

        $tables = [
            'users',
            'legal_entities',
            'marketplace_stores',
            'integration_connections',
            'integration_sync_profiles',
            'channel_products',
            'channel_listings',
            'channel_orders',
            'order_financial_events',
            'crm_contacts',
            'return_intake_batches',
            'shipments',
            'ad_accounts',
            'trendyol_booster_products',
            'wa_accounts',
            'support_channels',
            'wa_knowledge_articles',
            'support_release_packages',
            'support_release_package_items',
            'support_release_events',
            'support_artifact_versions',
        ];
        $before = collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);

        $this->assertSame(0, Artisan::call('zolm:demo:seed'), Artisan::output());

        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), "{$table} tablosu idempotent değil.");
        }

        $auditExitCode = Artisan::call('zolm:demo:audit');
        $auditOutput = Artisan::output();

        $this->assertSame(0, $auditExitCode, $auditOutput);
        $this->assertStringContainsString('uygulama-içi sağlık denetimi başarılı', $auditOutput);
    }

    public function test_seed_command_is_fail_closed_outside_local_and_testing(): void
    {
        $originalEnvironment = $this->app->environment();
        $this->app->detectEnvironment(fn () => 'production');

        try {
            $this->assertSame(1, Artisan::call('zolm:demo:seed'));
            $this->assertDatabaseMissing('users', ['email' => 'mockdata1@zolm.test']);
        } finally {
            $this->app->detectEnvironment(fn () => $originalEnvironment);
        }
    }
}
