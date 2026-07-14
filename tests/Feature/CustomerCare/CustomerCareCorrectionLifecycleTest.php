<?php

namespace Tests\Feature\CustomerCare;

use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\SupportAiRun;
use App\Models\SupportAnswerError;
use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportRoleAssignment;
use App\Models\User;
use App\Services\Support\CustomerCareCorrectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCareCorrectionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_wrong_answer_creates_task_regression_and_queues_correction(): void
    {
        config(['customer-care.governance_enabled' => true]);
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $legal = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Correction Legal',
            'tax_number' => '1234567890',
            'is_active' => true,
        ]);
        $store = MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legal->id,
            'store_name' => 'Correction Store',
            'marketplace' => 'trendyol',
            'is_active' => true,
        ]);
        SupportRoleAssignment::create(['user_id' => $user->id, 'store_id' => $store->id, 'role' => 'admin']);
        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol',
            'status' => 'active',
            'is_enabled' => true,
            'config_json' => ['automation_settings' => ['ai_mode' => 'automatic', 'auto_reply' => true]],
        ]);
        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'store_id' => $store->id,
            'external_conversation_id' => 'correction-conv',
            'external_customer_id' => 'customer-1',
            'source_type' => 'trendyol',
            'status' => 'open',
            'ai_mode' => 'automatic',
            'ownership_status' => 'ai',
        ]);
        SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'text',
            'body_encrypted' => 'Kargom nerede?',
        ]);
        $wrongMessage = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'ai',
            'message_type' => 'text',
            'body_encrypted' => 'Siparişiniz yarın kesin teslim edilecek.',
            'delivery_status' => 'sent',
        ]);
        SupportAiRun::create([
            'store_id' => $store->id,
            'conversation_id' => $conversation->id,
            'message_id' => $wrongMessage->id,
            'prompt_raw' => 'Kargom nerede?',
            'response_raw' => $wrongMessage->body_encrypted,
            'confidence_score' => 90,
            'status' => 'sent',
        ]);

        $service = app(CustomerCareCorrectionService::class);
        $error = $service->report(
            $conversation,
            $wrongMessage,
            $user,
            'Yarın kesin teslim',
            'Doğrulanmamış teslimat vaadi',
            'critical'
        );

        $this->assertSame('handoff', $conversation->fresh()->ai_mode);
        $this->assertSame('human', $conversation->fresh()->ownership_status);
        $this->assertDatabaseHas('support_correction_tasks', ['support_answer_error_id' => $error->id, 'status' => 'pending']);
        $this->assertDatabaseHas('support_regression_cases', ['support_answer_error_id' => $error->id, 'status' => 'pending_review']);
        $this->assertFalse((bool) data_get($channel->fresh()->config_json, 'automation_settings.auto_reply'));

        $corrected = $service->correct($error, 'Önceki mesajımızdaki teslimat bilgisi hatalıydı. Güncel kayıt için temsilcimiz kontrol sağlayacak.', $user);

        $this->assertSame('correction_queued', $corrected->status);
        $this->assertNotNull($corrected->correction_message_id);
        $this->assertDatabaseHas('support_dispatches', ['message_id' => $corrected->correction_message_id, 'status' => 'pending']);
        $this->assertDatabaseHas('support_correction_tasks', ['support_answer_error_id' => $error->id, 'status' => 'completed']);
        $this->assertNotEmpty($corrected->regressionCase->expected_answer_encrypted);

        $rawClaim = \DB::table('support_answer_errors')->where('id', $error->id)->value('affected_claim_encrypted');
        $this->assertNotSame('Yarın kesin teslim', $rawClaim);
    }

    public function test_message_from_another_conversation_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $legal = LegalEntity::create(['user_id' => $user->id, 'name' => 'L', 'tax_number' => '1111111111', 'is_active' => true]);
        $store = MarketplaceStore::create(['user_id' => $user->id, 'legal_entity_id' => $legal->id, 'store_name' => 'S', 'marketplace' => 'trendyol', 'is_active' => true]);
        $channel = SupportChannel::create(['store_id' => $store->id, 'key' => 'trendyol', 'name' => 'T', 'status' => 'active', 'is_enabled' => true]);
        $first = SupportConversation::create(['support_channel_id' => $channel->id, 'store_id' => $store->id, 'external_conversation_id' => 'c1', 'source_type' => 'trendyol']);
        $second = SupportConversation::create(['support_channel_id' => $channel->id, 'store_id' => $store->id, 'external_conversation_id' => 'c2', 'source_type' => 'trendyol']);
        $foreignMessage = SupportMessage::create(['conversation_id' => $second->id, 'direction' => 'outbound', 'sender_type' => 'ai', 'message_type' => 'text', 'body_encrypted' => 'wrong']);

        app(CustomerCareCorrectionService::class)->report($first, $foreignMessage, $user, 'claim', 'cause');
    }
}
