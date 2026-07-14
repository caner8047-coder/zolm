<?php

namespace App\Livewire\CustomerCare;

use Livewire\Component;
use App\Models\MarketplaceStore;
use App\Models\SupportAiRun;
use App\Models\SupportAgentAction;
use App\Models\SupportIntegrationDelivery;
use App\Models\SupportAiCostEvent;
use App\Services\Support\CustomerCareAiProviderHealthService;

class OpsCenter extends Component
{
    public int $selectedStoreId;

    public function mount()
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Bu sayfaya erişim yetkiniz bulunmamaktadır.');
        }

        $store = MarketplaceStore::first();
        $this->selectedStoreId = $store ? $store->id : 0;
    }

    public function render()
    {
        $stores = MarketplaceStore::all();

        $healthService = app(CustomerCareAiProviderHealthService::class);
        $providerHealth = [
            'Gemini' => $healthService->isProviderHealthy('Gemini') ? 'Healthy' : 'Unhealthy',
            'Groq' => $healthService->isProviderHealthy('Groq') ? 'Healthy' : 'Unhealthy',
        ];

        // Latency and calculations
        $runs = SupportAiRun::where('store_id', $this->selectedStoreId)
            ->where('status', '!=', 'failed')
            ->pluck('latency_ms')
            ->filter()
            ->sort()
            ->values()
            ->toArray();

        // AI Run Failure Rate (P1-3)
        $totalRunsCount = SupportAiRun::where('store_id', $this->selectedStoreId)->count();
        $failedRunsCount = SupportAiRun::where('store_id', $this->selectedStoreId)->where('status', 'failed')->count();
        $aiRunFailureRate = $totalRunsCount > 0 ? (int)(($failedRunsCount / $totalRunsCount) * 100) : null;

        // Actual Dispatch Failure Rate (P1-3)
        $totalDispatches = \App\Models\SupportDispatch::whereHas('conversation', function ($q) {
            $q->where('store_id', $this->selectedStoreId);
        })->count();
        $failedDispatches = \App\Models\SupportDispatch::whereHas('conversation', function ($q) {
            $q->where('store_id', $this->selectedStoreId);
        })->where('status', 'failed')->count();
        $dispatchFailureRate = $totalDispatches > 0 ? (int)(($failedDispatches / $totalDispatches) * 100) : null;

        $p50 = null;
        $p95 = null;
        $count = count($runs);
        if ($count > 0) {
            $p50Index = (int) floor($count * 0.5);
            $p95Index = (int) floor($count * 0.95);
            $p50 = $runs[$p50Index];
            $p95 = $runs[min($p95Index, $count - 1)];
        }

        // Cost estimations (P1-3: avoid showing unknown cost as fake zero)
        $totalCost = SupportAiCostEvent::where('store_id', $this->selectedStoreId)->sum('cost_estimate');
        $hasCostData = SupportAiCostEvent::where('store_id', $this->selectedStoreId)->exists();
        $unknownCostsCount = SupportAiCostEvent::where('store_id', $this->selectedStoreId)->whereNull('cost_estimate')->count();
        $knownCostsCount = SupportAiCostEvent::where('store_id', $this->selectedStoreId)->whereNotNull('cost_estimate')->count();

        // Circuit breaker: bayrak kapalıysa normal/closed gibi gösterme.
        $monitorMetrics = app(\App\Services\Support\CustomerCarePilotMonitorService::class)
            ->getStoreMetrics($this->selectedStoreId, auth()->user());
        $circuitBreakerStatus = $monitorMetrics['circuit_breaker_status'] ?? 'unknown';

        // Dead letter queue count
        $deadLetterCount = SupportIntegrationDelivery::whereHas('event', function ($q) {
                $q->where('store_id', $this->selectedStoreId);
            })
            ->where('status', 'dead_letter')
            ->count();

        // Policy blocks
        $policyBlockCount = SupportAgentAction::where(function($q) {
                $q->where('details_json->store_id', $this->selectedStoreId)
                  ->orWhere('details_json->store_id', (string) $this->selectedStoreId);
            })
            ->whereIn('action', ['wa_send_blocked', 'policy_blocked', 'policy_block'])
            ->count();

        return view('livewire.customer-care.ops-center', [
            'stores' => $stores,
            'providerHealth' => $providerHealth,
            'dispatchFailureRate' => $dispatchFailureRate,
            'aiRunFailureRate' => $aiRunFailureRate,
            'policyBlockCount' => $policyBlockCount,
            'circuitBreakerStatus' => $circuitBreakerStatus,
            'totalCost' => $totalCost,
            'hasCostData' => $hasCostData,
            'unknownCostsCount' => $unknownCostsCount,
            'knownCostsCount' => $knownCostsCount,
            'totalDispatches' => $totalDispatches,
            'totalRunsCount' => $totalRunsCount,
            'latencySampleCount' => $count,
            'p50' => $p50,
            'p95' => $p95,
            'deadLetterCount' => $deadLetterCount,
        ])->layout('layouts.app');
    }
}
