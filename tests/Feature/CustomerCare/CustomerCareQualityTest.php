<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\MarketplaceStore;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportAiRun;
use App\Models\SupportQualityReview;
use App\Models\SupportQualityReviewItem;
use App\Models\SupportKnowledgeSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

class CustomerCareQualityTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $operatorUser;
    protected MarketplaceStore $store;
    protected SupportConversation $conversation;
    protected SupportMessage $message;

    private function reviewedScores(): array
    {
        return [
            'accuracy' => 90,
            'brand_voice' => 85,
            'channel_policy' => 95,
            'pii_safety' => 100,
            'clarity' => 80,
            'sales_alignment' => 70,
            'promise_risk' => 90,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'customer-care.enabled' => true,
            'customer-care.quality_center_enabled' => true,
            'customer-care.ops_center_enabled' => true,
            'customer-care.integration_hub_enabled' => true,
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'system@zolm.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->operatorUser = User::create([
            'name' => 'Operator User',
            'email' => 'operator@zolm.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);

        $le = \App\Models\LegalEntity::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Test Corp',
            'tax_office' => 'TaxOffice',
            'tax_number' => '1234567890',
        ]);

        $this->store = MarketplaceStore::create([
            'user_id' => $this->adminUser->id,
            'legal_entity_id' => $le->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Zolm Store A',
            'store_code' => 'ST_A',
            'seller_id' => '1001',
            'status' => 'active',
            'is_active' => true,
        ]);

        $channel = \App\Models\SupportChannel::create([
            'store_id' => $this->store->id,
            'key' => 'whatsapp_main',
            'channel_type' => 'whatsapp',
            'name' => 'WhatsApp Main',
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $this->conversation = SupportConversation::create([
            'store_id' => $this->store->id,
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv-99',
            'external_customer_id' => 'cust-99',
            'source_type' => 'whatsapp',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $this->message = SupportMessage::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => 'Merhaba, size nasıl yardımcı olabilirim?',
            'body_preview' => 'Merhaba...',
            'delivery_status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function test_admin_can_submit_quality_review_with_scores()
    {
        $this->actingAs($this->adminUser);

        $scores = [
            'accuracy' => 90,
            'brand_voice' => 85,
            'channel_policy' => 95,
            'pii_safety' => 100,
            'clarity' => 80,
            'sales_alignment' => 70,
            'promise_risk' => 90,
        ];

        $overallExpected = (int) (array_sum($scores) / count($scores));

        // Submit via Livewire simulation or direct Service call
        $review = SupportQualityReview::create([
            'store_id' => $this->store->id,
            'conversation_id' => $this->conversation->id,
            'message_id' => $this->message->id,
            'reviewer_id' => $this->adminUser->id,
            'overall_score' => $overallExpected,
            'feedback' => 'Genel yanıt kalitesi oldukça başarılı.',
            'decision' => 'approved',
        ]);

        foreach ($scores as $category => $score) {
            $review->items()->create([
                'category' => $category,
                'score' => $score,
                'comment' => 'Kategori yorumu',
            ]);
        }

        $this->assertDatabaseHas('support_quality_reviews', [
            'store_id' => $this->store->id,
            'overall_score' => $overallExpected,
            'decision' => 'approved',
        ]);

        $this->assertDatabaseHas('support_quality_review_items', [
            'support_quality_review_id' => $review->id,
            'category' => 'accuracy',
            'score' => 90,
        ]);
    }

    public function test_non_admin_cannot_access_quality_review()
    {
        $this->actingAs($this->operatorUser);

        $response = $this->get('/customer-care/quality');
        $response->assertStatus(403);
    }

    public function test_review_cannot_be_saved_with_unmeasured_default_scores(): void
    {
        $this->actingAs($this->adminUser);

        $component = \Livewire\Livewire::test(\App\Livewire\CustomerCare\QualityCenter::class)
            ->set('selectedStoreId', $this->store->id)
            ->set('filterType', 'agent_reply')
            ->call('selectItem', $this->message->id, 'agent_reply')
            ->call('submitReview');

        $component->assertSet(
            'errorMessage',
            'Tüm kalite kriterleri insan incelemesiyle 0-100 arasında puanlanmalıdır.'
        );
        $this->assertDatabaseCount('support_quality_reviews', 0);
    }

    public function test_pii_masked_in_feedback_and_comments()
    {
        $this->actingAs($this->adminUser);

        // Setup Livewire component
        $component = \Livewire\Livewire::test(\App\Livewire\CustomerCare\QualityCenter::class);
        $component->set('selectedStoreId', $this->store->id);
        $component->set('filterType', 'agent_reply');
        $component->set('selectedConversationId', $this->conversation->id);
        $component->set('selectedMessageId', $this->message->id);
        $component->set('selectedItemId', $this->message->id);

        $component->set('feedback', 'Müşteri Caner Ramazan Önal, tel: 05554443322 için yanıt.');
        $component->set('scores', [
            'accuracy' => 90,
            'brand_voice' => 85,
            'channel_policy' => 95,
            'pii_safety' => 100,
            'clarity' => 80,
            'sales_alignment' => 70,
            'promise_risk' => 90,
        ]);
        $component->set('comments.accuracy', 'Caner Bey detayları sordu.');

        $component->call('submitReview');

        // Caner Ramazan Önal should be replaced with [İSİM-GİZLENDİ]
        // 05554443322 should be replaced with [TELEFON-GİZLENDİ]
        $review = SupportQualityReview::first();
        $this->assertNotNull($review);
        $this->assertStringNotContainsString('Caner Ramazan', $review->feedback);
        $this->assertStringNotContainsString('05554443322', $review->feedback);

        $accuracyItem = SupportQualityReviewItem::where('support_quality_review_id', $review->id)
            ->where('category', 'accuracy')
            ->first();
        $this->assertNotNull($accuracyItem);
        $this->assertStringNotContainsString('Caner', $accuracyItem->comment);
    }

    public function test_golden_candidate_review_does_not_change_live_dataset()
    {
        $this->actingAs($this->adminUser);

        $review = SupportQualityReview::create([
            'store_id' => $this->store->id,
            'conversation_id' => $this->conversation->id,
            'message_id' => $this->message->id,
            'reviewer_id' => $this->adminUser->id,
            'overall_score' => 95,
            'decision' => 'golden_candidate',
            'feedback' => 'Güzel bir golden dataset örneği.',
        ]);

        $evalService = app(\App\Services\Support\AI\CustomerCareEvalService::class);
        $dataset = $evalService->getGoldenDataset();

        // Count should not change automatically
        $this->assertCount(24, $dataset);
    }

    public function test_sample_command_in_dry_run_does_not_persist()
    {
        $this->actingAs($this->adminUser);

        // Run dry-run sample command
        $code = Artisan::call('customer-care:sample-quality-reviews', [
            '--store' => $this->store->id,
            '--limit' => 10,
        ]);

        $this->assertEquals(0, $code);
        $this->assertEquals(0, SupportQualityReview::count());
    }

    public function test_quality_route_blocks_when_flag_off()
    {
        $this->actingAs($this->adminUser);
        // 1. Flag off -> 404
        config(['customer-care.quality_center_enabled' => false]);
        $response = $this->get('/customer-care/quality');
        $response->assertStatus(404);

        // 2. Flag on, not admin -> 403
        config(['customer-care.quality_center_enabled' => true]);
        $this->actingAs($this->operatorUser);
        $response = $this->get('/customer-care/quality');
        $response->assertStatus(403);

        // 3. Flag on, admin -> 200
        $this->actingAs($this->adminUser);
        $response = $this->get('/customer-care/quality');
        $response->assertStatus(200);
    }

    public function test_quality_center_prevents_selecting_cross_store_items()
    {
        $this->actingAs($this->adminUser);

        // Create Store B
        $le2 = \App\Models\LegalEntity::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Store B Corp',
            'tax_number' => '9876543210',
        ]);
        $storeB = MarketplaceStore::create([
            'user_id' => $this->adminUser->id,
            'legal_entity_id' => $le2->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Store B',
            'store_code' => 'ST_B',
            'seller_id' => '1002',
            'status' => 'active',
            'is_active' => true,
        ]);

        // Create AI run for Store B
        $runB = SupportAiRun::create([
            'store_id' => $storeB->id,
            'conversation_id' => $this->conversation->id,
            'message_id' => $this->message->id,
            'prompt_raw' => 'Store B prompt',
            'response_raw' => 'Store B response',
            'status' => 'success',
        ]);

        $component = \Livewire\Livewire::test(\App\Livewire\CustomerCare\QualityCenter::class);
        $component->set('selectedStoreId', $this->store->id); // Store A selected

        // Select Store B's run
        $component->call('selectItem', $runB->id, 'ai_run');

        // Should reset selections and display error
        $component->assertSet('selectedItemId', null);
        $component->assertSet('errorMessage', 'Seçilen yapay zeka çalıştırması bu mağazaya ait değil veya bulunamadı.');
    }

    public function test_quality_center_prevents_submitting_cross_store_reviews()
    {
        $this->actingAs($this->adminUser);

        // Create Store B and its message
        $le2 = \App\Models\LegalEntity::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Store B Corp',
            'tax_number' => '9876543210',
        ]);
        $storeB = MarketplaceStore::create([
            'user_id' => $this->adminUser->id,
            'legal_entity_id' => $le2->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Store B',
            'store_code' => 'ST_B',
            'seller_id' => '1002',
            'status' => 'active',
            'is_active' => true,
        ]);
        $channelB = \App\Models\SupportChannel::create([
            'store_id' => $storeB->id,
            'key' => 'whatsapp_b',
            'channel_type' => 'whatsapp',
            'name' => 'WA B',
            'is_enabled' => true,
            'config_json' => [],
        ]);
        $convB = SupportConversation::create([
            'store_id' => $storeB->id,
            'support_channel_id' => $channelB->id,
            'external_conversation_id' => 'conv-b',
            'external_customer_id' => 'cust-b',
            'source_type' => 'whatsapp',
            'status' => 'open',
        ]);
        $msgB = SupportMessage::create([
            'conversation_id' => $convB->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => 'Store B message text',
            'delivery_status' => 'sent',
        ]);

        $component = \Livewire\Livewire::test(\App\Livewire\CustomerCare\QualityCenter::class);
        $component->set('selectedStoreId', $this->store->id); // Store A selected
        $component->set('filterType', 'agent_reply');

        // Manually force cross-store message ID without selectItem guard
        $component->set('selectedItemId', $msgB->id);
        $component->set('selectedMessageId', $msgB->id);
        $component->set('selectedConversationId', $convB->id);

        $component->call('submitReview');

        $component->assertSet('errorMessage', 'Seçilen kayıt bu mağazaya ait değil. İşlem engellendi.');
        $this->assertEquals(0, SupportQualityReview::count());
    }

    public function test_quality_center_kb_candidate_redacts_pii_in_proposed_answer()
    {
        $this->actingAs($this->adminUser);

        // Message with PII
        $piiMessage = SupportMessage::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => 'Müşteri Caner Ramazan Önal iade istiyor, telefonu: 05554443322.',
            'delivery_status' => 'sent',
        ]);

        $component = \Livewire\Livewire::test(\App\Livewire\CustomerCare\QualityCenter::class);
        $component->set('selectedStoreId', $this->store->id);
        $component->set('filterType', 'agent_reply');

        $component->call('selectItem', $piiMessage->id, 'agent_reply');
        $component->set('decision', 'kb_candidate');
        $component->set('scores', $this->reviewedScores());

        $component->call('submitReview');

        $component->assertSet('successMessage', 'Kalite denetim incelemesi başarıyla kaydedildi.');

        $suggestion = SupportKnowledgeSuggestion::first();
        $this->assertNotNull($suggestion);

        // Assertions for redaction in proposed answer
        $this->assertStringNotContainsString('Caner Ramazan', $suggestion->proposed_answer);
        $this->assertStringNotContainsString('05554443322', $suggestion->proposed_answer);
        $this->assertStringContainsString('***', $suggestion->proposed_answer);
    }

    public function test_sample_command_with_execute_creates_pending_reviews()
    {
        $this->actingAs($this->adminUser);

        // Create an AI run to be sampled
        SupportAiRun::create([
            'store_id' => $this->store->id,
            'conversation_id' => $this->conversation->id,
            'message_id' => $this->message->id,
            'prompt_raw' => 'Sorgu',
            'response_raw' => 'Cevap',
            'status' => 'success',
        ]);

        // Run sample command with --execute
        $code = Artisan::call('customer-care:sample-quality-reviews', [
            '--store' => $this->store->id,
            '--limit' => 10,
            '--execute' => true,
        ]);

        $this->assertEquals(0, $code);

        // Assertions for pending review creation
        $review = SupportQualityReview::first();
        $this->assertNotNull($review);
        $this->assertEquals('pending_review', $review->decision);
        $this->assertEquals(0, $review->overall_score);
    }

    public function test_submit_review_prevents_client_side_property_manipulation_for_ai_run()
    {
        $this->actingAs($this->adminUser);

        // Store B
        $le2 = \App\Models\LegalEntity::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Store B Corp',
            'tax_number' => '9876543210',
        ]);
        $storeB = MarketplaceStore::create([
            'user_id' => $this->adminUser->id,
            'legal_entity_id' => $le2->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Store B',
            'store_code' => 'ST_B',
            'seller_id' => '1002',
            'status' => 'active',
            'is_active' => true,
        ]);
        $convB = SupportConversation::create([
            'store_id' => $storeB->id,
            'support_channel_id' => $this->conversation->support_channel_id,
            'external_conversation_id' => 'conv_b_1',
            'external_customer_id' => 'cust_b_1',
            'status' => 'active',
            'source_type' => 'chat',
        ]);
        $msgB = SupportMessage::create([
            'conversation_id' => $convB->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => 'Store B private text',
            'sent_at' => now(),
        ]);

        // Store A valid AI run
        $runA = SupportAiRun::create([
            'store_id' => $this->store->id,
            'conversation_id' => $this->conversation->id,
            'message_id' => $this->message->id,
            'prompt_raw' => 'Store A valid prompt',
            'response_raw' => 'Store A valid response',
            'status' => 'success',
        ]);

        $component = \Livewire\Livewire::test(\App\Livewire\CustomerCare\QualityCenter::class);
        $component->set('selectedStoreId', $this->store->id);
        $component->set('filterType', 'ai_run');

        // Target item is Store A valid AI run, but client manipulates properties to point to Store B message and conversation
        $component->set('selectedItemId', $runA->id);
        $component->set('selectedConversationId', $convB->id);
        $component->set('selectedMessageId', $msgB->id);
        $component->set('feedback', 'Great job');
        $component->set('decision', 'approved');
        $component->set('scores', $this->reviewedScores());

        $component->call('submitReview');

        // Review is created in database
        $review = SupportQualityReview::where('store_id', $this->store->id)->first();
        $this->assertNotNull($review);

        // Assert: Resolved conversation and message ID are Run A's (Store A) canonical values, not manipulated ones!
        $this->assertEquals($runA->conversation_id, $review->conversation_id);
        $this->assertEquals($runA->message_id, $review->message_id);
        $this->assertNotEquals($convB->id, $review->conversation_id);
        $this->assertNotEquals($msgB->id, $review->message_id);
    }

    public function test_submit_review_prevents_client_side_property_manipulation_for_agent_reply()
    {
        $this->actingAs($this->adminUser);

        // Store B
        $le2 = \App\Models\LegalEntity::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Store B Corp',
            'tax_number' => '9876543210',
        ]);
        $storeB = MarketplaceStore::create([
            'user_id' => $this->adminUser->id,
            'legal_entity_id' => $le2->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Store B',
            'store_code' => 'ST_B',
            'seller_id' => '1002',
            'status' => 'active',
            'is_active' => true,
        ]);
        $convB = SupportConversation::create([
            'store_id' => $storeB->id,
            'support_channel_id' => $this->conversation->support_channel_id,
            'external_conversation_id' => 'conv_b_2',
            'external_customer_id' => 'cust_b_2',
            'status' => 'active',
            'source_type' => 'chat',
        ]);
        $msgB = SupportMessage::create([
            'conversation_id' => $convB->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => 'Store B secret message',
            'sent_at' => now(),
        ]);

        // Store A valid message
        $msgA = SupportMessage::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => 'Store A valid message text',
            'sent_at' => now(),
        ]);

        $component = \Livewire\Livewire::test(\App\Livewire\CustomerCare\QualityCenter::class);
        $component->set('selectedStoreId', $this->store->id);
        $component->set('filterType', 'agent_reply');

        $component->set('selectedItemId', $msgA->id);
        $component->set('selectedConversationId', $convB->id);
        $component->set('selectedMessageId', $msgB->id);
        $component->set('decision', 'kb_candidate');
        $component->set('scores', $this->reviewedScores());

        $component->call('submitReview');

        // Review is created in database
        $review = SupportQualityReview::where('store_id', $this->store->id)->first();
        $this->assertNotNull($review);

        // Assert: Resolved values are Message A's (Store A) canonical values, not manipulated ones
        $this->assertEquals($msgA->conversation_id, $review->conversation_id);
        $this->assertEquals($msgA->id, $review->message_id);

        // Check KB candidate proposed answer is Message A's body, NOT manipulated Message B's body
        $suggestion = SupportKnowledgeSuggestion::where('store_id', $this->store->id)->first();
        $this->assertNotNull($suggestion);
        $this->assertEquals('Store A valid message text', $suggestion->proposed_answer);
        $this->assertStringNotContainsString('Store B secret message', $suggestion->proposed_answer);
    }

    public function test_sample_command_masks_pii_in_output()
    {
        $this->actingAs($this->adminUser);

        // Create message with PII
        $msg = SupportMessage::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => 'Müşteri Caner Ramazan Önal, Tel: 05554443322, E-posta: caner@zolm.com',
            'sent_at' => now(),
        ]);

        // Run command and capture output buffer
        Artisan::call('customer-care:sample-quality-reviews', [
            '--store' => $this->store->id,
            '--limit' => 10,
        ]);

        $output = Artisan::output();

        $this->assertStringContainsString('=== Örnekleme Başlatılıyor', $output);
        $this->assertStringNotContainsString('Caner Ramazan Önal', $output);
        $this->assertStringNotContainsString('05554443322', $output);
        $this->assertStringNotContainsString('caner@zolm.com', $output);
    }

    public function test_sample_command_fails_closed_without_system_actor()
    {
        // Deactivate system actor (deleting the user from database or setting incorrect email configuration)
        User::where('email', 'system@zolm.com')->delete();

        // Run sample command with --execute
        $code = Artisan::call('customer-care:sample-quality-reviews', [
            '--store' => $this->store->id,
            '--limit' => 10,
            '--execute' => true,
        ]);

        // Fails with exit code 1
        $this->assertEquals(1, $code);

        // No quality reviews created
        $this->assertEquals(0, SupportQualityReview::count());
    }
}
