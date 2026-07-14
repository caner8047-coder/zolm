<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\MarketplaceStore;
use App\Models\SupportLegalHold;
use App\Models\SupportConsentRecord;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportRoleAssignment;
use App\Models\SupportDataSubjectRequest;
use App\Models\SupportApprovalRequest;
use App\Models\SupportAiRun;
use App\Models\SupportWebLead;
use App\Models\SupportWidgetSession;
use App\Models\WaContact;
use App\Services\Support\Compliance\CustomerCareConsentService;
use App\Services\Support\Compliance\CustomerCareComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class CustomerCareComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $operatorUser;
    protected MarketplaceStore $storeA;
    protected MarketplaceStore $storeB;

    private function createApprovedExportDsr(string $customerId): SupportDataSubjectRequest
    {
        $approval = SupportApprovalRequest::create([
            'store_id' => $this->storeA->id,
            'requester_id' => $this->adminUser->id,
            'action_type' => 'dsr_export',
            'details_json' => ['customer_hash' => hash('sha256', $customerId)],
            'status' => 'consumed',
            'approved_by' => $this->operatorUser->id,
            'approved_at' => now(),
            'consumed_at' => now(),
            'consumed_by' => $this->adminUser->id,
        ]);

        return SupportDataSubjectRequest::create([
            'store_id' => $this->storeA->id,
            'customer_id' => $customerId,
            'request_type' => 'export',
            'approval_request_id' => $approval->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'customer-care.enabled' => true,
            'customer-care.governance_enabled' => true,
            'customer-care.compliance_enabled' => true,
            'customer-care.reliability_enabled' => true,
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@zolm.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->operatorUser = User::create([
            'name' => 'Operator User',
            'email' => 'op@zolm.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);

        $le = \App\Models\LegalEntity::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Test Legal Entity Name',
            'company_name' => 'Test Holding',
            'tax_office' => 'Kadikoy',
            'tax_number' => '1234567890',
            'address' => 'Istanbul',
        ]);

        $this->storeA = MarketplaceStore::create([
            'legal_entity_id' => $le->id,
            'user_id' => $this->adminUser->id,
            'store_name' => 'Store A',
            'marketplace' => 'trendyol',
            'is_active' => true,
        ]);

        $this->storeB = MarketplaceStore::create([
            'legal_entity_id' => $le->id,
            'user_id' => $this->adminUser->id,
            'store_name' => 'Store B',
            'marketplace' => 'hepsiburada',
            'is_active' => true,
        ]);
    }

    public function test_compliance_route_blocks_when_flag_off()
    {
        $this->actingAs($this->adminUser);
        config(['customer-care.compliance_enabled' => false]);

        $response = $this->get('/customer-care/compliance');
        $response->assertStatus(404);
    }

    public function test_legal_hold_blocks_anonymization()
    {
        $this->actingAs($this->adminUser);

        // Assign admin role to this store
        SupportRoleAssignment::create([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->storeA->id,
            'role' => 'admin',
        ]);

        // Place hold
        SupportLegalHold::create([
            'store_id' => $this->storeA->id,
            'customer_id' => 'cust_999',
            'reason' => 'Yasal Inceleme',
            'active' => true,
        ]);

        $service = app(CustomerCareComplianceService::class);
        $this->assertTrue($service->isUnderLegalHold($this->storeA->id, 'cust_999'));

        // Try to anonymize cust_999
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('yasal takip (legal hold) altındadır');

        $service->processDsrRequest($this->storeA->id, 'cust_999', 'anonymize');
    }

    public function test_legal_hold_release_requires_and_consumes_two_person_approval(): void
    {
        SupportRoleAssignment::create([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->storeA->id,
            'role' => 'admin',
        ]);
        SupportRoleAssignment::create([
            'user_id' => $this->operatorUser->id,
            'store_id' => $this->storeA->id,
            'role' => 'supervisor',
        ]);
        $hold = SupportLegalHold::create([
            'store_id' => $this->storeA->id,
            'customer_id' => 'cust_legal_1',
            'reason' => 'Aktif hukuki inceleme',
            'active' => true,
        ]);

        $this->actingAs($this->adminUser);
        \Livewire\Livewire::test(\App\Livewire\CustomerCare\Compliance::class)
            ->set('selectedStoreId', $this->storeA->id)
            ->call('releaseHold', $hold->id)
            ->assertSet('successMessage', 'Bu riskli işlem için onay gerekiyor. Onay talebi oluşturuldu. Onaylandıktan sonra tekrar çalıştırabilirsiniz.');

        $request = \App\Models\SupportApprovalRequest::where('action_type', 'legal_hold_release_' . $hold->id)
            ->where('status', 'pending')
            ->firstOrFail();
        $this->assertTrue($hold->fresh()->active);
        $this->assertSame(hash('sha256', 'cust_legal_1'), $request->details_json['customer_hash']);

        $this->actingAs($this->operatorUser);
        \Livewire\Livewire::test(\App\Livewire\CustomerCare\Governance::class)
            ->set('selectedStoreId', $this->storeA->id)
            ->call('approveRequest', $request->id)
            ->assertSet('successMessage', 'İşlem başarıyla onaylandı.');

        $this->actingAs($this->adminUser);
        \Livewire\Livewire::test(\App\Livewire\CustomerCare\Compliance::class)
            ->set('selectedStoreId', $this->storeA->id)
            ->call('releaseHold', $hold->id)
            ->assertSet('successMessage', 'Yasal koruma engeli kaldırıldı.');

        $this->assertFalse($hold->fresh()->active);
        $this->assertSame('consumed', $request->fresh()->status);
    }

    public function test_marketing_blocked_when_consent_missing_but_operational_allowed()
    {
        $consentService = app(CustomerCareConsentService::class);

        // Missing consent -> marketing should fail-closed (false), operational should pass (true)
        $this->assertFalse($consentService->hasConsent($this->storeA->id, 'cust_abc', 'whatsapp_main', 'marketing'));
        $this->assertTrue($consentService->hasConsent($this->storeA->id, 'cust_abc', 'whatsapp_main', 'operational'));

        // Grant consent
        SupportConsentRecord::create([
            'store_id' => $this->storeA->id,
            'customer_id' => 'cust_abc',
            'channel_key' => 'whatsapp_main',
            'consent_type' => 'marketing',
            'status' => 'granted',
            'recorded_at' => now(),
        ]);

        $this->assertTrue($consentService->hasConsent($this->storeA->id, 'cust_abc', 'whatsapp_main', 'marketing'));
    }

    public function test_dsr_export_is_xml_sanitized_and_utf8_bom()
    {
        $this->actingAs($this->adminUser);

        // Setup role
        SupportRoleAssignment::create([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->storeA->id,
            'role' => 'admin',
        ]);

        $channel = \App\Models\SupportChannel::create([
            'store_id' => $this->storeA->id,
            'key' => 'whatsapp',
            'channel_type' => 'whatsapp',
            'name' => 'WA Main',
            'is_enabled' => true,
        ]);

        $conv = SupportConversation::create([
            'store_id' => $this->storeA->id,
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv_xyz',
            'external_customer_id' => 'cust_123',
            'status' => 'active',
            'source_type' => 'chat',
        ]);

        // Create message with XML control character (\x08)
        SupportMessage::create([
            'conversation_id' => $conv->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'text',
            'body_encrypted' => "Hello \x08 World!",
            'sent_at' => now(),
        ]);

        $service = app(CustomerCareComplianceService::class);
        $dsr = $this->createApprovedExportDsr('cust_123');
        $export = $service->generateAccessExport(
            $this->storeA->id,
            'cust_123',
            $dsr->id,
            $this->adminUser
        );

        // Check UTF-8 BOM prefix (\xEF\xBB\xBF)
        $this->assertStringStartsWith("\xEF\xBB\xBF", $export);

        // Check XML control character is removed
        $this->assertStringNotContainsString("\x08", $export);
        $this->assertStringContainsString("Hello  World!", $export);
        $this->assertSame('completed', $dsr->fresh()->status);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $service->generateAccessExport(
            $this->storeA->id,
            'cust_123',
            $dsr->id,
            $this->adminUser
        );
    }

    public function test_dsr_export_request_requires_risk_approval()
    {
        $this->actingAs($this->adminUser);

        // Assign 'admin' to adminUser
        SupportRoleAssignment::create([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->storeA->id,
            'role' => 'admin',
        ]);

        $component = \Livewire\Livewire::test(\App\Livewire\CustomerCare\Compliance::class);
        $component->set('selectedStoreId', $this->storeA->id);
        $component->set('customerId', 'cust_123');
        $component->set('requestType', 'export');

        // Should throw/create approval request since it's not pre-approved
        $component->call('createDsr');

        $component->assertSee('onay gerekiyor');
        $this->assertDatabaseHas('support_approval_requests', [
            'store_id' => $this->storeA->id,
            'action_type' => 'dsr_export',
            'requester_id' => $this->adminUser->id,
            'status' => 'pending',
        ]);
    }

    public function test_dsr_erasure_removes_customer_data_across_customer_care_surfaces(): void
    {
        Storage::fake('local');
        $this->actingAs($this->adminUser);

        SupportRoleAssignment::create([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->storeA->id,
            'role' => 'admin',
        ]);

        $channel = \App\Models\SupportChannel::create([
            'store_id' => $this->storeA->id,
            'key' => 'web_chat',
            'name' => 'Web Chat',
            'status' => 'active',
            'is_enabled' => true,
        ]);
        $conversation = SupportConversation::create([
            'store_id' => $this->storeA->id,
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv-dsr-erase',
            'external_customer_id' => 'cust_erase_1',
            'status' => 'active',
            'source_type' => 'chat',
        ]);

        $attachmentPath = 'customer-care/attachments/dsr-proof.txt';
        Storage::disk('local')->put($attachmentPath, 'kişisel veri');
        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'file',
            'body_encrypted' => 'Silinecek müşteri mesajı',
            'body_preview' => 'Silinecek müşteri mesajı',
            'payload_json' => ['encrypted_path' => $attachmentPath, 'name' => 'kişisel-belge.txt'],
            'sent_at' => now(),
        ]);
        $aiRun = SupportAiRun::create([
            'store_id' => $this->storeA->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'prompt_raw' => 'Silinecek prompt',
            'response_raw' => 'Silinecek cevap',
            'status' => 'completed',
        ]);
        $session = SupportWidgetSession::create([
            'support_channel_id' => $channel->id,
            'conversation_id' => $conversation->id,
            'session_hash' => hash('sha256', 'dsr-session'),
            'token_hash' => hash('sha256', 'dsr-token'),
            'origin' => 'https://example.test',
            'consent_granted' => true,
            'privacy_notice_version' => 'v1',
            'consented_at' => now(),
            'last_seen_at' => now(),
            'expires_at' => now()->addHour(),
            'status' => 'active',
            'metadata_json' => ['ip' => '192.0.2.10'],
        ]);
        $lead = SupportWebLead::create([
            'store_id' => $this->storeA->id,
            'support_widget_session_id' => $session->id,
            'conversation_id' => $conversation->id,
            'name_encrypted' => 'Ayşe Müşteri',
            'email_encrypted' => 'ayse@example.test',
            'phone_encrypted' => '+905551112233',
            'purpose_encrypted' => 'Sipariş desteği',
            'conversation_summary_encrypted' => 'Kişisel konuşma özeti',
            'consent_basis' => 'explicit_widget',
            'privacy_notice_version' => 'v1',
            'consented_at' => now(),
            'status' => 'new',
        ]);
        $waContact = WaContact::create([
            'store_id' => $this->storeA->id,
            'wc_customer_id' => 'cust_erase_1',
            'phone_e164_encrypted' => '+905551112233',
            'phone_hash' => hash('sha256', '+905551112233'),
            'first_name' => 'Ayşe',
            'last_name' => 'Müşteri',
            'status' => 'active',
        ]);
        $consent = SupportConsentRecord::create([
            'store_id' => $this->storeA->id,
            'customer_id' => 'cust_erase_1',
            'channel_key' => 'whatsapp_main',
            'consent_type' => 'marketing',
            'status' => 'granted',
            'recorded_at' => now(),
        ]);
        $approval = SupportApprovalRequest::create([
            'store_id' => $this->storeA->id,
            'requester_id' => $this->adminUser->id,
            'action_type' => 'dsr_delete',
            'details_json' => ['customer_hash' => hash('sha256', 'cust_erase_1')],
            'status' => 'approved',
            'approved_by' => $this->operatorUser->id,
            'approved_at' => now(),
        ]);

        app(CustomerCareComplianceService::class)->processDsrRequest(
            $this->storeA->id,
            'cust_erase_1',
            'delete',
            [],
            $this->adminUser
        );

        Storage::disk('local')->assertMissing($attachmentPath);
        $this->assertSame('[KVKK-SİLİNDİ]', $message->fresh()->body_encrypted);
        $this->assertNull($message->fresh()->payload_json);
        $this->assertSame('[KVKK-SİLİNDİ]', $aiRun->fresh()->prompt_raw);
        $this->assertStringStartsWith('[KVKK-SİLİNDİ]-', $conversation->fresh()->external_customer_id);
        $this->assertSame('anonymized', $lead->fresh()->status);
        $this->assertSame('[KVKK-SİLİNDİ]', $lead->fresh()->email_encrypted);
        $this->assertSame('anonymized', $session->fresh()->status);
        $this->assertNull($session->fresh()->metadata_json);
        $this->assertSame('anonymized', $waContact->fresh()->status);
        $this->assertNull($waContact->fresh()->wc_customer_id);
        $this->assertStringStartsWith('[KVKK-SİLİNDİ]-', $consent->fresh()->customer_id);
        $this->assertSame('consumed', $approval->fresh()->status);
        $this->assertDatabaseHas('support_data_subject_requests', [
            'store_id' => $this->storeA->id,
            'request_type' => 'delete',
            'approval_request_id' => $approval->id,
            'status' => 'completed',
        ]);
    }

    public function test_dsr_export_filename_is_masked_and_logs_action()
    {
        $this->actingAs($this->adminUser);

        SupportRoleAssignment::create([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->storeA->id,
            'role' => 'admin',
        ]);

        $dsr = $this->createApprovedExportDsr('cust_raw_123');

        $component = \Livewire\Livewire::test(\App\Livewire\CustomerCare\Compliance::class);
        $component->set('selectedStoreId', $this->storeA->id);

        $component->call('exportDsr', $dsr->id);

        // Assert download filename does not contain raw customer_id
        $expectedRef = "dsr-export-dsr_" . $dsr->id . "_" . substr(hash('sha256', 'cust_raw_123'), 0, 8) . ".json";
        $component->assertFileDownloaded($expectedRef);

        // Assert audit log was created
        $this->assertDatabaseHas('support_agent_actions', [
            'user_id' => $this->adminUser->id,
            'action' => 'dsr_export_downloaded',
        ]);
    }

    public function test_lineage_logs_customer_id_as_hashed_and_supports_search()
    {
        $this->actingAs($this->adminUser);

        SupportRoleAssignment::create([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->storeA->id,
            'role' => 'admin',
        ]);

        $service = app(CustomerCareComplianceService::class);
        $service->logLineageEvent($this->storeA->id, 'cust_pii_123', 10, 'message_read', 'message', 10);

        // Ensure database does NOT contain raw customer_id
        $this->assertDatabaseMissing('support_data_lineage_events', [
            'customer_id' => 'cust_pii_123',
        ]);

        // Ensure database contains hashed customer_id
        $hashed = hash('sha256', 'cust_pii_123');
        $this->assertDatabaseHas('support_data_lineage_events', [
            'customer_id' => $hashed,
            'action_type' => 'message_read',
        ]);

        // Test Livewire lineage search
        $component = \Livewire\Livewire::test(\App\Livewire\CustomerCare\Compliance::class);
        $component->set('selectedStoreId', $this->storeA->id);
        $component->set('searchCustomerId', 'cust_pii_123');
        $component->call('searchLineage');

        $component->assertSet('lineageEvents', function ($events) {
            return count($events) === 1 && $events[0]['action_type'] === 'message_read';
        });
    }
}
