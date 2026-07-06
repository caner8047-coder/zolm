<?php

namespace App\Livewire\Ads;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\AdRecommendation;
use App\Models\AdRecommendationAction;
use App\Services\Ads\RuleEngine;
use App\Enums\AdRecommendationPriority;

class AiActionCenter extends Component
{
    use WithPagination;

    // ─── Filtreler ──────────────────────────────────────────────
    public string $priorityFilter = '';
    public string $statusFilter = '';

    protected $queryString = [
        'priorityFilter' => ['except' => ''],
        'statusFilter' => ['except' => 'new'],
    ];

    // ─── Özet İstatistikler ────────────────────────────────────
    public array $stats = [
        'total_recommendations' => 0,
        'new_recommendations' => 0,
        'critical_count' => 0,
        'high_count' => 0,
        'accepted_count' => 0,
        'rejected_count' => 0,
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

        $stats = AdRecommendation::where('user_id', $userId)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "new" THEN 1 ELSE 0 END) as new_count,
                SUM(CASE WHEN priority = "critical" THEN 1 ELSE 0 END) as critical,
                SUM(CASE WHEN priority = "high" THEN 1 ELSE 0 END) as high,
                SUM(CASE WHEN status = "accepted" THEN 1 ELSE 0 END) as accepted,
                SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected
            ')->first();

        $this->stats['total_recommendations'] = $stats->total ?? 0;
        $this->stats['new_recommendations'] = $stats->new_count ?? 0;
        $this->stats['critical_count'] = $stats->critical ?? 0;
        $this->stats['high_count'] = $stats->high ?? 0;
        $this->stats['accepted_count'] = $stats->accepted ?? 0;
        $this->stats['rejected_count'] = $stats->rejected ?? 0;
    }

    // ─── Öneri Sorgusu ─────────────────────────────────────────
    public function getRecommendationsProperty()
    {
        $query = AdRecommendation::where('user_id', auth()->id());

        if ($this->priorityFilter) {
            $query->where('priority', $this->priorityFilter);
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        return $query->orderByDesc('created_at')
            ->paginate(20);
    }

    // ─── Aksiyonlar ────────────────────────────────────────────
    public function acceptRecommendation(int $recommendationId): void
    {
        $recommendation = AdRecommendation::where('user_id', auth()->id())
            ->findOrFail($recommendationId);

        $recommendation->update(['status' => 'accepted']);

        AdRecommendationAction::create([
            'recommendation_id' => $recommendationId,
            'user_id' => auth()->id(),
            'action' => 'accepted',
        ]);

        $this->loadStats();
    }

    public function rejectRecommendation(int $recommendationId): void
    {
        $recommendation = AdRecommendation::where('user_id', auth()->id())
            ->findOrFail($recommendationId);

        $recommendation->update(['status' => 'rejected']);

        AdRecommendationAction::create([
            'recommendation_id' => $recommendationId,
            'user_id' => auth()->id(),
            'action' => 'rejected',
        ]);

        $this->loadStats();
    }

    public function snoozeRecommendation(int $recommendationId): void
    {
        $recommendation = AdRecommendation::where('user_id', auth()->id())
            ->findOrFail($recommendationId);

        $recommendation->update(['status' => 'snoozed']);

        AdRecommendationAction::create([
            'recommendation_id' => $recommendationId,
            'user_id' => auth()->id(),
            'action' => 'snoozed',
        ]);

        $this->loadStats();
    }

    // ─── Kural Motoru Tetikle ──────────────────────────────────
    public function runRuleEngine(): void
    {
        $ruleEngine = app(RuleEngine::class);
        $ruleEngine->runAllRules(auth()->id());
        $this->loadStats();
    }

    public function render()
    {
        return view('livewire.ads.ai-action-center')
            ->layout('layouts.app', ['title' => 'Reklam Zekâsı — AI Aksiyon Merkezi']);
    }
}
