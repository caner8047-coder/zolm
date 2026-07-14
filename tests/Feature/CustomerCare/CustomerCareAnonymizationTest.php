<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\MarketplaceStore;
use App\Models\LegalEntity;
use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportDispatch;
use App\Models\SupportAgentAction;
use App\Models\SupportAiRun;
use App\Models\SupportApprovalRequest;
use App\Models\SupportLegalHold;
use App\Models\WaContact;
use App\Services\Support\CustomerCareAnonymizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class CustomerCareAnonymizationTest extends TestCase
{
    use RefreshDatabase, CustomerCareTestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSystemActor();
        Config::set('customer-care.enabled', true);
        Config::set('customer-care.governance_enabled', true);
    }

    private function approveStoreAnonymization(MarketplaceStore $store): void
    {
        $systemActor = \App\Services\Support\TenantContext::getSystemActor();
        SupportApprovalRequest::create([
            'store_id' => $store->id,
            'requester_id' => $systemActor->id,
            'action_type' => 'anonymize_store_' . $store->id,
            'details_json' => ['store_id' => $store->id],
            'status' => 'approved',
            'approved_by' => $store->user_id,
            'approved_at' => now(),
        ]);
    }

    protected function createStoreWithData(User $user): array
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
            'marketplace' => 'trendyol',
            'is_active' => true,
        ]);

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Destek',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'store_id' => $store->id,
            'external_conversation_id' => 'ext_' . uniqid(),
            'source_type' => 'trendyol',
            'status' => 'open',
            'priority' => 'normal',
            'ai_mode' => 'suggestion_only',
            'last_message_at' => now(),
            'version' => 1,
        ]);

        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'text',
            'body_encrypted' => 'Adım Ali Veli, telefonum 05321112233',
            'body_preview' => 'Telefonum 05321112233',
            'delivery_status' => 'received',
        ]);

        SupportAiRun::create([
            'store_id' => $store->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'prompt_raw' => 'Telefonum 05321112233',
            'response_raw' => 'Merhaba Ali Veli',
            'confidence_score' => 50,
            'status' => 'handoff',
        ]);

        $waContact = WaContact::create([
            'store_id' => $store->id,
            'phone_e164_encrypted' => 'test_encrypted_placeholder',
            'phone_hash' => WaContact::hashPhone('+905551234567'),
            'first_name' => 'Ali',
            'last_name' => 'Veli',
            'status' => 'active',
        ]);

        return [$store, $channel, $conversation, $message, $waContact];
    }

    // ──────────────────────────────────────────────
    // 1. Dry-run data değiştirmez
    // ──────────────────────────────────────────────

    public function test_dry_run_does_not_change_data()
    {
        $user = User::factory()->create();
        [$store, $channel, $conversation, $message, $waContact] = $this->createStoreWithData($user);

        $originalBody = $message->body_encrypted;
        $originalName = $waContact->first_name;

        $service = app(CustomerCareAnonymizationService::class);
        $result = $service->anonymizeStore($store->id, true); // dry-run

        $this->assertTrue($result['dry_run']);
        $this->assertArrayHasKey('would_anonymize', $result);

        // Veri değişmemiş olmalı
        $message->refresh();
        $waContact->refresh();
        $this->assertEquals($originalBody, $message->body_encrypted);
        $this->assertEquals($originalName, $waContact->first_name);
    }

    // ──────────────────────────────────────────────
    // 2. Force olmadan gerçek anonymization çalışmaz
    // ──────────────────────────────────────────────

    public function test_artisan_command_dry_run_by_default()
    {
        $user = User::factory()->create();
        [$store, $channel, $conversation, $message, $waContact] = $this->createStoreWithData($user);

        $originalBody = $message->body_encrypted;

        $this->artisan('customer-care:anonymize', ['--store-id' => $store->id])
            ->assertExitCode(0);

        // Veri değişmemiş olmalı
        $message->refresh();
        $this->assertEquals($originalBody, $message->body_encrypted);
    }

    // ──────────────────────────────────────────────
    // 3. Store ID olmadan komut başarısız olur
    // ──────────────────────────────────────────────

    public function test_artisan_command_fails_without_store_id()
    {
        $this->artisan('customer-care:anonymize')
            ->assertExitCode(1);
    }

    // ──────────────────────────────────────────────
    // 4. Force ile PII redakte, audit bütünlüğü korunur
    // ──────────────────────────────────────────────

    public function test_force_anonymization_redacts_pii_but_preserves_ledger_integrity()
    {
        $user = User::factory()->create();
        [$store, $channel, $conversation, $message, $waContact] = $this->createStoreWithData($user);

        // Audit log oluştur
        SupportAgentAction::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'action' => 'agent_reply',
            'details_json' => ['note' => 'test'],
        ]);

        $service = app(CustomerCareAnonymizationService::class);
        $this->approveStoreAnonymization($store);
        $result = $service->anonymizeStore($store->id, false); // force

        $this->assertFalse($result['dry_run']);
        $this->assertGreaterThan(0, $result['messages_redacted']);
        $this->assertEmpty($result['errors'], 'Hata: ' . implode(', ', $result['errors'] ?? []));

        $freshMessage = $message->fresh();
        $this->assertSame('[KVKK-SİLİNDİ]', $freshMessage->body_encrypted);
        $this->assertSame('[KVKK-SİLİNDİ]', $freshMessage->body_preview);
        $this->assertNotSame('[KVKK-SİLİNDİ]', \DB::table('support_messages')->where('id', $message->id)->value('body_encrypted'));

        $freshAiRun = SupportAiRun::where('conversation_id', $conversation->id)->firstOrFail();
        $this->assertSame('[KVKK-SİLİNDİ]', $freshAiRun->prompt_raw);
        $this->assertSame('[KVKK-SİLİNDİ]', $freshAiRun->response_raw);
        $this->assertNotSame('[KVKK-SİLİNDİ]', \DB::table('support_ai_runs')->where('id', $freshAiRun->id)->value('prompt_raw'));

        // WaContact PII — first_name güncellendi
        $this->assertDatabaseHas('wa_contacts', [
            'store_id' => $store->id,
            'first_name' => '[KVKK-SİLİNDİ]',
        ]);

        // phone_hash NOT NULL+UNIQUE — kvkk-deleted placeholder
        $freshContact = WaContact::where('store_id', $store->id)->first();
        $this->assertNotNull($freshContact, 'WaContact store_id ile bulunabilmeli');
        $this->assertStringStartsWith('kvkk-deleted-', $freshContact->phone_hash);

        // Conversation kaydı korunmuş (audit bütünlüğü)
        $this->assertDatabaseHas('support_conversations', [
            'id' => $conversation->id,
            'store_id' => $store->id,
        ]);

        // Agent action kaydı korunmuş (ledger bütünlüğü)
        $this->assertDatabaseHas('support_agent_actions', [
            'conversation_id' => $conversation->id,
            'action' => 'agent_reply',
        ]);
    }

    // ──────────────────────────────────────────────
    // 5. Cross-store anonymization engellenir
    // ──────────────────────────────────────────────

    public function test_cross_store_anonymization_is_blocked()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        [$store1, , $conversation1, $message1] = $this->createStoreWithData($user1);
        [$store2, , $conversation2, $message2] = $this->createStoreWithData($user2);

        $originalBody2 = $message2->body_encrypted;

        $service = app(CustomerCareAnonymizationService::class);
        // store1 için anonymize yap
        $this->approveStoreAnonymization($store1);
        $result = $service->anonymizeStore($store1->id, false);
        $this->assertFalse($result['dry_run']);

        // store2'nin mesajı değişmemiş olmalı
        $message2->refresh();
        $this->assertEquals($originalBody2, $message2->body_encrypted);
    }

    // ──────────────────────────────────────────────
    // 6. Emergency stop: pending AI dispatch'ler iptal edilir
    // ──────────────────────────────────────────────

    public function test_emergency_stop_cancels_pending_ai_dispatches()
    {
        $user = User::factory()->create();
        [$store, $channel, $conversation] = $this->createStoreWithData($user);

        // AI mesajı oluştur
        $aiMessage = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'ai',
            'message_type' => 'text',
            'body_encrypted' => 'AI yanıtı',
            'delivery_status' => 'pending',
        ]);

        // Pending dispatch oluştur
        $dispatch = SupportDispatch::create([
            'support_channel_id' => $channel->id,
            'conversation_id' => $conversation->id,
            'message_id' => $aiMessage->id,
            'idempotency_key' => 'test_dispatch_ai_' . uniqid(),
            'status' => 'pending',
            'attempt_count' => 0,
        ]);

        $service = app(CustomerCareAnonymizationService::class);
        $cancelled = $service->cancelPendingAiDispatches($store->id);

        $this->assertEquals(1, $cancelled);

        $dispatch->refresh();
        $this->assertEquals('cancelled', $dispatch->status);
        $this->assertStringContainsString('Circuit Breaker', $dispatch->last_error);
    }

    // ──────────────────────────────────────────────
    // 7. Manual reply circuit breaker open iken devam eder
    // ──────────────────────────────────────────────

    public function test_manual_replies_not_cancelled_by_emergency_stop()
    {
        $user = User::factory()->create();
        [$store, $channel, $conversation] = $this->createStoreWithData($user);

        // Manuel (agent) mesajı oluştur
        $agentMessage = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent', // manuel
            'message_type' => 'text',
            'body_encrypted' => 'Manuel temsilci yanıtı',
            'delivery_status' => 'pending',
        ]);

        $agentDispatch = SupportDispatch::create([
            'support_channel_id' => $channel->id,
            'conversation_id' => $conversation->id,
            'message_id' => $agentMessage->id,
            'idempotency_key' => 'test_dispatch_agent_' . uniqid(),
            'status' => 'pending',
            'attempt_count' => 0,
        ]);

        $service = app(CustomerCareAnonymizationService::class);
        $cancelled = $service->cancelPendingAiDispatches($store->id);

        // Manuel dispatch etkilenmemiş olmalı
        $agentDispatch->refresh();
        $this->assertEquals('pending', $agentDispatch->status,
            'Manuel (agent) reply dispatch circuit breaker tarafından iptal edilmemeli');
        $this->assertEquals(0, $cancelled);
    }

    // ──────────────────────────────────────────────
    // 8. Circuit breaker command route/list'te görünür
    // ──────────────────────────────────────────────

    public function test_anonymize_command_appears_in_artisan_list()
    {
        $this->artisan('list', ['--raw' => true])
            ->expectsOutputToContain('customer-care:anonymize');
    }

    // ──────────────────────────────────────────────
    // 9. Conversation bazlı anonymization — cross-store engellenir
    // ──────────────────────────────────────────────

    public function test_conversation_anonymization_cross_store_blocked()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        [$store1, , $conversation1, $message1] = $this->createStoreWithData($user1);
        [$store2] = $this->createStoreWithData($user2);

        $service = app(CustomerCareAnonymizationService::class);

        // store2 ID'si ile store1'in konuşmasını anonymize etmeye çalış
        $this->expectException(\InvalidArgumentException::class);
        $service->anonymizeConversation($conversation1->id, $store2->id, false);
    }

    public function test_force_anonymization_requires_approval_and_preserves_legal_hold_conversation(): void
    {
        $user = User::factory()->create();
        [$store, , $conversation, $message] = $this->createStoreWithData($user);
        $conversation->update(['external_customer_id' => 'cust_legal_hold']);
        SupportLegalHold::create([
            'store_id' => $store->id,
            'customer_id' => 'cust_legal_hold',
            'reason' => 'Devam eden hukuki inceleme',
            'active' => true,
        ]);
        $service = app(CustomerCareAnonymizationService::class);

        try {
            $service->anonymizeStore($store->id, false);
            $this->fail('Onaysız anonimleştirme engellenmeliydi.');
        } catch (\App\Exceptions\ApprovalRequiredException) {
            $this->assertSame('Adım Ali Veli, telefonum 05321112233', $message->fresh()->body_encrypted);
        }

        $request = SupportApprovalRequest::where('store_id', $store->id)
            ->where('action_type', 'anonymize_store_' . $store->id)
            ->where('status', 'pending')
            ->firstOrFail();
        $request->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $service->anonymizeStore($store->id, false);

        $this->assertSame('Adım Ali Veli, telefonum 05321112233', $message->fresh()->body_encrypted);
        $audit = SupportAgentAction::where('action', 'compliance_block')->latest()->firstOrFail();
        $this->assertArrayNotHasKey('blocked_customers', $audit->details_json);
        $this->assertContains(hash('sha256', 'cust_legal_hold'), $audit->details_json['blocked_customer_hashes']);
    }
}
