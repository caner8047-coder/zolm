<?php

namespace App\Livewire\Ads;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\AdCampaign;
use App\Models\AdKeywordSnapshot;
use App\Enums\AdChannelCode;
use App\Enums\StoreTargetingType;

class StoreAdsPage extends Component
{
    use WithPagination;

    private const SORTABLE_COLUMNS = ['name', 'status', 'selected_gbm', 'recommended_gbm', 'actual_gbm'];

    // ─── Filtreler ──────────────────────────────────────────────
    public string $search = '';
    public string $targetingFilter = '';
    public string $sortBy = 'name';
    public string $sortDir = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'targetingFilter' => ['except' => ''],
        'sortBy' => ['except' => 'name'],
        'sortDir' => ['except' => 'desc'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    // ─── Özet İstatistikler ────────────────────────────────────
    public array $stats = [
        'total_campaigns' => 0,
        'smart_campaigns' => 0,
        'manual_campaigns' => 0,
        'total_keywords' => 0,
        'zero_sale_keywords' => 0,
        'total_spend' => 0,
        'avg_gbm' => 0,
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

        $campaigns = AdCampaign::where('user_id', $userId)
            ->where('channel_code', AdChannelCode::StoreAds->value);

        $this->stats['total_campaigns'] = (clone $campaigns)->count();
        $this->stats['smart_campaigns'] = (clone $campaigns)->where('targeting_type', StoreTargetingType::Smart->value)->count();
        $this->stats['manual_campaigns'] = (clone $campaigns)->where('targeting_type', StoreTargetingType::Manual->value)->count();

        // Kelime istatistikleri
        $keywordQuery = AdKeywordSnapshot::whereHas('campaign', function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->where('channel_code', AdChannelCode::StoreAds->value);
        });

        $keywordStats = (clone $keywordQuery)->selectRaw('
            COUNT(DISTINCT keyword) as total_keywords,
            COALESCE(SUM(CASE WHEN sales_total = 0 AND spend > 0 THEN 1 ELSE 0 END), 0) as zero_sale_keywords,
            COALESCE(SUM(spend), 0) as total_spend,
            COALESCE(AVG(CASE WHEN impressions > 0 THEN spend / impressions * 1000 ELSE 0 END), 0) as avg_gbm
        ')->first();

        $this->stats['total_keywords'] = $keywordStats->total_keywords ?? 0;
        $this->stats['zero_sale_keywords'] = $keywordStats->zero_sale_keywords ?? 0;
        $this->stats['total_spend'] = $keywordStats->total_spend ?? 0;
        $this->stats['avg_gbm'] = $keywordStats->avg_gbm ?? 0;
    }

    // ─── Kampanya Sorgusu ──────────────────────────────────────
    public function getCampaignsProperty()
    {
        $userId = auth()->id();

        $query = AdCampaign::where('user_id', $userId)
            ->where('channel_code', AdChannelCode::StoreAds->value);

        if ($this->search) {
            $query->where('name', 'like', "%{$this->search}%");
        }

        if ($this->targetingFilter) {
            $query->where('targeting_type', $this->targetingFilter);
        }

        return $query->orderBy($this->sortBy, $this->sortDir)->paginate(20);
    }

    public function sortTable(string $column): void
    {
        if (!in_array($column, self::SORTABLE_COLUMNS, true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'desc';
        }
    }

    public function render()
    {
        return view('livewire.ads.store-ads-page')
            ->layout('layouts.app', ['title' => 'Reklam Zekâsı — Mağaza Reklamları']);
    }
}
