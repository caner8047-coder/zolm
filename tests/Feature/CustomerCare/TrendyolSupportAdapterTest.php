<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\MarketplaceStore;
use App\Models\IntegrationConnection;
use App\Models\SupportChannel;
use App\Models\SupportChannelCapability;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\MarketplaceQuestion;
use App\Models\User;
use App\Models\LegalEntity;
use App\Services\Support\TrendyolSupportChannelAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class TrendyolSupportAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
    }

    protected function createStore($user, $name)
    {
        $legal = LegalEntity::create([
            'user_id' => $user->id,
            'name' => $name . ' Legal',
            'tax_number' => '1234567890',
            'is_active' => true,
        ]);

        return MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legal->id,
            'store_name' => $name,
            'marketplace' => 'trendyol',
            'is_active' => true,
        ]);
    }

    public function test_adapter_capabilities_depend_on_connection_and_channel_status()
    {
        $user = User::factory()->create();
        $store = $this->createStore($user, 'Trendyol Store');

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Destek',
            'is_enabled' => true,
        ]);

        SupportChannelCapability::create([
            'support_channel_id' => $channel->id,
            'capability' => 'send_messages',
            'status' => 'available',
            'source' => 'adapter',
        ]);

        $adapter = new TrendyolSupportChannelAdapter();

        // 1. Not configured & enabled => send_messages status is unavailable
        $caps = $adapter->getCapabilities($channel);
        $sendCap = collect($caps)->firstWhere('capability', 'send_messages');
        $this->assertEquals('unavailable', $sendCap['status']);

        // 2. Configured & enabled => send_messages status is available
        IntegrationConnection::create([
            'store_id' => $store->id,
            'status' => 'configured',
            'provider' => 'trendyol',
        ]);

        $caps = $adapter->getCapabilities($channel);
        $sendCap = collect($caps)->firstWhere('capability', 'send_messages');
        $this->assertEquals('available', $sendCap['status']);

        // 3. Configured & disabled => send_messages status is unavailable
        $channel->update(['is_enabled' => false]);
        $caps = $adapter->getCapabilities($channel);
        $sendCap = collect($caps)->firstWhere('capability', 'send_messages');
        $this->assertEquals('unavailable', $sendCap['status']);
    }

    public function test_can_reply_only_when_configured_and_enabled()
    {
        $user = User::factory()->create();
        $store = $this->createStore($user, 'Trendyol Store');

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Destek',
            'is_enabled' => true,
        ]);

        SupportChannelCapability::create([
            'support_channel_id' => $channel->id,
            'capability' => 'send_messages',
            'status' => 'available',
            'source' => 'adapter',
        ]);

        $adapter = new TrendyolSupportChannelAdapter();

        // Disabled and not configured -> false
        $this->assertFalse($adapter->canReply($channel));

        // Configure connection
        IntegrationConnection::create([
            'store_id' => $store->id,
            'status' => 'configured',
            'provider' => 'trendyol',
        ]);

        // Enabled and configured -> true
        $this->assertTrue($adapter->canReply($channel));

        // Disable channel
        $channel->update(['is_enabled' => false]);
        $this->assertFalse($adapter->canReply($channel));
    }

    public function test_strict_parsing_and_tenant_idor_protection()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $store1 = $this->createStore($user1, 'Store 1');
        $store2 = $this->createStore($user2, 'Store 2');

        $channel1 = SupportChannel::create([
            'store_id' => $store1->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Destek 1',
            'is_enabled' => true,
        ]);

        SupportChannelCapability::create([
            'support_channel_id' => $channel1->id,
            'capability' => 'send_messages',
            'status' => 'available',
            'source' => 'adapter',
        ]);

        $question = MarketplaceQuestion::create([
            'store_id' => $store2->id, // Question belongs to store 2
            'external_question_id' => 'q123',
            'status' => 'waiting_for_answer',
            'question_text' => 'Fiyat nedir?',
        ]);

        $adapter = new TrendyolSupportChannelAdapter();

        // Attempting to send reply for store2's question using store1's channel -> should fail (IDOR check)
        $response = $adapter->sendReply($channel1, "trendyol_questions_{$question->id}", 'Cevap');
        $this->assertFalse($response['success']);
        $this->assertEquals('Konuşma veya Soru bulunamadı ya da bu mağazaya ait değil', $response['message']);

        // Attempting with invalid format -> should fail
        $response = $adapter->sendReply($channel1, "invalid_format_{$question->id}", 'Cevap');
        $this->assertFalse($response['success']);
        $this->assertEquals('Geçersiz konuşma formatı veya soru ID bulunamadı', $response['message']);
    }

    public function test_send_reply_idempotency()
    {
        $user = User::factory()->create();
        $store = $this->createStore($user, 'Trendyol Store');

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Destek',
            'is_enabled' => true,
        ]);

        SupportChannelCapability::create([
            'support_channel_id' => $channel->id,
            'capability' => 'send_messages',
            'status' => 'available',
            'source' => 'adapter',
        ]);

        $question = MarketplaceQuestion::create([
            'store_id' => $store->id,
            'external_question_id' => 'q123',
            'status' => 'waiting_for_answer',
            'question_text' => 'Fiyat nedir?',
        ]);

        $adapter = new TrendyolSupportChannelAdapter();

        // Set cache key indicating already processed
        $idempKey = 'test_idemp_key_123';
        $lockKey = "idemp_trendyol_reply_" . md5($idempKey);
        Cache::put($lockKey, 'dummy_channel_message_id', 60);

        $response = $adapter->sendReply($channel, "trendyol_questions_{$question->id}", 'Cevap', $idempKey);
        $this->assertTrue($response['success']);
        $this->assertTrue($response['is_duplicate']);
        $this->assertEquals('dummy_channel_message_id', $response['channel_message_id']);
    }
}
