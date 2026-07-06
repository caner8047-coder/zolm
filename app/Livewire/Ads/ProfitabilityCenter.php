<?php

namespace App\Livewire\Ads;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\AdProfitabilitySnapshot;
use App\Services\Ads\ProfitabilityService;
use App\Enums\CalculationStatus;

class ProfitabilityCenter extends Component
{
    use WithPagination;

    // ─── Filtreler ──────────────────────────────────────────────
    public string $statusFilter = '';

    protected $queryString = [
        'statusFilter' => ['except' => ''],
    ];

    // ─── Özet İstatistikler ────────────────────────────────────
    public array $stats = [
        'total_calculations' => 0,
        'complete_calculations' => 0,
        'partial_calculations' => 0,
        'insufficient_calculations' => 0,
        'total_net_profit' => 0,
        'avg_margin' => 0,
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
        $service = app(ProfitabilityService::class);
        $this->stats = $service->getProfitabilitySummary(auth()->id());
    }

    // ─── Kârlılık Verileri ────────────────────────────────────
    public function getProfitabilityDataProperty()
    {
        $query = AdProfitabilitySnapshot::where('user_id', auth()->id())
            ->with(['campaign', 'product']);

        if ($this->statusFilter) {
            $query->where('calculation_status', $this->statusFilter);
        }

        return $query->orderByDesc('period_start')
            ->paginate(20);
    }

    public function render()
    {
        return view('livewire.ads.profitability-center')
            ->layout('layouts.app', ['title' => 'Reklam Zekâsı — Kârlılık Merkezi']);
    }
}
