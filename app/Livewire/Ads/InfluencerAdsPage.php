<?php

namespace App\Livewire\Ads;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\AdCampaign;
use App\Models\InfluencerProfile;
use App\Models\InfluencerCreatorSnapshot;
use App\Enums\AdChannelCode;

class InfluencerAdsPage extends Component
{
    use WithPagination;

    // ─── Filtreler ──────────────────────────────────────────────
    public string $search = '';

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    // ─── Özet İstatistikler ────────────────────────────────────
    public array $stats = [
        'total_creators' => 0,
        'total_campaigns' => 0,
        'total_revenue' => 0,
        'total_sales' => 0,
        'total_new_customers' => 0,
        'avg_conversion' => 0,
    ];

    public function mount()
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $this->loadStats();
    }

    public function loadStats(): void
    {
        $userId = auth()->id();

        $this->stats['total_creators'] = InfluencerProfile::where('user_id', $userId)->count();
        $this->stats['total_campaigns'] = AdCampaign::where('user_id', $userId)
            ->where('channel_code', AdChannelCode::InfluencerAds->value)
            ->count();

        $snapshotStats = InfluencerCreatorSnapshot::whereHas('campaign', function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->where('channel_code', AdChannelCode::InfluencerAds->value);
        })->selectRaw('
            COALESCE(SUM(revenue_total), 0) as total_revenue,
            COALESCE(SUM(sales_total), 0) as total_sales,
            COALESCE(SUM(new_customers), 0) as total_new_customers,
            COALESCE(AVG(CASE WHEN link_visits > 0 THEN sales_total * 100.0 / link_visits ELSE 0 END), 0) as avg_conversion
        ')->first();

        $this->stats['total_revenue'] = $snapshotStats->total_revenue ?? 0;
        $this->stats['total_sales'] = $snapshotStats->total_sales ?? 0;
        $this->stats['total_new_customers'] = $snapshotStats->total_new_customers ?? 0;
        $this->stats['avg_conversion'] = $snapshotStats->avg_conversion ?? 0;
    }

    // ─── Creator Sorgusu ───────────────────────────────────────
    public function getCreatorsProperty()
    {
        $userId = auth()->id();

        $query = InfluencerProfile::where('user_id', $userId)
            ->withCount(['creatorSnapshots as total_revenue' => function ($q) use ($userId) {
                $q->whereHas('campaign', function ($cq) use ($userId) {
                    $cq->where('user_id', $userId)
                       ->where('channel_code', AdChannelCode::InfluencerAds->value);
                });
            }])
            ->withSum(['creatorSnapshots as total_sales_sum' => function ($q) use ($userId) {
                $q->whereHas('campaign', function ($cq) use ($userId) {
                    $cq->where('user_id', $userId)
                       ->where('channel_code', AdChannelCode::InfluencerAds->value);
                });
            }], 'sales_total');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('handle', 'like', "%{$this->search}%")
                  ->orWhere('display_name', 'like', "%{$this->search}%");
            });
        }

        return $query->paginate(20);
    }

    public function render()
    {
        return view('livewire.ads.influencer-ads-page')
            ->layout('layouts.app', ['title' => 'Reklam Zekâsı — Influencer Reklamları']);
    }
}
