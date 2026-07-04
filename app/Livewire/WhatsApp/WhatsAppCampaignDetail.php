<?php

namespace App\Livewire\WhatsApp;

use App\Models\WaCampaign;
use App\Models\WaCampaignDailyMetric;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class WhatsAppCampaignDetail extends Component
{
    public int $campaignId = 0;
    public array $campaign = [];
    public array $metrics = [];
    public array $audienceSummary = [];

    public function mount(int $id): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $this->campaignId = $id;
        $this->loadCampaign();
    }

    public function loadCampaign(): void
    {
        $campaign = WaCampaign::with(['segment', 'account', 'template'])
            ->find($this->campaignId);

        if (!$campaign) return;

        $this->campaign = $campaign->toArray();
        $this->metrics = WaCampaignDailyMetric::where('campaign_id', $this->campaignId)
            ->orderBy('metric_date')->get()->toArray();

        $audiences = $campaign->audiences();
        $this->audienceSummary = $audiences->select('eligibility_status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('eligibility_status')
            ->pluck('count', 'eligibility_status')
            ->toArray();
    }

    public function render()
    {
        return view('livewire.whatsapp.whatsapp-campaign-detail');
    }
}
