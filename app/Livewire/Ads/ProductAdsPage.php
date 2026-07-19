<?php

namespace App\Livewire\Ads;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\AdCampaign;
use App\Models\AdCampaignSnapshot;
use App\Enums\AdChannelCode;
use App\Services\Ads\ProductAdsService;

class ProductAdsPage extends Component
{
    use WithPagination;

    private const SORTABLE_COLUMNS = ['name', 'status', 'roas'];

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
        $this->stats = app(ProductAdsService::class)->getCampaignStats(auth()->id());
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

        if ($this->sortBy === 'roas') {
            $query->orderBy(
                AdCampaignSnapshot::select('roas')
                    ->whereColumn('campaign_id', 'ad_campaigns.id')
                    ->latest('captured_at')
                    ->limit(1),
                $this->sortDir
            );
        } else {
            $query->orderBy($this->sortBy, $this->sortDir);
        }

        return $query->paginate(20);
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
        return view('livewire.ads.product-ads-page')
            ->layout('layouts.app', ['title' => 'Reklam Zekâsı — Ürün Reklamları']);
    }
}
