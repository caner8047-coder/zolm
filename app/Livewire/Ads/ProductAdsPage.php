<?php

namespace App\Livewire\Ads;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\AdCampaign;
use App\Models\AdCampaignSnapshot;
use App\Models\AdCampaignProduct;
use App\Enums\AdChannelCode;
use Illuminate\Support\Facades\DB;

class ProductAdsPage extends Component
{
    use WithPagination;

    // ─── Filtreler ──────────────────────────────────────────────
    public string $search = '';
    public string $roasFilter = '';
    public string $statusFilter = '';
    public string $sortBy = 'roas';
    public string $sortDir = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'roasFilter' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'sortBy' => ['except' => 'roas'],
        'sortDir' => ['except' => 'desc'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoasFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    // ─── Özet İstatistikler ────────────────────────────────────
    public array $stats = [
        'total_campaigns' => 0,
        'active_campaigns' => 0,
        'total_spend' => 0,
        'total_revenue' => 0,
        'avg_roas' => 0,
        'direct_roas' => 0,
        'indirect_roas' => 0,
        'zero_sale_spend' => 0,
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

        $query = AdCampaign::where('user_id', $userId)
            ->where('channel_code', AdChannelCode::ProductAds->value);

        $this->stats['total_campaigns'] = (clone $query)->count();
        $this->stats['active_campaigns'] = (clone $query)->where('status', 'active')->count();

        // Son snapshot'lardan istatistik hesapla
        $snapshotQuery = AdCampaignSnapshot::whereHas('campaign', fn($q) => $q->where('user_id', $userId)->where('channel_code', AdChannelCode::ProductAds->value))
            ->whereIn('id', function ($q) use ($userId) {
                $q->selectRaw('MAX(id)')
                    ->from('ad_campaign_snapshots')
                    ->groupBy('campaign_id');
            });

        $stats = (clone $snapshotQuery)->selectRaw('
            COALESCE(SUM(spend), 0) as total_spend,
            COALESCE(SUM(revenue_total), 0) as total_revenue,
            COALESCE(SUM(CASE WHEN spend > 0 THEN revenue_total / spend ELSE 0 END) / COUNT(*), 0) as avg_roas,
            COALESCE(SUM(CASE WHEN spend > 0 THEN revenue_direct / spend ELSE 0 END) / COUNT(*), 0) as direct_roas,
            COALESCE(SUM(CASE WHEN spend > 0 THEN revenue_indirect / spend ELSE 0 END) / COUNT(*), 0) as indirect_roas
        ')->first();

        $this->stats['total_spend'] = $stats->total_spend ?? 0;
        $this->stats['total_revenue'] = $stats->total_revenue ?? 0;
        $this->stats['avg_roas'] = $stats->avg_roas ?? 0;
        $this->stats['direct_roas'] = $stats->direct_roas ?? 0;
        $this->stats['indirect_roas'] = $stats->indirect_roas ?? 0;

        // Sıfır satışlı harcama
        $this->stats['zero_sale_spend'] = (clone $snapshotQuery)
            ->where('sales_total', 0)
            ->where('spend', '>', 0)
            ->sum('spend');
    }

    // ─── Kampanya Sorgusu ──────────────────────────────────────
    public function getCampaignsProperty()
    {
        $userId = auth()->id();

        $query = AdCampaign::where('user_id', $userId)
            ->where('channel_code', AdChannelCode::ProductAds->value)
            ->with('latestSnapshot');

        // Arama
        if ($this->search) {
            $query->where('name', 'like', "%{$this->search}%");
        }

        // Durum filtresi
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        // ROAS filtresi
        if ($this->roasFilter) {
            $query->whereHas('latestSnapshot', function ($q) {
                match ($this->roasFilter) {
                    'high' => $q->where('roas', '>=', 5),
                    'medium' => $q->where('roas', '>=', 2)->where('roas', '<', 5),
                    'low' => $q->where('roas', '<', 2),
                    'zero' => $q->where('roas', '=', 0),
                    default => null,
                };
            });
        }

        // Sıralama
        $query->with('latestSnapshot');
        $query->orderBy($this->sortBy === 'roas' ? 'id' : $this->sortBy, $this->sortDir);

        return $query->paginate(20);
    }

    public function sortTable(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'desc';
        }
    }

    public function render()
    {
        return view('livewire.ads.product-ads-page')
            ->layout('layouts.app', ['title' => 'Reklam Zekâsı — Ürün Reklamları']);
    }
}
