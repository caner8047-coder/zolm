<?php

namespace App\Livewire\Ads;

use Livewire\Component;
use App\Models\AdCampaign;
use App\Models\AdCampaignSnapshot;
use App\Models\AdProductSnapshot;
use App\Models\AdCampaignProduct;
use App\Models\AdReconciliation;

class ProductAdsCampaignDetail extends Component
{
    public int $campaignId;
    public ?AdCampaign $campaign = null;
    public array $snapshots = [];
    public array $productSnapshots = [];
    public array $reconciliations = [];
    public array $stats = [
        'total_spend' => 0,
        'total_revenue_direct' => 0,
        'total_revenue_indirect' => 0,
        'total_revenue' => 0,
        'total_sales' => 0,
        'avg_roas' => 0,
        'direct_roas' => 0,
        'indirect_roas' => 0,
    ];

    public function mount(int $campaignId)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $this->campaignId = $campaignId;

        $this->campaign = AdCampaign::where('user_id', auth()->id())
            ->where('id', $campaignId)
            ->firstOrFail();

        $this->loadData();
    }

    public function loadData(): void
    {
        // Kampanya snapshot'ları
        $this->snapshots = AdCampaignSnapshot::where('campaign_id', $this->campaignId)
            ->orderByDesc('captured_at')
            ->take(10)
            ->get()
            ->toArray();

        // Ürün bazlı snapshot'lar
        $this->productSnapshots = AdProductSnapshot::where('campaign_id', $this->campaignId)
            ->with('adCampaignProduct')
            ->orderByDesc('captured_at')
            ->take(50)
            ->get()
            ->toArray();

        // Mutabakat kayıtları
        $this->reconciliations = AdReconciliation::where('campaign_id', $this->campaignId)
            ->orderByDesc('calculated_at')
            ->take(10)
            ->get()
            ->toArray();

        // Son snapshot'tan istatistikleri hesapla
        $latestSnapshot = AdCampaignSnapshot::where('campaign_id', $this->campaignId)
            ->latest('captured_at')
            ->first();

        if ($latestSnapshot) {
            $this->stats['total_spend'] = $latestSnapshot->spend;
            $this->stats['total_revenue_direct'] = $latestSnapshot->revenue_direct;
            $this->stats['total_revenue_indirect'] = $latestSnapshot->revenue_indirect;
            $this->stats['total_revenue'] = $latestSnapshot->revenue_total;
            $this->stats['total_sales'] = $latestSnapshot->sales_total;
            $this->stats['avg_roas'] = $latestSnapshot->roas;
            $this->stats['direct_roas'] = $latestSnapshot->spend > 0 ? $latestSnapshot->revenue_direct / $latestSnapshot->spend : 0;
            $this->stats['indirect_roas'] = $latestSnapshot->spend > 0 ? $latestSnapshot->revenue_indirect / $latestSnapshot->spend : 0;
        }
    }

    public function render()
    {
        return view('livewire.ads.product-ads-campaign-detail')
            ->layout('layouts.app', ['title' => 'Reklam Zekâsı — ' . ($this->campaign?->name ?? 'Kampanya Detayı')]);
    }
}
