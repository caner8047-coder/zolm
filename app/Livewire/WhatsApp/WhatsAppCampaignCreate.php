<?php

namespace App\Livewire\WhatsApp;

use App\Models\MarketplaceStore;
use App\Models\WaAccount;
use App\Models\WaAutomationConfig;
use App\Models\WaCampaign;
use App\Models\WaCampaignAudience;
use App\Models\WaCampaignEvent;
use App\Models\WaSegment;
use App\Models\WaTemplate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class WhatsAppCampaignCreate extends Component
{
    // Wizard adımı
    public int $step = 1;

    // Adım 1: Mağaza
    public ?int $storeId = null;

    // Adım 2: Segment
    public ?int $segmentId = null;
    public string $newSegmentName = '';
    public string $newSegmentDescription = '';
    public array $segmentFilters = [];
    public int $estimatedCount = 0;

    // Adım 3: Template
    public ?int $templateId = null;
    public array $templateParams = [];

    // Adım 4: Detaylar
    public string $campaignName = '';
    public string $campaignDescription = '';
    public ?string $scheduleAt = null;
    public int $batchSize = 50;
    public int $batchDelaySeconds = 5;
    public int $attributionWindowDays = 7;
    public bool $quietHoursEnabled = true;

    // Adım 5: Kupon
    public bool $couponEnabled = false;
    public string $couponType = 'percent';
    public float $couponValue = 0;
    public float $couponMinimumSpend = 0;
    public int $couponExpiryHours = 48;
    public int $couponUsageLimit = 1;

    // Yardımcı
    public bool $saving = false;

    public function getAvailableStoresProperty()
    {
        return MarketplaceStore::where('marketplace', 'woocommerce')
            ->where('is_active', true)
            ->get();
    }

    public function getSegmentsProperty()
    {
        if (!$this->storeId) return collect();
        return WaSegment::where('store_id', $this->storeId)->active()->get();
    }

    public function getTemplatesProperty()
    {
        if (!$this->storeId) return collect();
        $account = WaAccount::where('store_id', $this->storeId)->active()->first();
        if (!$account) return collect();
        return WaTemplate::forAccount($account)->approved()->where('category', 'marketing')->get();
    }

    public function updatedStoreId(): void
    {
        $this->segmentId = null;
        $this->templateId = null;
        $this->estimatedCount = 0;
    }

    public function updatedSegmentId(): void
    {
        if ($this->segmentId) {
            $segment = WaSegment::find($this->segmentId);
            if ($segment) {
                $engine = app(\App\Services\WhatsApp\SegmentEngine::class);
                $this->estimatedCount = $engine->estimateCount($segment);
            }
        }
    }

    public function nextStep(): void
    {
        if ($this->step < 5) {
            $this->step++;
        }
    }

    public function prevStep(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function saveAsDraft(): void
    {
        $this->saveCampaign(WaCampaign::STATUS_DRAFT);
    }

    public function submitForApproval(): void
    {
        $this->saveCampaign(WaCampaign::STATUS_DRAFT);

        $campaign = WaCampaign::latest()->first();
        if ($campaign) {
            $service = app(\App\Services\WhatsApp\CampaignService::class);
            try {
                $service->submitForApproval($campaign, auth()->id());
                session()->flash('wa_success', 'Kampanya onaya gönderildi.');
                return;
            } catch (\Throwable $e) {
                session()->flash('wa_error', $e->getMessage());
                return;
            }
        }
    }

    private function saveCampaign(string $status): void
    {
        $this->validate([
            'storeId' => 'required|exists:marketplace_stores,id',
            'campaignName' => 'required|string|max:150',
        ]);

        $campaign = WaCampaign::create([
            'store_id' => $this->storeId,
            'wa_account_id' => WaAccount::where('store_id', $this->storeId)->active()->first()?->id,
            'segment_id' => $this->segmentId,
            'template_id' => $this->templateId,
            'name' => $this->campaignName,
            'description' => $this->campaignDescription,
            'status' => $status,
            'template_params_json' => $this->templateParams,
            'schedule_at' => $this->scheduleAt,
            'batch_size' => $this->batchSize,
            'batch_delay_seconds' => $this->batchDelaySeconds,
            'attribution_window_days' => $this->attributionWindowDays,
            'quiet_hours_enabled' => $this->quietHoursEnabled,
            'coupon_enabled' => $this->couponEnabled,
            'coupon_type' => $this->couponType,
            'coupon_value' => $this->couponValue,
            'coupon_minimum_spend' => $this->couponMinimumSpend,
            'coupon_expiry_hours' => $this->couponExpiryHours,
            'coupon_usage_limit' => $this->couponUsageLimit,
            'created_by' => auth()->id(),
        ]);

        app(\App\Services\WhatsApp\AuditLogService::class)->log(
            'campaign_created',
            'wa_campaign',
            $campaign->id,
            ['name' => $this->campaignName, 'status' => $status],
        );
    }

    public function render()
    {
        return view('livewire.whatsapp.whatsapp-campaign-create');
    }
}
