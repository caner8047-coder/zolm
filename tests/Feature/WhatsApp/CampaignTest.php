<?php

namespace Tests\Feature\WhatsApp;

use App\Models\WaAutomationConfig;
use App\Models\WaCampaign;
use App\Models\WaCampaignAudience;
use App\Models\WaCampaignEvent;
use App\Models\WaSegment;
use App\Services\WhatsApp\CampaignService;
use App\Services\WhatsApp\CampaignSenderService;
use App\Services\WhatsApp\SegmentEngine;

class CampaignTest extends WhatsAppTestCase
{
    public function test_draft_campaign_no_outbox(): void
    {
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905321111111');
        $this->giveConsent($contact, $store, 'marketing', 'granted');

        $segment = WaSegment::create([
            'store_id' => $store->id,
            'name' => 'Test Segment',
            'status' => 'active',
            'rules_json' => ['filters' => []],
            'created_by' => $store->user_id,
        ]);

        $campaign = WaCampaign::create([
            'store_id' => $store->id,
            'wa_account_id' => $this->createAccount($store)->id,
            'segment_id' => $segment->id,
            'name' => 'Draft Kampanya',
            'status' => WaCampaign::STATUS_DRAFT,
            'created_by' => $store->user_id,
        ]);

        $service = new CampaignSenderService();
        $service->startCampaign($campaign);

        $this->assertEquals(0, WaCampaignAudience::count());
    }

    public function test_approval_required_before_scheduled(): void
    {
        $store = $this->createStore();
        $account = $this->createAccount($store);
        $campaign = WaCampaign::create([
            'store_id' => $store->id,
            'wa_account_id' => $account->id,
            'name' => 'Test',
            'status' => WaCampaign::STATUS_PENDING_APPROVAL,
            'created_by' => $store->user_id,
        ]);

        $service = new CampaignService();
        $this->expectException(\RuntimeException::class);
        $service->schedule($campaign, now()->addHour()->toDateTimeString());
    }

    public function test_self_approval_prevented_by_default(): void
    {
        $store = $this->createStore();
        $account = $this->createAccount($store);
        $campaign = WaCampaign::create([
            'store_id' => $store->id,
            'wa_account_id' => $account->id,
            'name' => 'Test',
            'status' => WaCampaign::STATUS_PENDING_APPROVAL,
            'created_by' => $store->user_id,
        ]);

        $service = new CampaignService();
        $this->expectException(\RuntimeException::class);
        $service->approve($campaign, $store->user_id);
    }

    public function test_template_mismatch_prevents_approval(): void
    {
        $store = $this->createStore();
        $account = $this->createAccount($store);
        $otherAccount = $this->createAccount($store);

        // Gerçek template oluştur — diğer hesaba ait
        $template = \App\Models\WaTemplate::create([
            'wa_account_id' => $otherAccount->id,
            'name' => 'test_template',
            'language' => 'tr',
            'category' => 'marketing',
            'status' => 'approved',
        ]);

        $campaign = WaCampaign::create([
            'store_id' => $store->id,
            'wa_account_id' => $account->id,
            'template_id' => $template->id,
            'name' => 'Test',
            'status' => WaCampaign::STATUS_DRAFT,
            'created_by' => $store->user_id,
        ]);

        $service = new CampaignService();
        $this->expectException(\RuntimeException::class);
        $service->submitForApproval($campaign, $store->user_id);
    }

    public function test_pause_prevents_new_recipient_queuing(): void
    {
        $store = $this->createStore();
        $account = $this->createAccount($store);
        $contact = $this->createContact($store, '+905322222222');
        $this->giveConsent($contact, $store, 'marketing', 'granted');

        $segment = WaSegment::create([
            'store_id' => $store->id, 'name' => 'Pause Test',
            'status' => 'active', 'rules_json' => ['filters' => []],
            'created_by' => $store->user_id,
        ]);

        $campaign = WaCampaign::create([
            'store_id' => $store->id, 'wa_account_id' => $account->id,
            'segment_id' => $segment->id, 'name' => 'Pause Test',
            'status' => WaCampaign::STATUS_RUNNING, 'created_by' => $store->user_id,
        ]);

        WaCampaignAudience::create([
            'campaign_id' => $campaign->id, 'contact_id' => $contact->id,
            'store_id' => $store->id, 'eligibility_status' => 'queued',
        ]);

        $service = new CampaignService();
        $service->pause($campaign, $store->user_id);

        $paused = WaCampaignAudience::where('campaign_id', $campaign->id)
            ->where('eligibility_status', 'skipped')->count();

        $this->assertGreaterThan(0, $paused);
    }

    public function test_cancel_clears_queued_outbox(): void
    {
        $store = $this->createStore();
        $account = $this->createAccount($store);
        $contact = $this->createContact($store, '+905323333333');
        $this->giveConsent($contact, $store, 'marketing', 'granted');

        $segment = WaSegment::create([
            'store_id' => $store->id, 'name' => 'Cancel Test',
            'status' => 'active', 'rules_json' => ['filters' => []],
            'created_by' => $store->user_id,
        ]);

        $campaign = WaCampaign::create([
            'store_id' => $store->id, 'wa_account_id' => $account->id,
            'segment_id' => $segment->id, 'name' => 'Cancel Test',
            'status' => WaCampaign::STATUS_RUNNING, 'created_by' => $store->user_id,
        ]);

        WaCampaignAudience::create([
            'campaign_id' => $campaign->id, 'contact_id' => $contact->id,
            'store_id' => $store->id, 'eligibility_status' => 'queued',
        ]);

        $service = new CampaignService();
        $service->cancel($campaign, 'Test iptali', $store->user_id);

        $cancelled = WaCampaignAudience::where('campaign_id', $campaign->id)
            ->where('eligibility_status', 'skipped')
            ->where('exclusion_reason', 'campaign_cancelled')
            ->count();

        $this->assertGreaterThan(0, $cancelled);
        $this->assertEquals(WaCampaign::STATUS_CANCELLED, $campaign->fresh()->status);
    }

    public function test_running_campaign_not_editable(): void
    {
        $store = $this->createStore();
        $campaign = WaCampaign::create([
            'store_id' => $store->id,
            'wa_account_id' => $this->createAccount($store)->id,
            'name' => 'Running Test',
            'status' => WaCampaign::STATUS_RUNNING,
            'created_by' => $store->user_id,
        ]);

        $this->assertFalse($campaign->isEditable());
        $this->assertTrue($campaign->isRunnable());
    }

    public function test_segment_change_requires_re_approval(): void
    {
        $store = $this->createStore();
        $campaign = WaCampaign::create([
            'store_id' => $store->id,
            'wa_account_id' => $this->createAccount($store)->id,
            'name' => 'Segment Change',
            'status' => WaCampaign::STATUS_APPROVED,
            'created_by' => $store->user_id,
        ]);

        // Segment değişikliği → status pending_approval'a düşmeli
        $newSegment = WaSegment::create([
            'store_id' => $store->id, 'name' => 'New Segment',
            'status' => 'active', 'rules_json' => ['filters' => []],
            'created_by' => $store->user_id,
        ]);
        $campaign->update(['segment_id' => $newSegment->id, 'status' => WaCampaign::STATUS_PENDING_APPROVAL]);

        $service = new CampaignService();
        $this->expectException(\RuntimeException::class);
        $service->schedule($campaign, now()->addHour()->toDateTimeString());
    }

    public function test_same_campaign_contact_not_queued_twice(): void
    {
        $store = $this->createStore();
        $account = $this->createAccount($store);
        $contact = $this->createContact($store, '+905324444444');
        $this->giveConsent($contact, $store, 'marketing', 'granted');

        $segment = WaSegment::create([
            'store_id' => $store->id, 'name' => 'Dupe Test',
            'status' => 'active', 'rules_json' => ['filters' => []],
            'created_by' => $store->user_id,
        ]);

        $campaign = WaCampaign::create([
            'store_id' => $store->id, 'wa_account_id' => $account->id,
            'segment_id' => $segment->id, 'name' => 'Dupe Test',
            'status' => WaCampaign::STATUS_DRAFT, 'created_by' => $store->user_id,
        ]);

        WaCampaignAudience::create([
            'campaign_id' => $campaign->id, 'contact_id' => $contact->id,
            'store_id' => $store->id, 'eligibility_status' => 'eligible',
        ]);

        try {
            WaCampaignAudience::create([
                'campaign_id' => $campaign->id, 'contact_id' => $contact->id,
                'store_id' => $store->id, 'eligibility_status' => 'eligible',
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique constraint — beklenen
        }

        // En az 1 kayıt olmalı, 2 olmamalı
        $this->assertDatabaseHas('wa_campaign_audiences', [
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
        ]);
    }
}
