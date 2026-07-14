<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\MarketplaceStore;
use App\Models\User;
use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Livewire\Livewire;
use App\Livewire\CustomerCare\Inbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

class CustomerCareInboxTest extends TestCase
{
    use RefreshDatabase, CustomerCareTestHelper;

    protected MarketplaceStore $store;
    protected User $user;
    protected SupportChannel $channel;
    protected SupportConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'operator']);

        $legalEntity = \App\Models\LegalEntity::create([
            'user_id' => $this->user->id,
            'name' => 'My Test Store Legal',
            'tax_number' => '1234567890',
            'is_active' => true,
        ]);

        $this->store = MarketplaceStore::create([
            'user_id' => $this->user->id,
            'legal_entity_id' => $legalEntity->id,
            'store_name' => 'My Test Store',
            'marketplace' => 'trendyol',
            'is_active' => true,
        ]);

        $this->channel = SupportChannel::create([
            'store_id' => $this->store->id,
            'channel_type' => 'trendyol',
            'key' => 'trendyol',
            'name' => 'Trendyol Test Channel',
            'is_enabled' => true,
            'credentials_json' => [],
        ]);

        $this->channel->capabilities()->create([
            'capability' => 'send_messages',
            'status' => 'available',
            'source' => 'adapter',
        ]);

        $this->conversation = SupportConversation::create([
            'support_channel_id' => $this->channel->id,
            'external_conversation_id' => 'CONV123',
            'external_customer_id' => 'CUST123',
            'store_id' => $this->store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
            'priority' => 'medium',
            'ai_mode' => 'manual',
            'ownership_status' => 'ai',
            'version' => 1,
        ]);

        // Add inbound message
        SupportMessage::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'text',
            'body_encrypted' => 'Merhaba, kargo nerede?',
            'body_preview' => 'Merhaba, kargo nerede?',
            'delivery_status' => 'received',
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.inbox_enabled', true);
        Config::set('customer-care.auto_reply_enabled', true);
        Config::set('customer-care.pilot_store_allowlist', [$this->store->id]);
        Config::set('customer-care.auto_reply_max_per_hour', 100);
        Config::set('customer-care.business_hours_auto_reply_enabled', true);

        // Mock Channel Manager
        $mockAdapter = $this->createMock(\App\Services\Support\SupportChannelAdapterInterface::class);
        $mockAdapter->method('canReply')->willReturn(true);
        $mockAdapter->method('sendReply')->willReturn(['success' => true, 'channel_message_id' => 'ch_msg_ok']);
        $mockAdapter->method('getOutboundTargetStatus')->willReturn('sent');

        $mockManager = $this->createMock(\App\Services\Support\SupportChannelManager::class);
        $mockManager->method('resolveForChannel')->willReturn($mockAdapter);
        $mockManager->method('resolve')->willReturn($mockAdapter);
        $this->app->instance(\App\Services\Support\SupportChannelManager::class, $mockManager);
    }

    public function test_it_throws_404_if_feature_flag_is_disabled()
    {
        Config::set('customer-care.inbox_enabled', false);

        $this->actingAs($this->user)
            ->get('/customer-care/inbox')
            ->assertStatus(404);
    }

    public function test_unauthorized_user_cannot_view_conversation()
    {
        $otherUser = User::factory()->create(['role' => 'operator']);

        // This other operator has no store relation to $this->store, so they can't access it
        Livewire::actingAs($otherUser)
            ->test(Inbox::class)
            ->call('selectConversation', $this->conversation->id)
            ->assertSet('selectedConversationId', null)
            ->assertSet('errorMessage', 'Bu konuşmaya erişim yetkiniz bulunmamaktadır.');
    }

    public function test_claim_and_release_actions()
    {
        Livewire::actingAs($this->user)
            ->test(Inbox::class)
            ->set('selectedConversationId', $this->conversation->id)
            ->call('claimConversation')
            ->assertSet('successMessage', 'Konuşma başarıyla sahiplenildi.');

        $this->conversation->refresh();
        $this->assertEquals($this->user->id, $this->conversation->assigned_user_id);
        $this->assertEquals('human', $this->conversation->ownership_status);

        Livewire::actingAs($this->user)
            ->test(Inbox::class)
            ->set('selectedConversationId', $this->conversation->id)
            ->call('releaseConversation')
            ->assertSet('successMessage', 'Konuşma sahipliği AI\'a geri bırakıldı.');

        $this->conversation->refresh();
        $this->assertNull($this->conversation->assigned_user_id);
        $this->assertEquals('ai', $this->conversation->ownership_status);
    }

    public function test_resolve_and_reopen_actions()
    {
        Livewire::actingAs($this->user)
            ->test(Inbox::class)
            ->set('selectedConversationId', $this->conversation->id)
            ->call('resolveConversation')
            ->assertSet('successMessage', 'Konuşma çözüldü olarak işaretlendi.');

        $this->conversation->refresh();
        $this->assertEquals('resolved', $this->conversation->status);

        Livewire::actingAs($this->user)
            ->test(Inbox::class)
            ->set('selectedConversationId', $this->conversation->id)
            ->call('reopenConversation')
            ->assertSet('successMessage', 'Konuşma yeniden açıldı.');

        $this->conversation->refresh();
        $this->assertEquals('open', $this->conversation->status);
    }

    public function test_agent_reply_policy_block()
    {
        // Try sending a reply containing a policy-blocked keyword "kapıda ödeme"
        Livewire::actingAs($this->user)
            ->test(Inbox::class)
            ->set('selectedConversationId', $this->conversation->id)
            ->set('replyText', 'Bizde kapıda ödeme seçeneği maalesef yoktur.')
            ->call('sendReply');

        $this->assertDatabaseHas('support_agent_actions', [
            'conversation_id' => $this->conversation->id,
            'action' => 'policy_block',
        ]);

        $this->assertEquals(0, SupportMessage::where('direction', 'outbound')->count());
    }

    public function test_agent_reply_success()
    {
        // Mock adapter behavior for Trendyol so it returns successful outbound target status
        Livewire::actingAs($this->user)
            ->test(Inbox::class)
            ->set('selectedConversationId', $this->conversation->id)
            ->set('replyText', 'Kargonuz yola çıktı, kargo firması Yurtiçi Kargo.')
            ->call('sendReply')
            ->assertSet('replyText', '');

        $this->assertDatabaseHas('support_messages', [
            'conversation_id' => $this->conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
        ]);
    }

    public function test_copilot_ai_draft_generation()
    {
        Config::set('customer-care.demo_mode', true);

        $this->conversation->update(['ai_mode' => 'copilot']);
        SupportMessage::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'text',
            'body_encrypted' => 'Merhaba',
            'body_preview' => 'Merhaba',
            'delivery_status' => 'received',
        ]);

        Livewire::actingAs($this->user)
            ->test(Inbox::class)
            ->set('selectedConversationId', $this->conversation->id)
            ->call('generateAiDraft')
            ->assertSet('errorMessage', '')
            ->assertSet('successMessage', 'AI Taslağı başarıyla oluşturuldu.');

        $this->assertDatabaseHas('support_messages', [
            'conversation_id' => $this->conversation->id,
            'sender_type' => 'ai',
            'delivery_status' => 'draft',
        ]);
    }

    public function test_switching_to_automatic_mode_requires_passing_eval_gate()
    {
        \App\Models\SupportLanguageQualityGate::create([
            'store_id' => $this->store->id,
            'language' => 'tr',
            'dataset_version' => 'tr-test-v1',
            'sample_size' => 24,
            'average_score' => 95,
            'source_accuracy' => 100,
            'critical_error_count' => 0,
            'passed' => true,
            'evaluated_at' => now(),
        ]);

        // 1. With no eval done, change to automatic must fail
        Livewire::actingAs($this->user)
            ->test(Inbox::class)
            ->set('selectedConversationId', $this->conversation->id)
            ->call('changeAiMode', 'automatic')
            ->assertSet('errorMessage', 'Otomatik mod etkinleştirilemez: Eval Gate Failure: Tamamlanmış golden değerlendirme bulunamadı.');

        // 2. Add a passed evaluation
        Config::set('customer-care.reliability_enabled', true);
        Config::set('customer-care.circuit_breaker_enabled', true);
        $this->seedPassEval($this->store->id);

        Livewire::actingAs($this->user)
            ->test(Inbox::class)
            ->set('selectedConversationId', $this->conversation->id)
            ->call('changeAiMode', 'automatic')
            ->assertSet('successMessage', 'Otomasyon modu başarıyla güncellendi: Automatic');

        $this->conversation->refresh();
        $this->assertEquals('automatic', $this->conversation->ai_mode);
    }

    public function test_unauthorized_user_cannot_manipulate_selected_conversation_id_idor()
    {
        $otherUser = User::factory()->create(['role' => 'operator']);
        $otherLegal = \App\Models\LegalEntity::create([
            'user_id' => $otherUser->id,
            'name' => 'Other Store Legal',
            'tax_number' => '9876543210',
            'is_active' => true,
        ]);
        $otherStore = MarketplaceStore::create([
            'user_id' => $otherUser->id,
            'legal_entity_id' => $otherLegal->id,
            'store_name' => 'Other Store',
            'marketplace' => 'trendyol',
            'is_active' => true,
        ]);

        $otherChannel = SupportChannel::create([
            'store_id' => $otherStore->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Destek 2',
            'is_enabled' => true,
        ]);

        $otherConv = SupportConversation::create([
            'support_channel_id' => $otherChannel->id,
            'store_id' => $otherStore->id,
            'external_conversation_id' => 'other_conv_123',
            'external_customer_id' => 'cust_other_123',
            'status' => 'open',
            'source_type' => 'trendyol',
        ]);

        SupportMessage::create([
            'conversation_id' => $otherConv->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'body_encrypted' => 'Bu gizli veri sadece otherUser gorebilmeli.',
            'body_preview' => 'gizli veri',
            'delivery_status' => 'sent',
        ]);

        // 1. When trying to render unauthorized conversation, it must not return messages
        Livewire::actingAs($this->user) // Acting as main user
            ->test(Inbox::class)
            ->set('selectedConversationId', $otherConv->id) // manipulate public state
            ->assertViewHas('selectedConversation', null)
            ->assertViewHas('messages', function ($messages) {
                return $messages->isEmpty();
            });

        // 2. generateAiDraft on unauthorized conversation must fail and not create drafts
        Livewire::actingAs($this->user)
            ->test(Inbox::class)
            ->set('selectedConversationId', $otherConv->id)
            ->call('generateAiDraft');

        $this->assertEquals(0, SupportMessage::where('conversation_id', $otherConv->id)->where('sender_type', 'ai')->count());

        // 3. sendReply on unauthorized conversation must fail and not write messages
        Livewire::actingAs($this->user)
            ->test(Inbox::class)
            ->set('selectedConversationId', $otherConv->id)
            ->set('replyText', 'Saldırgan cevabı')
            ->call('sendReply');

        $this->assertEquals(0, SupportMessage::where('conversation_id', $otherConv->id)->where('sender_type', 'agent')->count());
    }
}
