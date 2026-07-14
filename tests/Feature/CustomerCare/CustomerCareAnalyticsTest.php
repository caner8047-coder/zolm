<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\MarketplaceStore;
use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportAgentAction;
use App\Models\User;
use App\Models\LegalEntity;
use App\Services\Support\CustomerCareAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

class CustomerCareAnalyticsTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_route_returns_404_when_feature_flag_is_disabled()
    {
        Config::set('customer-care.analytics_enabled', false);

        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('customer-care.analytics'));
        $response->assertStatus(404);
    }

    public function test_tenant_isolation_prevents_unauthorized_access()
    {
        Config::set('customer-care.analytics_enabled', true);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $store1 = $this->createStore($user1, 'Store 1');

        $this->actingAs($user2);

        \Livewire\Livewire::test(\App\Livewire\CustomerCare\Analytics::class, ['selectedStoreId' => $store1->id])
            ->assertStatus(403);
    }

    public function test_kpi_calculations_are_accurate()
    {
        $user = User::factory()->create();
        $store = $this->createStore($user, 'Store 1');

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Destek',
            'is_enabled' => true,
        ]);

        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'store_id' => $store->id,
            'external_conversation_id' => 'trendyol_questions_66',
            'external_customer_id' => 'cust66',
            'status' => 'resolved',
            'ownership_status' => 'human',
            'source_type' => 'chat',
        ]);

        // Inbound message
        SupportMessage::create([
            'conversation_id' => $conv->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'body_encrypted' => 'Merhaba',
            'sent_at' => now()->subMinutes(10),
        ]);

        // Outbound agent reply
        SupportMessage::create([
            'conversation_id' => $conv->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'body_encrypted' => 'Merhaba, nasil yardimci olabilirim?',
            'sent_at' => now()->subMinutes(5),
        ]);

        // Policy block action
        SupportAgentAction::create([
            'conversation_id' => $conv->id,
            'user_id' => $user->id,
            'action' => 'policy_block',
            'details_json' => ['reason' => 'Kapida odeme engellendi'],
        ]);

        $service = app(CustomerCareAnalyticsService::class);
        $metrics = $service->getStoreMetrics($store->id, 30, $user);

        $this->assertEquals(1, $metrics['total_conversations']);
        $this->assertEquals(1, $metrics['human_reply_count']);
        $this->assertEquals(100.0, $metrics['handoff_rate']);
        $this->assertEquals(1, $metrics['policy_block_count']);
        $this->assertEquals(100.0, $metrics['resolution_rate']);
    }

    public function test_sla_breach_detection()
    {
        $user = User::factory()->create();
        $store = $this->createStore($user, 'Store 1');

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Destek',
            'is_enabled' => true,
        ]);

        // 1. Conversation open and waiting response for > 30 minutes
        $conv1 = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'store_id' => $store->id,
            'external_conversation_id' => 'trendyol_questions_77',
            'external_customer_id' => 'cust77',
            'status' => 'open',
            'last_inbound_at' => now()->subMinutes(40),
            'last_outbound_at' => null,
            'source_type' => 'chat',
        ]);

        // Inbound message 40 mins ago
        SupportMessage::create([
            'conversation_id' => $conv1->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'body_encrypted' => 'Kargo?',
            'created_at' => now()->subMinutes(40),
        ]);

        $service = app(CustomerCareAnalyticsService::class);
        $metrics = $service->getStoreMetrics($store->id, 30, $user);

        $this->assertEquals(1, $metrics['breached_first_response_count']);
        $this->assertTrue($metrics['breached_conversations']->contains('id', $conv1->id));
    }

    public function test_secure_pii_redacted_export()
    {
        Config::set('customer-care.analytics_enabled', true);

        $user = User::factory()->create(['email' => 'sensitive-pii@example.com']);
        $this->actingAs($user);

        $store = $this->createStore($user, 'Store 1');
        $store->update(['store_name' => '=HYPERLINK("https://evil.test","x")']);

        $livewire = \Livewire\Livewire::test(\App\Livewire\CustomerCare\Analytics::class, ['selectedStoreId' => $store->id]);
        $response = $livewire->instance()->exportReport();

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);

        ob_start();
        $response->sendContent();
        $csvContent = ob_get_clean();

        // Verify UTF-8 BOM is present
        $this->assertStringStartsWith(chr(0xEF).chr(0xBB).chr(0xBF), $csvContent);
        // Verify UTF-8 encoding
        $this->assertTrue(mb_check_encoding($csvContent, 'UTF-8'));
        // Verify XML control characters are cleaned
        $this->assertDoesNotMatchRegularExpression('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $csvContent);
        // Verify PII is not leaked/leaked agent/user details
        $this->assertStringNotContainsString('sensitive-pii@example.com', $csvContent);
        $this->assertStringContainsString("'=HYPERLINK", $csvContent);
    }

    public function test_analytics_does_not_fabricate_topic_metrics_when_no_ai_runs()
    {
        $user = User::factory()->create();
        $store = $this->createStore($user, 'Store 1');

        $service = app(CustomerCareAnalyticsService::class);
        $metrics = $service->getStoreMetrics($store->id, 30, $user);

        // Verify topics metrics array is completely empty instead of containing fabricated default records
        $this->assertEmpty($metrics['topics']);
        $this->assertNull($metrics['avg_first_response_time']);
        $this->assertNull($metrics['avg_resolution_time']);
    }
}
