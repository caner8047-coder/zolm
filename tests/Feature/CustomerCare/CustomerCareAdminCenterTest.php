<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\MarketplaceStore;
use App\Models\LegalEntity;
use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportAgentAction;
use App\Models\SupportDispatch;
use App\Models\SupportKnowledgeSuggestion;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class CustomerCareAdminCenterTest extends TestCase
{
    use RefreshDatabase, CustomerCareTestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSystemActor();
        Config::set('customer-care.enabled', true);
        Config::set('customer-care.admin_center_enabled', true);
    }

    protected function createStoreWithChannel(User $user, string $key = 'whatsapp'): array
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
            'key' => $key,
            'name' => 'Support Channel',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        return [$store, $channel];
    }

    // ──────────────────────────────────────────────
    // 1. Role / Feature Flag Guards
    // ──────────────────────────────────────────────

    public function test_non_admin_user_cannot_access_admin_center_page()
    {
        $user = User::factory()->create(['role' => 'operator']); // non-admin
        $this->actingAs($user);

        $response = $this->get(route('customer-care.admin'));
        $response->assertStatus(403);
    }

    public function test_admin_center_blocks_when_flag_disabled()
    {
        Config::set('customer-care.admin_center_enabled', false);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $response = $this->get(route('customer-care.admin'));
        $response->assertStatus(404);
    }

    // ──────────────────────────────────────────────
    // 2. Metrics Aggregations
    // ──────────────────────────────────────────────

    public function test_admin_center_correctly_aggregates_store_summaries()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        [$store, $channel] = $this->createStoreWithChannel($admin);

        // Seed 1 pending suggestion, 1 pending dispatch
        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'store_id' => $store->id,
            'external_conversation_id' => 'conv_1',
            'status' => 'open',
            'source_type' => 'whatsapp',
        ]);

        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'ai',
            'message_type' => 'text',
            'body_encrypted' => 'Fatura talebiniz...',
            'delivery_status' => 'draft',
        ]);

        SupportDispatch::create([
            'conversation_id' => $conversation->id,
            'support_channel_id' => $channel->id,
            'message_id' => $message->id,
            'status' => 'pending',
            'idempotency_key' => 'id_1',
        ]);

        SupportKnowledgeSuggestion::create([
            'store_id' => $store->id,
            'source_conversation_id' => $conversation->id,
            'source_message_id' => $message->id,
            'category' => 'Finans',
            'title' => 'Fatura Talebi',
            'proposed_answer' => 'Faturanız e-posta ile gönderilir.',
            'confidence' => 85,
            'status' => 'pending',
            'hash_key' => 'hash_key_1',
        ]);

        Livewire::test(\App\Livewire\CustomerCare\AdminCenter::class)
            ->assertViewHas('storesSummary', function ($summary) use ($store) {
                $storeSummary = collect($summary)->firstWhere('id', $store->id);
                return $storeSummary['pending_dispatches'] === 1 && $storeSummary['suggestion_backlog'] === 1;
            });
    }

    // ──────────────────────────────────────────────
    // 3. Audit CSV Export - BOM & PII Redaction
    // ──────────────────────────────────────────────

    public function test_audit_csv_export_redacts_pii_and_uses_bom()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        [$store, $channel] = $this->createStoreWithChannel($admin);

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'store_id' => $store->id,
            'external_conversation_id' => 'conv_1',
            'status' => 'open',
            'source_type' => 'whatsapp',
        ]);

        // Seed audit log with PII phone and email
        SupportAgentAction::create([
            'conversation_id' => $conversation->id,
            'user_id' => $admin->id,
            'action' => 'policy_block',
            'details_json' => [
                'reason' => 'Yasaklı telefon 05321112233 paylaşılamaz. email: test@zolm.com',
            ],
        ]);

        $response = Livewire::test(\App\Livewire\CustomerCare\AdminCenter::class)
            ->call('exportAuditCsv', $store->id);

        $response->assertStatus(200);

        $csvContent = base64_decode($response->effects['download']['content']);

        // 1. Verify UTF-8 BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csvContent);

        // 2. Verify PII Redacted
        $this->assertStringNotContainsString('05321112233', $csvContent);
        $this->assertStringNotContainsString('test@zolm.com', $csvContent);
    }

    public function test_audit_csv_export_isolates_global_actions_between_stores()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        [$storeA, $chanA] = $this->createStoreWithChannel($admin);
        [$storeB, $chanB] = $this->createStoreWithChannel($admin);

        // Global action log for Store A
        SupportAgentAction::create([
            'conversation_id' => null,
            'user_id' => $admin->id,
            'action' => 'brand_voice_updated',
            'details_json' => [
                'channel_id' => $chanA->id,
                'tone' => 'Store A Tone',
            ],
        ]);

        // Global action log for Store B
        SupportAgentAction::create([
            'conversation_id' => null,
            'user_id' => $admin->id,
            'action' => 'brand_voice_updated',
            'details_json' => [
                'channel_id' => $chanB->id,
                'tone' => 'Store B Tone Secret',
            ],
        ]);

        // Export for Store A
        $response = Livewire::test(\App\Livewire\CustomerCare\AdminCenter::class)
            ->call('exportAuditCsv', $storeA->id);

        $response->assertStatus(200);
        $csvContent = base64_decode($response->effects['download']['content']);

        $this->assertStringContainsString('Store A Tone', $csvContent);
        // Store B global action MUST NOT be present in Store A export!
        $this->assertStringNotContainsString('Store B Tone Secret', $csvContent);
    }

    // ──────────────────────────────────────────────
    // 4. Pilot Launch Report Command
    // ──────────────────────────────────────────────

    public function test_pilot_launch_report_command_creates_valid_markdown()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$store, $channel] = $this->createStoreWithChannel($admin);

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'store_id' => $store->id,
            'external_conversation_id' => 'wa_1',
            'status' => 'open',
            'source_type' => 'whatsapp',
        ]);

        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'ai',
            'message_type' => 'text',
            'body_encrypted' => 'Fatura',
            'delivery_status' => 'failed',
        ]);

        // Failed dispatch with PII, XML chars, and pipe in error
        SupportDispatch::create([
            'conversation_id' => $conversation->id,
            'support_channel_id' => $channel->id,
            'message_id' => $message->id,
            'status' => 'failed',
            'idempotency_key' => 'idemp_pii_xml',
            'last_error' => "Hata | Telefon 05321112233 sızdı. \x03 XML. \n Yeni satır.",
        ]);

        // Seed golden eval run
        $this->seedPassEval($store->id);

        $reportDirectory = storage_path('framework/testing/customer-care-reports-' . uniqid());
        Config::set('customer-care.report_directory', $reportDirectory);

        try {
            $code = Artisan::call('customer-care:pilot-launch-report', [
                '--store' => $store->id,
            ]);

            $this->assertEquals(0, $code);

            $filePath = "{$reportDirectory}/pilot-launch-report-store-{$store->id}.md";
            $this->assertTrue(File::exists($filePath));

            $content = File::get($filePath);
            $this->assertStringContainsString('Pilot Lansman Raporu', $content);
            $this->assertStringContainsString('Hazırlık Durumu', $content);
            $this->assertStringContainsString('Route & Command Inventory', $content);
            $this->assertStringContainsString('Golden Evaluation Summary', $content);

            // Verify PII is redacted
            $this->assertStringNotContainsString('05321112233', $content);

            // Verify XML control character \x03 is removed
            $this->assertStringNotContainsString("\x03", $content);

            // Verify pipe | is replaced/normalized to protect markdown structure
            $this->assertStringNotContainsString('Hata | Telefon', $content);
        } finally {
            File::deleteDirectory($reportDirectory);
        }
    }
}
