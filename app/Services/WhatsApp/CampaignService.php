<?php

namespace App\Services\WhatsApp;

use App\Models\WaCampaign;
use App\Models\WaCampaignAudience;
use App\Models\WaCampaignEvent;
use App\Models\WaContact;
use App\Models\WaFrequencyCap;
use App\Models\WaOutbox;
use App\Models\WaTemplate;
use Illuminate\Support\Facades\DB;

class CampaignService
{
    /**
     * Kampanyayı taslaktan onaya gönder
     */
    public function submitForApproval(WaCampaign $campaign, int $userId): void
    {
        if ($campaign->status !== WaCampaign::STATUS_DRAFT) {
            throw new \RuntimeException('Sadece draft kampanyalar onaya gönderilebilir.');
        }

        // Template doğrulama
        $this->validateTemplate($campaign);

        $campaign->update([
            'status' => WaCampaign::STATUS_PENDING_APPROVAL,
        ]);

        $this->logEvent($campaign, null, 'submitted', null, $userId);
    }

    /**
     * Kampanyayı onayla
     */
    public function approve(WaCampaign $campaign, int $userId): void
    {
        if ($campaign->status !== WaCampaign::STATUS_PENDING_APPROVAL) {
            throw new \RuntimeException('Sadece pending_approval kampanyaları onaylanabilir.');
        }

        // Self-approval kontrolü (opsiyonel — config'den)
        if ($campaign->created_by === $userId && !config('whatsapp.campaigns.allow_self_approval', false)) {
            throw new \RuntimeException('Kendi kampanyanızı onaylayamazsınız.');
        }

        $campaign->update([
            'status' => WaCampaign::STATUS_APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        $this->logEvent($campaign, null, 'approved', null, $userId);
    }

    /**
     * Kampanyayı zamanla
     */
    public function schedule(WaCampaign $campaign, string $scheduleAt): void
    {
        if (!in_array($campaign->status, [WaCampaign::STATUS_APPROVED, WaCampaign::STATUS_DRAFT], true)) {
            throw new \RuntimeException('Bu durumdaki kampanya zamanlanamaz.');
        }

        $campaign->update([
            'status' => WaCampaign::STATUS_SCHEDULED,
            'schedule_at' => $scheduleAt,
        ]);

        $this->logEvent($campaign, null, 'scheduled', ['schedule_at' => $scheduleAt]);
    }

    /**
     * Kampanyayı duraklat
     */
    public function pause(WaCampaign $campaign, int $userId): void
    {
        if ($campaign->status !== WaCampaign::STATUS_RUNNING) {
            throw new \RuntimeException('Sadece running kampanya duraklatılabilir.');
        }

        $campaign->update([
            'status' => WaCampaign::STATUS_PAUSED,
            'paused_at' => now(),
        ]);

        // Gönderilmemiş audience kayıtlarını durdur
        $campaign->audiences()
            ->where('eligibility_status', 'queued')
            ->update(['eligibility_status' => 'skipped', 'exclusion_reason' => 'campaign_paused']);

        $this->logEvent($campaign, null, 'paused', null, $userId);
    }

    /**
     * Kampanyayı devam ettir
     */
    public function resume(WaCampaign $campaign, int $userId): void
    {
        if ($campaign->status !== WaCampaign::STATUS_PAUSED) {
            throw new \RuntimeException('Sadece paused kampanya devam ettirilebilir.');
        }

        $campaign->update([
            'status' => WaCampaign::STATUS_RUNNING,
            'paused_at' => null,
        ]);

        // Skipped audience kayıtlarını tekrar queue'ya al
        $campaign->audiences()
            ->where('eligibility_status', 'skipped')
            ->where('exclusion_reason', 'campaign_paused')
            ->update(['eligibility_status' => 'eligible']);

        $this->logEvent($campaign, null, 'resumed', null, $userId);
    }

    /**
     * Kampanyayı iptal et
     */
    public function cancel(WaCampaign $campaign, string $reason, int $userId): void
    {
        if (!in_array($campaign->status, [WaCampaign::STATUS_DRAFT, WaCampaign::STATUS_APPROVED, WaCampaign::STATUS_SCHEDULED, WaCampaign::STATUS_RUNNING, WaCampaign::STATUS_PAUSED], true)) {
            throw new \RuntimeException('Bu durumdaki kampanya iptal edilemez.');
        }

        $campaign->update([
            'status' => WaCampaign::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        // Pending outbox kayıtlarını iptal et
        $campaign->audiences()
            ->whereHas('outbox', function ($q) {
                $q->where('status', WaOutbox::STATUS_QUEUED);
            })
            ->each(function ($audience) {
                $audience->outbox->update(['status' => WaOutbox::STATUS_CANCELLED]);
                $audience->update(['eligibility_status' => 'skipped', 'exclusion_reason' => 'campaign_cancelled']);
            });

        // Henüz queue edilmemiş audience'leri de iptal et
        $campaign->audiences()
            ->where('eligibility_status', 'queued')
            ->update(['eligibility_status' => 'skipped', 'exclusion_reason' => 'campaign_cancelled']);

        $this->logEvent($campaign, null, 'cancelled', ['reason' => $reason], $userId);
    }

    /**
     * Template doğrulama
     */
    private function validateTemplate(WaCampaign $campaign): void
    {
        if (!$campaign->template_id) {
            throw new \RuntimeException('Şablon seçilmemiş.');
        }

        $template = WaTemplate::find($campaign->template_id);
        if (!$template) {
            throw new \RuntimeException('Şablon bulunamadı.');
        }

        if ($template->status !== 'approved') {
            throw new \RuntimeException('Şablon approved durumda değil.');
        }

        if ($template->wa_account_id !== $campaign->wa_account_id) {
            throw new \RuntimeException('Şablon bu hesaba ait değil.');
        }
    }

    /**
     * Durum değişikliği event kaydı
     */
    private function logEvent(WaCampaign $campaign, ?int $audienceId, string $eventType, ?array $payload = null, ?int $actorUserId = null): void
    {
        WaCampaignEvent::create([
            'campaign_id' => $campaign->id,
            'audience_id' => $audienceId,
            'event_type' => $eventType,
            'payload_json' => $payload,
            'actor_user_id' => $actorUserId,
        ]);
    }
}
