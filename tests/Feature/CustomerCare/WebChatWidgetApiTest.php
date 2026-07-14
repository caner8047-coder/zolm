<?php

namespace Tests\Feature\CustomerCare;

use App\Models\IntegrationConnection;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\SupportChannel;
use App\Models\SupportDispatch;
use App\Models\SupportMessage;
use App\Models\SupportWebLead;
use App\Models\SupportWidgetSession;
use App\Models\SupportAgentAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebChatWidgetApiTest extends TestCase
{
    use RefreshDatabase;

    private function createWidget(): SupportChannel
    {
        config(['customer-care.enabled' => true, 'customer-care.web_chat_enabled' => true]);
        $user = User::factory()->create(['is_active' => true]);
        $legal = LegalEntity::create(['user_id' => $user->id, 'name' => 'Widget Legal', 'tax_number' => '1234567890', 'is_active' => true]);
        $store = MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legal->id,
            'store_name' => 'Widget Store',
            'marketplace' => 'shopify',
            'is_active' => true,
        ]);
        IntegrationConnection::create([
            'store_id' => $store->id,
            'provider' => 'web_chat',
            'status' => 'active',
            'webhook_secret' => 'server-only-secret',
        ]);

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'web_chat',
            'public_key' => 'widget_public_test_key_1234567890',
            'name' => 'Web Chat',
            'status' => 'active',
            'is_enabled' => true,
            'config_json' => [
                'automation_settings' => ['ai_mode' => 'suggestion_only', 'min_confidence' => 85],
                'web_chat' => [
                    'allowed_origins' => ['https://magaza.example'],
                    'consent_required' => true,
                    'privacy_notice_version' => '2026-01',
                    'privacy_notice_text' => 'Veriler destek amacıyla işlenir.',
                    'assistant_name' => 'Mira',
                    'primary_color' => '#1e293b',
                    'popular_prompts' => ['Siparişim nerede?', 'İade koşulları'],
                    'marketing_notice_text' => 'Kampanyalar için ayrı izin veriyorum.',
                ],
            ],
        ]);
        $channel->capabilities()->create(['capability' => 'attachments', 'status' => 'available', 'source' => 'adapter']);
        return $channel;
    }

    public function test_widget_rejects_unknown_origin_and_missing_consent(): void
    {
        $channel = $this->createWidget();
        $url = '/api/customer-care/widget/' . $channel->public_key . '/session';

        $this->withHeader('Origin', 'https://evil.example')->postJson($url, ['consent' => true])->assertForbidden();
        $this->withHeader('Origin', 'https://magaza.example')->postJson($url, ['consent' => false])
            ->assertStatus(422)
            ->assertJsonPath('privacy_notice_version', '2026-01');
    }

    public function test_widget_session_lead_message_poll_and_ack_flow(): void
    {
        $channel = $this->createWidget();
        $origin = 'https://magaza.example';
        $base = '/api/customer-care/widget/' . $channel->public_key;

        $bootstrap = $this->withHeader('Origin', $origin)->postJson($base . '/session', [
            'consent' => true,
            'marketing_consent' => false,
            'lead' => [
                'name' => 'Ayşe Yılmaz',
                'email' => 'ayse@example.com',
                'phone' => '05321234567',
                'purpose' => 'Kurumsal ürün teklifi almak istiyorum.',
                'idempotency_key' => 'lead-idempotency-0001',
                'campaign' => 'yaz-kampanyasi',
            ],
        ])->assertOk()->assertHeader('Access-Control-Allow-Origin');

        $token = $bootstrap->json('token');
        $this->assertNotEmpty($token);
        $session = SupportWidgetSession::firstOrFail();
        $lead = SupportWebLead::firstOrFail();
        $this->assertTrue($session->consent_granted);
        $this->assertNotNull($lead->crm_contact_id);
        $this->assertFalse($lead->marketing_consent_granted);
        $this->assertSame('yaz-kampanyasi', $lead->campaign);
        $this->assertSame('Kurumsal ürün teklifi almak istiyorum.', $lead->purpose_encrypted);
        $this->assertNotSame('ayse@example.com', \DB::table('support_web_leads')->where('id', $lead->id)->value('email_encrypted'));

        $payload = ['body' => 'Merhaba, L beden stokta mı?', 'idempotency_key' => 'message-key-0001', 'website' => ''];
        $first = $this->withHeaders(['Origin' => $origin, 'Authorization' => 'Bearer ' . $token])
            ->postJson($base . '/messages', $payload)
            ->assertStatus(202);
        $messageId = $first->json('message_id');
        $this->assertNotNull($messageId);

        $this->withHeaders(['Origin' => $origin, 'Authorization' => 'Bearer ' . $token])
            ->postJson($base . '/messages', $payload)
            ->assertStatus(202)
            ->assertJsonPath('projected', false);
        $this->assertDatabaseCount('support_messages', 1);

        $session->refresh();
        $outbound = SupportMessage::create([
            'conversation_id' => $session->conversation_id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => 'Evet, L beden stokta görünüyor.',
            'delivery_status' => 'queued',
        ]);
        SupportDispatch::create([
            'support_channel_id' => $channel->id,
            'conversation_id' => $session->conversation_id,
            'message_id' => $outbound->id,
            'idempotency_key' => 'widget-outbound-1',
            'status' => 'queued',
            'attempt_count' => 1,
        ]);

        $poll = $this->withHeaders(['Origin' => $origin, 'Authorization' => 'Bearer ' . $token])
            ->getJson($base . '/messages?after_id=' . $messageId)
            ->assertOk();
        $this->assertSame($outbound->id, $poll->json('messages.0.id'));

        $this->withHeaders(['Origin' => $origin, 'Authorization' => 'Bearer ' . $token])
            ->postJson($base . '/ack', ['message_ids' => [$outbound->id]])
            ->assertOk();
        $this->assertSame('delivered', $outbound->fresh()->delivery_status);
        $this->assertDatabaseHas('support_dispatches', ['message_id' => $outbound->id, 'status' => 'sent']);
    }

    public function test_widget_exposes_safe_theme_accepts_encrypted_attachment_and_handoff(): void
    {
        Storage::fake('local');
        $channel = $this->createWidget();
        $origin = 'https://magaza.example';
        $base = '/api/customer-care/widget/' . $channel->public_key;

        $this->withHeader('Origin', $origin)->getJson($base . '/configuration')
            ->assertOk()->assertJsonPath('widget.name', 'Mira')
            ->assertJsonPath('widget.attachments_enabled', true)
            ->assertJsonPath('widget.popular_prompts.0', 'Siparişim nerede?');

        $bootstrap = $this->withHeader('Origin', $origin)->postJson($base . '/session', [
            'consent' => true,
            'marketing_consent' => true,
            'lead' => ['purpose' => 'Demo talebi', 'idempotency_key' => 'lead-idempotency-0002'],
        ])->assertOk();
        $token = $bootstrap->json('token');
        $headers = ['Origin' => $origin, 'Authorization' => 'Bearer ' . $token];
        $this->withHeaders($headers)->postJson($base . '/messages', [
            'body' => 'Görseli inceler misiniz?', 'idempotency_key' => 'message-key-attachment',
        ])->assertStatus(202);

        $file = UploadedFile::fake()->image('urun.jpg', 320, 240)->size(100);
        $this->withHeaders($headers)->post($base . '/attachments', [
            'file' => $file,
            'idempotency_key' => 'attachment-key-0001',
        ])->assertStatus(202)->assertJsonPath('projected', true);
        $attachment = SupportMessage::where('message_type', 'attachment')->firstOrFail();
        Storage::disk('local')->assertExists($attachment->payload_json['encrypted_path']);

        $this->withHeaders($headers)->postJson($base . '/handoff', [])->assertOk()->assertJsonPath('status', 'pending');
        $conversation = SupportWidgetSession::firstOrFail()->conversation;
        $this->assertSame('human', $conversation->fresh()->ownership_status);
        $this->assertSame('handoff', $conversation->fresh()->ai_mode);
        $this->assertTrue(SupportAgentAction::where('conversation_id', $conversation->id)->where('action', 'human_handoff')->exists());
        $this->assertDatabaseHas('support_consent_records', ['store_id' => $channel->store_id, 'consent_type' => 'marketing', 'status' => 'granted']);
    }
}
