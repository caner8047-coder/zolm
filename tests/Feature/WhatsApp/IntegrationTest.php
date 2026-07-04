<?php

namespace Tests\Feature\WhatsApp;

use App\Models\WaWebhookEndpoint;
use App\Models\WaWebhookLog;
use App\Models\WaNotificationChannel;
use App\Models\WaNotificationTemplate;
use App\Models\WaNotificationSend;
use App\Models\WaExternalIntegration;
use App\Services\WhatsApp\WebhookReceiverService;
use App\Services\WhatsApp\IntegrationHealthService;
use App\Services\WhatsApp\NotificationBridgeService;
use App\Services\WhatsApp\IntegrationAdapterRegistry;
use Illuminate\Support\Facades\Config;

class IntegrationTest extends WhatsAppTestCase
{
    public function test_webhook_receiver_processes_inbound(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();

        $endpoint = WaWebhookEndpoint::create([
            'store_id' => $store->id,
            'name' => 'Test Webhook',
            'provider' => 'whatsapp',
            'url' => 'https://example.com/webhook',
            'status' => 'active',
            'is_active' => true,
        ]);

        $service = new WebhookReceiverService();
        $result = $service->receive($endpoint, ['event_type' => 'test'], '{"event_type":"test"}', null);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['log_id']);
    }

    public function test_webhook_invalid_signature_rejected(): void
    {
        $store = $this->createStore();

        $endpoint = WaWebhookEndpoint::create([
            'store_id' => $store->id,
            'name' => 'Test',
            'provider' => 'whatsapp',
            'url' => 'https://example.com',
            'secret_encrypted' => 'secret-key',
            'status' => 'active',
            'is_active' => true,
        ]);

        $service = new WebhookReceiverService();
        $result = $service->receive($endpoint, ['test' => true], '{"test":true}', 'wrong-signature');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid signature', $result['error']);
    }

    public function test_webhook_log_created(): void
    {
        $store = $this->createStore();

        $endpoint = WaWebhookEndpoint::create([
            'store_id' => $store->id,
            'name' => 'Test',
            'provider' => 'whatsapp',
            'url' => 'https://example.com',
            'status' => 'active',
            'is_active' => true,
        ]);

        $service = new WebhookReceiverService();
        $service->receive($endpoint, ['event_type' => 'test'], '{"test":true}', null);

        $log = WaWebhookLog::where('endpoint_id', $endpoint->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('processed', $log->status);
        $this->assertNotNull($log->processing_time_ms);
    }

    public function test_integration_health_check(): void
    {
        $store = $this->createStore();

        $integration = WaExternalIntegration::create([
            'store_id' => $store->id,
            'provider' => 'woocommerce',
            'name' => 'WC Test',
            'status' => 'configured',
            'is_enabled' => true,
            'config_json' => ['url' => ''],
        ]);

        $service = new IntegrationHealthService();
        $result = $service->checkHealth($integration);

        $this->assertEquals('error', $result['status']);
        $this->assertNotNull($integration->fresh()->last_health_check_at);
    }

    public function test_notification_bridge_sends_to_active_channel(): void
    {
        $store = $this->createStore();

        $channel = WaNotificationChannel::create([
            'store_id' => $store->id,
            'key' => 'sms',
            'name' => 'SMS',
            'type' => 'sms',
            'status' => 'configured',
            'is_enabled' => true,
        ]);

        $template = WaNotificationTemplate::create([
            'channel_id' => $channel->id,
            'key' => 'welcome',
            'name' => 'Hoş Geldin',
            'body_template' => 'Merhaba {name}, hoş geldiniz!',
            'status' => 'active',
        ]);

        $service = new NotificationBridgeService();
        $result = $service->send($channel, 'welcome', '+905321111111', ['name' => 'Test']);

        $this->assertTrue($result['success']);

        $send = WaNotificationSend::where('channel_id', $channel->id)->first();
        $this->assertNotNull($send);
        $this->assertEquals('sent', $send->status);
    }

    public function test_notification_disabled_channel(): void
    {
        $store = $this->createStore();

        $channel = WaNotificationChannel::create([
            'store_id' => $store->id,
            'key' => 'email',
            'name' => 'E-posta',
            'type' => 'email',
            'status' => 'configured',
            'is_enabled' => false,
        ]);

        $service = new NotificationBridgeService();
        $result = $service->send($channel, 'welcome', 'test@example.com', ['name' => 'Test']);

        $this->assertFalse($result['success']);
    }

    public function test_notification_missing_template(): void
    {
        $store = $this->createStore();

        $channel = WaNotificationChannel::create([
            'store_id' => $store->id,
            'key' => 'email',
            'name' => 'E-posta',
            'type' => 'email',
            'status' => 'configured',
            'is_enabled' => true,
        ]);

        $service = new NotificationBridgeService();
        $result = $service->send($channel, 'nonexistent', 'test@example.com', ['name' => 'Test']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Şablon bulunamadı', $result['message']);
    }

    public function test_integration_adapter_registry(): void
    {
        $registry = new IntegrationAdapterRegistry();

        $wcAdapter = $registry->resolve('woocommerce');
        $this->assertEquals('woocommerce', $wcAdapter->key());

        $nullAdapter = $registry->resolve('unknown');
        $this->assertEquals('unsupported', $nullAdapter->healthCheck(null)['status']);
    }
}
