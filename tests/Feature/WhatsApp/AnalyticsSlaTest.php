<?php

namespace Tests\Feature\WhatsApp;

use App\Models\SlaDefinition;
use App\Models\SlaTrack;
use App\Models\SlaEvent;
use App\Models\SupportConversation;
use App\Models\SupportChannel;
use App\Models\WaDailyMetric;
use App\Models\WaCampaign;
use App\Models\WaCampaignAudience;
use App\Services\WhatsApp\AnalyticsService;
use App\Services\WhatsApp\ReportingService;
use App\Services\WhatsApp\SLAService;
use Illuminate\Support\Facades\Config;

class AnalyticsSlaTest extends WhatsAppTestCase
{
    public function test_daily_metrics_created_for_store(): void
    {
        $store = $this->createStore();
        $service = new AnalyticsService();
        $service->calculateDailyMetrics($store->id);

        $metric = WaDailyMetric::where('store_id', $store->id)
            ->where('metric_date', today())
            ->first();

        $this->assertNotNull($metric);
        $this->assertEquals(0, $metric->messages_sent);
    }

    public function test_overview_summary_contains_expected_fields(): void
    {
        $store = $this->createStore();
        $service = new AnalyticsService();
        $summary = $service->getOverviewSummary($store->id);

        $this->assertArrayHasKey('today', $summary);
        $this->assertArrayHasKey('weekly', $summary);
        $this->assertArrayHasKey('active_campaigns', $summary);
        $this->assertArrayHasKey('open_conversations', $summary);
        $this->assertArrayHasKey('avg_delivery_rate', $summary['weekly']);
    }

    public function test_sla_track_created_for_conversation(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();

        SlaDefinition::create([
            'store_id' => $store->id,
            'name' => 'Normal SLA',
            'channel' => 'all',
            'priority' => 'normal',
            'first_response_minutes' => 60,
            'resolution_minutes' => 480,
            'business_hours_only' => false,
            'is_active' => true,
        ]);

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'whatsapp',
            'name' => 'WhatsApp',
            'status' => 'active',
            'is_active' => true,
        ]);

        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'wa_sla_test',
            'store_id' => $store->id,
            'source_type' => 'whatsapp',
            'status' => 'open',
        ]);

        $service = new SLAService();
        $track = $service->startTracking($conv);

        $this->assertNotNull($track);
        $this->assertEquals('active', $track->status);
        $this->assertNotNull($track->first_response_deadline);
        $this->assertNotNull($track->resolution_deadline);
    }

    public function test_first_response_recorded(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();

        $def = SlaDefinition::create([
            'store_id' => $store->id,
            'name' => 'Test SLA',
            'channel' => 'all',
            'priority' => 'normal',
            'first_response_minutes' => 60,
            'resolution_minutes' => 480,
            'business_hours_only' => false,
            'is_active' => true,
        ]);

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'whatsapp',
            'name' => 'WhatsApp',
            'status' => 'active',
            'is_active' => true,
        ]);

        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'wa_fr_test',
            'store_id' => $store->id,
            'source_type' => 'whatsapp',
            'status' => 'open',
        ]);

        $service = new SLAService();
        $track = $service->startTracking($conv);
        $service->recordFirstResponse($track);

        $track->refresh();
        $this->assertNotNull($track->first_response_at);
        $this->assertFalse($track->first_response_breached); // 60 dk içinde yanıt verildi
    }

    public function test_sla_breach_detection(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();

        $def = SlaDefinition::create([
            'store_id' => $store->id,
            'name' => 'Kısa SLA',
            'channel' => 'all',
            'priority' => 'normal',
            'first_response_minutes' => 1,
            'resolution_minutes' => 2,
            'business_hours_only' => false,
            'is_active' => true,
        ]);

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'whatsapp',
            'name' => 'WhatsApp',
            'status' => 'active',
            'is_active' => true,
        ]);

        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'wa_breach_test',
            'store_id' => $store->id,
            'source_type' => 'whatsapp',
            'status' => 'open',
        ]);

        $service = new SLAService();
        $track = $service->startTracking($conv);

        // Deadline'i geçmiş gibi yap
        $track->update([
            'resolution_deadline' => now()->subMinute(),
            'first_response_deadline' => now()->subMinute(),
        ]);

        $results = $service->checkBreaches();

        $this->assertGreaterThan(0, $results['resolution_breached'] + $results['first_response_breached']);
    }

    public function test_sla_stats(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();

        $service = new SLAService();
        $stats = $service->getStats($store->id);

        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('compliance_rate', $stats);
        $this->assertArrayHasKey('avg_resolution_minutes', $stats);
    }

    public function test_campaign_report(): void
    {
        $store = $this->createStore();
        $campaign = WaCampaign::create([
            'store_id' => $store->id,
            'wa_account_id' => $this->createAccount($store)->id,
            'name' => 'Rapor Test',
            'status' => 'completed',
            'created_by' => $store->user_id,
            'total_recipients' => 100,
            'total_sent' => 80,
            'total_delivered' => 75,
            'total_read' => 50,
            'total_clicked' => 20,
            'total_converted' => 5,
        ]);

        $service = new ReportingService();
        $report = $service->getCampaignReport($campaign);

        $this->assertArrayHasKey('campaign', $report);
        $this->assertArrayHasKey('audience_summary', $report);
        $this->assertEquals(100, $report['audience_summary']['total']);
        $this->assertEquals(5, $report['audience_summary']['converted']);
    }
}
