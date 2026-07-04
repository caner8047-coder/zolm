<?php

namespace App\Services\WhatsApp;

use App\Models\WaCampaign;
use App\Models\WaCampaignAudience;
use App\Models\WaCampaignEvent;
use App\Models\WaContact;
use App\Models\WaFrequencyCap;
use App\Models\WaOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CampaignSenderService
{
    /**
     * Zamanlanmış kampanyaları işle
     */
    public function processScheduledCampaigns(): int
    {
        $campaigns = WaCampaign::where('status', WaCampaign::STATUS_SCHEDULED)
            ->where('schedule_at', '<=', now())
            ->with('account')
            ->limit(5)
            ->get();

        $processed = 0;
        foreach ($campaigns as $campaign) {
            $this->startCampaign($campaign);
            $processed++;
        }

        return $processed;
    }

    /**
     * Kampanyayı başlat — audience snapshot oluştur
     */
    public function startCampaign(WaCampaign $campaign): void
    {
        if ($campaign->status !== WaCampaign::STATUS_SCHEDULED && $campaign->status !== WaCampaign::STATUS_APPROVED) {
            return;
        }

        $campaign->update([
            'status' => WaCampaign::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        // Audience snapshot oluştur
        $segmentEngine = app(SegmentEngine::class);
        $contactIds = $segmentEngine->evaluate($campaign->segment);

        $eligibleService = app(EligibilityService::class);

        $batch = [];
        foreach ($contactIds as $contactId) {
            $contact = WaContact::find($contactId);
            if (!$contact) {
                continue;
            }

            // Final eligibility check
            if (!$eligibleService->isEligibleForMessaging($contact, 'marketing')) {
                continue;
            }

            // Frequency cap kontrolü
            if ($this->isFrequencyCapped($campaign, $contact)) {
                continue;
            }

            // Aktif cart recovery kontrolü
            if ($this->hasActiveCartRecovery($contact)) {
                continue;
            }

            $batch[] = [
                'campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
                'store_id' => $campaign->store_id,
                'eligibility_status' => 'eligible',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Toplu insert
        foreach (array_chunk($batch, 500) as $chunk) {
            DB::table('wa_campaign_audiences')->insert($chunk);
        }

        // Event log
        WaCampaignEvent::create([
            'campaign_id' => $campaign->id,
            'event_type' => 'started',
            'payload_json' => ['recipient_count' => count($batch)],
        ]);

        // Toplam alıcı güncelle
        $campaign->update(['total_recipients' => count($batch)]);
    }

    /**
     * Running kampanyaların batch'lerini gönder
     */
    public function processRunningCampaigns(): int
    {
        $campaigns = WaCampaign::where('status', WaCampaign::STATUS_RUNNING)
            ->with('account')
            ->limit(3)
            ->get();

        $totalSent = 0;
        foreach ($campaigns as $campaign) {
            $sent = $this->processCampaignBatch($campaign);
            $totalSent += $sent;

            // Tüm eligible bittiyse tamamla
            $remaining = $campaign->audiences()
                ->where('eligibility_status', 'eligible')
                ->count();

            if ($remaining === 0) {
                $campaign->update(['status' => WaCampaign::STATUS_COMPLETED, 'completed_at' => now()]);
                WaCampaignEvent::create([
                    'campaign_id' => $campaign->id,
                    'event_type' => 'completed',
                    'payload_json' => ['total_sent' => $campaign->total_sent],
                ]);
            }
        }

        return $totalSent;
    }

    /**
     * Tek bir kampanyanın batch'ini gönder
     */
    private function processCampaignBatch(WaCampaign $campaign): int
    {
        $batchSize = $campaign->batch_size ?? 50;

        $audiences = WaCampaignAudience::where('campaign_id', $campaign->id)
            ->where('eligibility_status', 'eligible')
            ->limit($batchSize)
            ->with('contact')
            ->get();

        $sent = 0;
        foreach ($audiences as $audience) {
            $result = $this->sendToAudience($campaign, $audience);
            if ($result) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Tek bir alıcıya kampanya mesajı gönder
     */
    private function sendToAudience(WaCampaign $campaign, WaCampaignAudience $audience): bool
    {
        $contact = $audience->contact;
        if (!$contact || $contact->status !== 'active') {
            $audience->update(['eligibility_status' => 'excluded', 'exclusion_reason' => 'contact_inactive']);
            return false;
        }

        // Test modu kontrolü
        $eligibleService = app(EligibilityService::class);
        if (!$eligibleService->isEligibleForMessaging($contact, 'marketing')) {
            $audience->update(['eligibility_status' => 'excluded', 'exclusion_reason' => 'eligibility_failed']);
            return false;
        }

        // Frequency cap
        if ($this->isFrequencyCapped($campaign, $contact)) {
            $audience->update(['eligibility_status' => 'excluded', 'exclusion_reason' => 'frequency_capped']);
            return false;
        }

        // Quiet hours
        if ($campaign->quiet_hours_enabled) {
            $eligibleService2 = app(EligibilityService::class);
            if ($eligibleService2->isWithinQuietHours()) {
                $audience->update(['eligibility_status' => 'eligible']); // Kalır, sonra tekrar denenir
                return false;
            }
        }

        // Kupon oluştur (eğer açıksa)
        $couponId = null;
        if ($campaign->coupon_enabled && $campaign->coupon_value > 0) {
            $couponId = $this->createCampaignCoupon($campaign, $contact);
            if (!$couponId) {
                $audience->update(['eligibility_status' => 'failed', 'exclusion_reason' => 'coupon_creation_failed']);
                return false;
            }
        }

        // Idempotency key
        $idempotencyKey = "campaign:{$campaign->id}:{$contact->id}";

        // Template parametreleri
        $templateParams = $campaign->template_params_json ?? [];

        try {
            $outboxService = app(OutboxService::class);
            $outbox = $outboxService->enqueue(
                contact: $contact,
                messageType: 'template',
                templateName: $campaign->template->name ?? '',
                templateLanguage: $campaign->template->language ?? 'tr',
                templateParams: $templateParams,
                priority: 'normal',
                automationKey: 'bulk_campaign',
            );

            $audience->update([
                'eligibility_status' => 'queued',
                'outbox_id' => $outbox->id,
                'coupon_id' => $couponId,
                'queued_at' => now(),
            ]);

            // Frequency cap güncelle
            $this->incrementFrequencyCap($campaign->store_id, $contact->id, 'marketing');

            return true;
        } catch (\Illuminate\Database\QueryException $e) {
            if (($e->errorInfo[1] ?? 0) === 1062) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Frekans limiti kontrolü
     */
    private function isFrequencyCapped(WaCampaign $campaign, WaContact $contact): bool
    {
        $config = \App\Models\WaAutomationConfig::get('frequency_cap', [
            'marketing_max_per_24h' => 2,
            'marketing_max_per_7d' => 5,
            'marketing_max_per_30d' => 15,
        ]);

        $max24h = $config['marketing_max_per_24h'] ?? 2;
        $max7d = $config['marketing_max_per_7d'] ?? 5;
        $max30d = $config['marketing_max_per_30d'] ?? 15;

        $last24h = WaOutbox::where('contact_id', $contact->id)
            ->where('automation_key', 'bulk_campaign')
            ->where('status', 'sent')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        if ($last24h >= $max24h) {
            return true;
        }

        $last7d = WaOutbox::where('contact_id', $contact->id)
            ->where('automation_key', 'bulk_campaign')
            ->where('status', 'sent')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        if ($last7d >= $max7d) {
            return true;
        }

        $last30d = WaOutbox::where('contact_id', $contact->id)
            ->where('automation_key', 'bulk_campaign')
            ->where('status', 'sent')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return $last30d >= $max30d;
    }

    private function hasActiveCartRecovery(WaContact $contact): bool
    {
        return \App\Models\WaAbandonedCart::where('contact_id', $contact->id)
            ->active()
            ->exists();
    }

    private function incrementFrequencyCap(int $storeId, int $contactId, string $messageClass): void
    {
        $windows = ['24h', '7d', '30d'];
        foreach ($windows as $window) {
            WaFrequencyCap::updateOrCreate(
                ['contact_id' => $contactId, 'store_id' => $storeId, 'message_class' => $messageClass, 'rolling_window_key' => $window],
                ['sent_count' => DB::raw('sent_count + 1'), 'last_sent_at' => now()]
            );
        }
    }

    private function createCampaignCoupon(WaCampaign $campaign, WaContact $contact): ?int
    {
        $code = strtoupper(substr(uniqid('CMP-'), 0, 12));
        $idempotencyKey = "campaign_coupon:{$campaign->id}:{$contact->id}";

        $existing = \App\Models\WaCoupon::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing->id;
        }

        $coupon = \App\Models\WaCoupon::create([
            'store_id' => $campaign->store_id,
            'contact_id' => $contact->id,
            'automation_key' => 'bulk_campaign',
            'code' => $code,
            'discount_type' => $campaign->coupon_type,
            'discount_value' => $campaign->coupon_value,
            'minimum_spend' => $campaign->coupon_minimum_spend,
            'expires_at' => now()->addHours($campaign->coupon_expiry_hours),
            'idempotency_key' => $idempotencyKey,
        ]);

        return $coupon->id;
    }
}
