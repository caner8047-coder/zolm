<?php

namespace Tests\Feature;

use App\Models\IntegrationSyncRun;
use App\Models\IntegrationWebhookEvent;
use App\Models\MarketplaceStore;
use App\Models\Shipment;
use App\Models\SupportArtifactVersion;
use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportDispatch;
use App\Models\SupportDispatchAttempt;
use App\Models\SupportMessage;
use App\Models\User;
use App\Models\WaAccount;
use App\Models\WaInboundMessage;
use App\Models\WaKnowledgeArticle;
use App\Services\Demo\ZolmDemoTenantAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ZolmDemoTenantHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Queue::fake();
        Mail::fake();
    }

    public function test_similar_email_slugs_keep_distinct_store_sets_and_original_tenant_ownership(): void
    {
        $firstUser = $this->seedTenant('foo.bar@zolm.test');
        $firstStores = MarketplaceStore::where('user_id', $firstUser->id)->get();
        $firstStoreIds = $firstStores->pluck('id')->sort()->values()->all();
        $firstSellerIds = $firstStores->pluck('seller_id')->sort()->values()->all();

        $secondUser = $this->seedTenant('foo-bar@zolm.test');
        $secondStores = MarketplaceStore::where('user_id', $secondUser->id)->get();
        $reloadedFirstStores = MarketplaceStore::where('user_id', $firstUser->id)->get();

        $this->assertCount(9, $reloadedFirstStores);
        $this->assertCount(9, $secondStores);
        $this->assertSame(
            $firstStoreIds,
            $reloadedFirstStores->pluck('id')->sort()->values()->all(),
            'İkinci tenant seed işlemi ilk tenant mağazalarını değiştirmemelidir.'
        );
        $this->assertSame(
            $firstSellerIds,
            $reloadedFirstStores->pluck('seller_id')->sort()->values()->all(),
            'İlk tenant seller ID değerlerini korumalıdır.'
        );
        $this->assertSame(
            [],
            array_values(array_intersect($firstSellerIds, $secondStores->pluck('seller_id')->all())),
            'Benzer slug üreten e-postalar seller ID paylaşmamalıdır.'
        );
        $this->assertSame(
            0,
            MarketplaceStore::whereIn('id', $firstStoreIds)
                ->where('user_id', '!=', $firstUser->id)
                ->count(),
            'İlk tenant mağazalarının ownership bilgisi korunmalıdır.'
        );
        $this->assertSame(18, MarketplaceStore::whereIn('user_id', [$firstUser->id, $secondUser->id])->count());
    }

    public function test_reset_removes_restricted_and_null_on_delete_roots_before_reseeding_single_fixtures(): void
    {
        $user = $this->seedTenant();
        $stores = MarketplaceStore::where('user_id', $user->id)->get();
        $oldStoreIds = $stores->pluck('id')->all();
        $trendyolStore = $stores->firstWhere('marketplace', 'trendyol');
        $channel = SupportChannel::where('store_id', $trendyolStore->id)
            ->where('key', 'trendyol')
            ->firstOrFail();
        $conversation = SupportConversation::where('support_channel_id', $channel->id)->firstOrFail();
        $inboundMessage = SupportMessage::where('conversation_id', $conversation->id)->firstOrFail();
        $waAccount = WaAccount::whereIn('store_id', $oldStoreIds)->firstOrFail();
        $knowledgeArticle = WaKnowledgeArticle::whereIn('store_id', $oldStoreIds)->firstOrFail();
        [$outboundMessage, $dispatch, $attempt] = $this->createPendingDispatch($channel, $conversation, 'reset');
        $webhookEvent = IntegrationWebhookEvent::create([
            'store_id' => $trendyolStore->id,
            'provider' => 'trendyol',
            'event_type' => 'order.updated',
            'external_event_id' => 'zolm-hardening-reset-webhook',
            'signature_valid' => true,
            'payload_json' => ['fixture' => 'hardening-reset'],
            'received_at' => now(),
            'status' => 'received',
        ]);

        $oldIds = [
            'user' => $user->id,
            'channel' => $channel->id,
            'conversation' => $conversation->id,
            'inbound_message' => $inboundMessage->id,
            'outbound_message' => $outboundMessage->id,
            'dispatch' => $dispatch->id,
            'attempt' => $attempt->id,
            'webhook' => $webhookEvent->id,
            'wa_account' => $waAccount->id,
            'knowledge_article' => $knowledgeArticle->id,
        ];

        $newUser = $this->seedTenant('mockdata1@zolm.test', true);
        $newStoreIds = MarketplaceStore::where('user_id', $newUser->id)->pluck('id');

        $this->assertNotSame($oldIds['user'], $newUser->id);
        $this->assertSame(0, MarketplaceStore::whereIn('id', $oldStoreIds)->count());
        $this->assertDatabaseMissing('support_channels', ['id' => $oldIds['channel']]);
        $this->assertDatabaseMissing('support_conversations', ['id' => $oldIds['conversation']]);
        $this->assertDatabaseMissing('support_messages', ['id' => $oldIds['inbound_message']]);
        $this->assertDatabaseMissing('support_messages', ['id' => $oldIds['outbound_message']]);
        $this->assertDatabaseMissing('support_dispatches', ['id' => $oldIds['dispatch']]);
        $this->assertDatabaseMissing('support_dispatch_attempts', ['id' => $oldIds['attempt']]);
        $this->assertDatabaseMissing('integration_webhook_events', ['id' => $oldIds['webhook']]);
        $this->assertDatabaseMissing('wa_accounts', ['id' => $oldIds['wa_account']]);
        $this->assertDatabaseMissing('wa_knowledge_articles', ['id' => $oldIds['knowledge_article']]);

        $this->assertCount(9, $newStoreIds);
        $this->assertSame(9, SupportChannel::whereIn('store_id', $newStoreIds)->count());
        $this->assertSame(9, SupportChannel::count());
        $this->assertSame(1, WaAccount::whereIn('store_id', $newStoreIds)->count());
        $this->assertSame(1, WaAccount::count());
        $this->assertSame(0, SupportChannel::whereNull('store_id')->count());
        $this->assertSame(0, SupportDispatch::count());
        $this->assertSame(0, SupportDispatchAttempt::count());
        $this->assertSame(0, IntegrationWebhookEvent::count());
    }

    public function test_pending_support_dispatch_makes_customer_care_audit_fail(): void
    {
        $user = $this->seedTenant();
        $trendyolStore = MarketplaceStore::where('user_id', $user->id)
            ->where('marketplace', 'trendyol')
            ->firstOrFail();
        $channel = SupportChannel::where('store_id', $trendyolStore->id)->firstOrFail();
        $conversation = SupportConversation::where('support_channel_id', $channel->id)->firstOrFail();

        $this->createPendingDispatch($channel, $conversation, 'audit');

        $audit = app(ZolmDemoTenantAuditor::class)->audit('mockdata1@zolm.test', 'password');
        $customerCareFinding = collect($audit['findings'])->firstWhere('area', 'Customer Care');

        $this->assertFalse($audit['healthy']);
        $this->assertNotNull($customerCareFinding);
        $this->assertSame('fail', $customerCareFinding['status']);
        $this->assertStringContainsString('gönderilebilir dispatch=1', $customerCareFinding['detail']);
    }

    public function test_reseed_preserves_a_newer_current_artifact_version(): void
    {
        $user = $this->seedTenant();
        $trendyolStore = MarketplaceStore::where('user_id', $user->id)
            ->where('marketplace', 'trendyol')
            ->firstOrFail();
        $knowledgeArticle = WaKnowledgeArticle::where('store_id', $trendyolStore->id)
            ->where('slug', 'zolm-demo-kargo-ve-iade-rehberi')
            ->firstOrFail();
        $baseline = SupportArtifactVersion::where('store_id', $trendyolStore->id)
            ->where('artifact_type', 'knowledge_article')
            ->where('artifact_id', $knowledgeArticle->id)
            ->where('is_current', true)
            ->firstOrFail();

        $baseline->update(['is_current' => false]);
        $versionTwo = SupportArtifactVersion::create([
            'store_id' => $trendyolStore->id,
            'artifact_type' => 'knowledge_article',
            'artifact_id' => $knowledgeArticle->id,
            'version_number' => 2,
            'content_json' => [
                'title' => 'ZOLM Demo Kargo ve İade Rehberi v2',
                'content' => 'Hardening testi tarafından oluşturulan güncel içerik.',
                'fixture_marker' => 'preserve-v2',
            ],
            'is_current' => true,
            'release_package_id' => $baseline->release_package_id,
        ]);

        $this->seedTenant();

        $this->assertTrue($versionTwo->fresh()->is_current);
        $this->assertSame('preserve-v2', $versionTwo->fresh()->content_json['fixture_marker']);
        $this->assertFalse($baseline->fresh()->is_current);
        $this->assertSame(
            1,
            SupportArtifactVersion::where('store_id', $trendyolStore->id)
                ->where('artifact_type', 'knowledge_article')
                ->where('artifact_id', $knowledgeArticle->id)
                ->where('is_current', true)
                ->count()
        );
    }

    public function test_demo_integration_sync_runs_use_completed_status(): void
    {
        $user = $this->seedTenant();
        $storeIds = MarketplaceStore::where('user_id', $user->id)->pluck('id');
        $syncRuns = IntegrationSyncRun::whereIn('store_id', $storeIds)
            ->where('trigger_type', 'demo')
            ->get();

        $this->assertCount(9, $storeIds);
        $this->assertCount(27, $syncRuns);
        $this->assertSame(['completed'], $syncRuns->pluck('status')->unique()->sort()->values()->all());
        $this->assertSame(27, $syncRuns->whereNotNull('finished_at')->count());
    }

    public function test_reseed_migrates_legacy_tenant_identifiers_without_creating_duplicates(): void
    {
        $user = $this->seedTenant();
        $trendyolStore = MarketplaceStore::where('user_id', $user->id)
            ->where('marketplace', 'trendyol')
            ->firstOrFail();
        $shipment = Shipment::where('user_id', $user->id)->where('source_type', 'demo')->firstOrFail();
        $inbound = WaInboundMessage::whereHas(
            'conversation',
            fn ($query) => $query->whereIn(
                'store_id',
                MarketplaceStore::where('user_id', $user->id)->pluck('id')
            )
        )->firstOrFail();

        $trendyolStore->forceFill(['seller_id' => 'demo-mockdata1-trendyol'])->save();
        $shipment->forceFill(['shipment_no' => 'ZOLM-DEMO-MOCKDATA1-SHIP-0001'])->save();
        $inbound->forceFill(['meta_message_id' => 'ZOLM-DEMO-WA-MSG-1'])->save();

        $this->seedTenant();

        $this->assertSame(9, MarketplaceStore::where('user_id', $user->id)->count());
        $this->assertSame($trendyolStore->id, MarketplaceStore::where('user_id', $user->id)
            ->where('marketplace', 'trendyol')->value('id'));
        $this->assertDatabaseMissing('marketplace_stores', ['seller_id' => 'demo-mockdata1-trendyol']);
        $this->assertSame(1, Shipment::where('user_id', $user->id)->where('source_type', 'demo')->count());
        $this->assertSame($shipment->id, Shipment::where('user_id', $user->id)->where('source_type', 'demo')->value('id'));
        $this->assertDatabaseMissing('shipments', ['shipment_no' => 'ZOLM-DEMO-MOCKDATA1-SHIP-0001']);
        $this->assertSame($inbound->id, WaInboundMessage::whereKey($inbound->id)->value('id'));
        $this->assertDatabaseMissing('wa_inbound_messages', ['meta_message_id' => 'ZOLM-DEMO-WA-MSG-1']);
    }

    private function seedTenant(string $email = 'mockdata1@zolm.test', bool $reset = false): User
    {
        $arguments = [
            '--email' => $email,
            '--password' => 'password',
            '--allow-shared-db' => true,
        ];

        if ($reset) {
            $arguments['--reset'] = true;
        }

        $exitCode = Artisan::call('zolm:demo:seed', $arguments);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, $output);

        return User::where('email', $email)->firstOrFail();
    }

    /**
     * @return array{0: SupportMessage, 1: SupportDispatch, 2: SupportDispatchAttempt}
     */
    private function createPendingDispatch(
        SupportChannel $channel,
        SupportConversation $conversation,
        string $suffix,
    ): array {
        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'external_message_id' => 'zolm-hardening-'.$suffix.'-outbound',
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => 'Hardening testi için bekleyen demo yanıtı.',
            'body_preview' => 'Hardening testi için bekleyen demo yanıtı.',
            'payload_json' => ['fixture' => 'hardening-'.$suffix],
            'sent_at' => now(),
            'delivery_status' => 'queued',
        ]);

        $dispatch = SupportDispatch::create([
            'support_channel_id' => $channel->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'idempotency_key' => 'zolm-hardening-'.$suffix.'-dispatch',
            'status' => 'pending',
            'attempt_count' => 1,
            'payload_json' => ['fixture' => 'hardening-'.$suffix],
        ]);

        $attempt = SupportDispatchAttempt::create([
            'support_dispatch_id' => $dispatch->id,
            'attempted_at' => now(),
            'status' => 'failed',
            'error_message' => 'Sentetik hardening hatası.',
            'latency_ms' => 15,
        ]);

        return [$message, $dispatch, $attempt];
    }
}
