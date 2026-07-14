<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\MarketplaceStore;
use App\Models\LegalEntity;
use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\MpOrder;
use App\Models\MpPeriod;
use App\Models\WaAccount;
use App\Models\WaContact;
use App\Models\WaConversation;
use App\Models\Shipment;
use App\Services\Support\CustomerCareIdentityResolver;
use App\Services\Support\CustomerCareCustomerSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CustomerCareCustomerSummaryTest extends TestCase
{
    use RefreshDatabase, CustomerCareTestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSystemActor();
    }

    protected function createStoreAndChannel(User $user, string $marketplace = 'trendyol'): array
    {
        $legal = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Legal ' . uniqid(),
            'tax_number' => (string) rand(1000000000, 9999999999),
            'is_active' => true,
        ]);

        $store = MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legal->id,
            'store_name' => 'Store ' . uniqid(),
            'marketplace' => $marketplace,
            'is_active' => true,
        ]);

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => $marketplace,
            'name' => ucfirst($marketplace) . ' Destek',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        return [$store, $channel];
    }

    protected function createConversation(SupportChannel $channel, MarketplaceStore $store, ?string $externalCustomerId = 'cust_123'): SupportConversation
    {
        return SupportConversation::create([
            'support_channel_id' => $channel->id,
            'store_id' => $store->id,
            'external_conversation_id' => 'ext_' . uniqid(),
            'external_customer_id' => $externalCustomerId,
            'source_type' => 'trendyol',
            'status' => 'open',
            'priority' => 'normal',
            'ai_mode' => 'suggestion_only',
            'last_message_at' => now(),
            'version' => 1,
        ]);
    }

    /**
     * MpPeriod ile birlikte MpOrder oluşturur (period_id NOT NULL FK zorunlu).
     */
    protected function createMpOrder(MarketplaceStore $store, User $user, string $orderNumber, string $status = 'shipped', string $productName = 'Ürün', ?string $externalCustomerId = 'cust_123'): MpOrder
    {
        $period = MpPeriod::firstOrCreate(
            [
                'user_id' => $user->id,
                'year' => 2026,
                'month' => 7,
                'marketplace' => $store->marketplace ?? 'trendyol',
            ],
            ['status' => 'completed']
        );

        return MpOrder::create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'period_id' => $period->id,
            'order_number' => $orderNumber,
            'order_date' => now(),
            'status' => $status,
            'product_name' => $productName,
            'quantity' => 1,
            'raw_data' => $externalCustomerId ? ['customer_external_id' => $externalCustomerId] : [],
        ]);
    }

    // ──────────────────────────────────────────────
    // 1. Cross-store sızmaz
    // ──────────────────────────────────────────────

    public function test_cross_store_customer_summary_does_not_leak()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        [$store1, $channel1] = $this->createStoreAndChannel($user1);
        [$store2, $channel2] = $this->createStoreAndChannel($user2);

        $conv1 = $this->createConversation($channel1, $store1);
        $conv2 = $this->createConversation($channel2, $store2);

        // store1 için sipariş oluştur
        $this->createMpOrder($store1, $user1, 'ORD-001');

        $summaryService = app(CustomerCareCustomerSummaryService::class);

        // store2'nin konuşması için özet al — store1 siparişleri görünmemeli
        $summary2 = $summaryService->getSummary($conv2);

        $orderNumbers = array_column($summary2['recent_orders'] ?? [], 'order_number');
        $this->assertNotContains('ORD-001', $orderNumbers);
    }

    // ──────────────────────────────────────────────
    // 2. WhatsApp contact → marketplace customer otomatik merge edilmez
    // ──────────────────────────────────────────────

    public function test_whatsapp_contact_not_auto_merged_with_marketplace_customer()
    {
        $user = User::factory()->create();
        [$store, $channel] = $this->createStoreAndChannel($user);

        // WaAccount oluştur
        WaAccount::create([
            'store_id' => $store->id,
            'phone_number_id' => '1234',
            'waba_id' => 'waba',
            'display_phone_number' => '+905551234567',
            'access_token_encrypted' => 'test_token',
            'is_active' => true,
        ]);

        $contact = WaContact::create([
            'store_id' => $store->id,
            'phone_e164_encrypted' => 'test_enc',
            'phone_hash' => WaContact::hashPhone('+905551234567'),
            'first_name' => 'Ali',
            'status' => 'active',
        ]);

        $waConv = WaConversation::create([
            'store_id' => $store->id,
            'contact_id' => $contact->id,
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $waChannel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'whatsapp',
            'name' => 'WhatsApp',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $waConversation = SupportConversation::create([
            'support_channel_id' => $waChannel->id,
            'store_id' => $store->id,
            'external_conversation_id' => 'wa_' . $waConv->id,
            'external_customer_id' => null,
            'source_type' => 'whatsapp',
            'status' => 'open',
            'priority' => 'normal',
            'ai_mode' => 'suggestion_only',
            'last_message_at' => now(),
            'source_reference_json' => ['wa_conversation_id' => $waConv->id],
            'version' => 1,
        ]);

        $trendyolConversation = $this->createConversation($channel, $store, 'cust_wa_ali');

        $resolver = app(CustomerCareIdentityResolver::class);
        $waIdentity = $resolver->resolveForConversation($waConversation);
        $trendyolIdentity = $resolver->resolveForConversation($trendyolConversation);

        // Otomatik merge yasak — farklı channel type'lar birleştirilmemeli
        $canAssociate = $resolver->canAssociate($waIdentity, $trendyolIdentity);
        $this->assertFalse($canAssociate, 'WhatsApp contact ile marketplace customer otomatik merge edilmemeli');
    }

    // ──────────────────────────────────────────────
    // 3. Deterministic/manual association olmadan channel history birleşmez
    // ──────────────────────────────────────────────

    public function test_channel_histories_do_not_merge_without_deterministic_key()
    {
        $user = User::factory()->create();
        [$store, $channel] = $this->createStoreAndChannel($user);

        $conv1 = $this->createConversation($channel, $store, 'customer_A');
        $conv2 = $this->createConversation($channel, $store, 'customer_B');

        $resolver = app(CustomerCareIdentityResolver::class);
        $id1 = $resolver->resolveForConversation($conv1);
        $id2 = $resolver->resolveForConversation($conv2);

        $canAssociate = $resolver->canAssociate($id1, $id2);
        $this->assertFalse($canAssociate);
    }

    // ──────────────────────────────────────────────
    // 4. PII masked summary — order_number
    // ──────────────────────────────────────────────

    public function test_customer_summary_masks_pii_in_order_numbers()
    {
        $user = User::factory()->create();
        [$store, $channel] = $this->createStoreAndChannel($user);
        $conv = $this->createConversation($channel, $store);

        // Telefon numarası gibi görünen sipariş numarası
        $this->createMpOrder($store, $user, '05321112233', 'shipped', 'Ürün B');

        $summaryService = app(CustomerCareCustomerSummaryService::class);
        $summary = $summaryService->getSummary($conv);

        $this->assertNotEmpty($summary['recent_orders']);
        // PII Redactor telefon maskeler
        $maskedOrderNum = $summary['recent_orders'][0]['order_number'];
        $this->assertStringNotContainsString('05321112233', $maskedOrderNum);
    }

    // ──────────────────────────────────────────────
    // 5. AI context only includes current store data
    // ──────────────────────────────────────────────

    public function test_ai_context_only_includes_current_store_data()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        [$store1, $channel1] = $this->createStoreAndChannel($user1);
        [$store2, $channel2] = $this->createStoreAndChannel($user2);

        $conv1 = $this->createConversation($channel1, $store1);

        // Store2 için sipariş oluştur — store1 AI context'ine girmemeli
        $this->createMpOrder($store2, $user2, 'CROSS-STORE-ORD', 'shipped', 'Başka Mağaza Ürünü');

        $summaryService = app(CustomerCareCustomerSummaryService::class);
        $aiContext = $summaryService->buildAiContextString($conv1);

        $this->assertStringNotContainsString('CROSS-STORE-ORD', $aiContext);
        $this->assertStringNotContainsString('Başka Mağaza Ürünü', $aiContext);
    }

    public function test_same_store_other_customer_orders_do_not_leak(): void
    {
        $user = User::factory()->create();
        [$store, $channel] = $this->createStoreAndChannel($user);
        $conversation = $this->createConversation($channel, $store, 'customer_A');

        $this->createMpOrder($store, $user, 'ORDER-A', 'shipped', 'A Ürünü', 'customer_A');
        $this->createMpOrder($store, $user, 'ORDER-B', 'shipped', 'B Ürünü', 'customer_B');

        $summary = app(CustomerCareCustomerSummaryService::class)->getSummary($conversation);
        $orderNumbers = array_column($summary['recent_orders'], 'order_number');

        $this->assertContains('ORDER-A', $orderNumbers);
        $this->assertNotContains('ORDER-B', $orderNumbers);
        $this->assertStringNotContainsString('ORDER-B', $summary['ai_safe_context']);
    }

    // ──────────────────────────────────────────────
    // 6. Empty state fabrication yapmaz
    // ──────────────────────────────────────────────

    public function test_empty_state_does_not_fabricate_orders_or_conversations()
    {
        $user = User::factory()->create();
        [$store, $channel] = $this->createStoreAndChannel($user);
        $conv = $this->createConversation($channel, $store);

        // Hiç sipariş yok
        $summaryService = app(CustomerCareCustomerSummaryService::class);
        $summary = $summaryService->getSummary($conv);

        $this->assertEmpty($summary['recent_orders'], 'Sipariş yoksa uydurma yapılmamalı');
        $this->assertEmpty($summary['recent_conversations'], 'Konuşma yoksa uydurma yapılmamalı');
        $this->assertFalse($summary['data_available']);

        $aiContext = $summaryService->buildAiContextString($conv);
        $this->assertEquals('', $aiContext, 'Veri yoksa AI context boş olmalı, uydurma yapılmamalı');
    }

    // ──────────────────────────────────────────────
    // 7. Identity resolver — same store same key assoc allowed
    // ──────────────────────────────────────────────

    public function test_identity_resolver_allows_same_store_deterministic_key()
    {
        $user = User::factory()->create();
        [$store, $channel] = $this->createStoreAndChannel($user);

        $conv1 = $this->createConversation($channel, $store, 'cust_same');
        $conv2 = $this->createConversation($channel, $store, 'cust_same');

        $resolver = app(CustomerCareIdentityResolver::class);
        $id1 = $resolver->resolveForConversation($conv1);
        $id2 = $resolver->resolveForConversation($conv2);

        $this->assertTrue($resolver->canAssociate($id1, $id2));
    }

    // ──────────────────────────────────────────────
    // 8. Cross-store association engellenir
    // ──────────────────────────────────────────────

    public function test_cross_store_association_blocked()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        [$store1, $channel1] = $this->createStoreAndChannel($user1);
        [$store2, $channel2] = $this->createStoreAndChannel($user2);

        $conv1 = $this->createConversation($channel1, $store1, 'cust_shared');
        $conv2 = $this->createConversation($channel2, $store2, 'cust_shared');

        $resolver = app(CustomerCareIdentityResolver::class);
        $id1 = $resolver->resolveForConversation($conv1);
        $id2 = $resolver->resolveForConversation($conv2);

        $this->assertFalse($resolver->canAssociate($id1, $id2));
    }

    public function test_customer_summary_service_rejects_foreign_actor(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create(['role' => 'operator']);
        [$store, $channel] = $this->createStoreAndChannel($owner);
        $conversation = $this->createConversation($channel, $store);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        app(CustomerCareCustomerSummaryService::class)->getSummary($conversation, $outsider);
    }

    public function test_verified_order_context_includes_fresh_carrier_tracking_and_distinguishes_estimate(): void
    {
        $user = User::factory()->create();
        [$store, $channel] = $this->createStoreAndChannel($user);
        $conversation = $this->createConversation($channel, $store, 'customer_shipping');
        $order = $this->createMpOrder($store, $user, 'ORDER-SHIP-1', 'shipped', 'Test Ürün', 'customer_shipping');
        $order->update(['raw_data' => [
            'customer_external_id' => 'customer_shipping',
            'estimatedDeliveryDate' => now()->addDays(2)->toDateString(),
        ]]);
        $shipment = Shipment::create([
            'user_id' => $user->id,
            'legal_entity_id' => $store->legal_entity_id,
            'store_id' => $store->id,
            'shipment_no' => 'SHP-TEST-1',
            'order_number' => 'ORDER-SHIP-1',
            'carrier_code' => 'surat',
            'carrier_name' => 'Sürat Kargo',
            'tracking_number' => 'TRK-998877',
            'status' => 'in_transit',
            'status_label' => 'Transfer merkezinde',
            'last_tracked_at' => now(),
        ]);
        SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'text',
            'body_encrypted' => 'Kargom nerede?',
            'delivery_status' => 'received',
        ]);

        $summary = app(CustomerCareCustomerSummaryService::class)->getSummary($conversation);
        $this->assertSame('Sürat Kargo', $summary['recent_orders'][0]['shipment']['carrier']);
        $this->assertSame('TRK-998877', $summary['recent_orders'][0]['shipment']['tracking_number']);
        $this->assertSame('estimated', $summary['recent_orders'][0]['delivery']['kind']);

        $context = app(\App\Services\Support\AI\CustomerCareContextBuilder::class)->buildContext($conversation);
        $this->assertStringContainsString('Tahmini teslim tarihi (kesin vaat değildir)', $context['orders']);
        $this->assertStringContainsString('TRK-998877', $context['orders']);
        $this->assertTrue(collect($context['citations'])->contains(fn ($source) => $source['type'] === 'shipment' && (int) $source['record_id'] === $shipment->id));
    }
}
