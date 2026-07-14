<?php

namespace App\Livewire\CustomerCare;

use Livewire\Component;
use App\Models\SupportAiRun;
use App\Models\SupportMessage;
use App\Livewire\CustomerCare\Concerns\ResolvesAccessibleStores;
use App\Services\Support\AI\CustomerCareEvalService;
use App\Services\Support\AI\CustomerCareAiProviderInterface;
use App\Services\Support\Security\SupportRbacService;

class PilotDashboard extends Component
{
    use ResolvesAccessibleStores;

    public ?int $selectedStoreId = null;
    public string $evalStatus = 'Not Evaluated';
    public int $avgEvalScore = 0;
    public array $evalDetails = [];

    public function mount()
    {
        $myStores = $this->resolveAccessibleStores();
        if ($myStores->isEmpty()) {
            abort(403, 'Erişilebilir bir mağazanız bulunmuyor.');
        }

        $this->selectedStoreId = $myStores->first()->id;

        $this->loadLastEval();
    }

    public function updatedSelectedStoreId()
    {
        $this->loadLastEval();
    }

    public function loadLastEval()
    {
        if ($this->selectedStoreId) {
            $this->enforceSelectedStoreAccess();
            $evalService = app(CustomerCareEvalService::class);
            $lastEval = $evalService->getLatestGoldenEval($this->selectedStoreId);
            if ($lastEval) {
                $this->avgEvalScore = $lastEval['average_score'] ?? 0;
                $this->evalStatus = ($lastEval['passed_eval_gate'] ?? false) ? 'PASSED Eval Gate' : 'FAILED Eval Gate';
                $this->evalDetails = $lastEval['details'] ?? [];
            } else {
                $this->avgEvalScore = 0;
                $this->evalStatus = 'Not Evaluated';
                $this->evalDetails = [];
            }
        } else {
            $this->avgEvalScore = 0;
            $this->evalStatus = 'Not Evaluated';
            $this->evalDetails = [];
        }
    }

    public function runGoldenEval()
    {
        $this->enforceSelectedStoreAccess();
        app(SupportRbacService::class)
            ->enforcePermission(auth()->user(), (int) $this->selectedStoreId, 'ai_draft_generate');

        $evalService = app(CustomerCareEvalService::class);
        $provider = app(CustomerCareAiProviderInterface::class);

        $result = $evalService->runGoldenDatasetEval(
            $this->selectedStoreId,
            $provider,
            auth()->id(),
            'tr-local-v1',
            'tr',
            auth()->user()
        );

        $this->avgEvalScore = $result['average_score'];
        $this->evalStatus = $result['passed_eval_gate'] ? 'PASSED Eval Gate' : 'FAILED Eval Gate';
        $this->evalDetails = $result['details'];
    }

    public function toggleCircuitBreaker()
    {
        if ($this->selectedStoreId) {
            $this->enforceSelectedStoreAccess();
            app(SupportRbacService::class)
                ->enforcePermission(auth()->user(), (int) $this->selectedStoreId, 'force_circuit_breaker');
            $forcedOpen = \Illuminate\Support\Facades\Cache::get("circuit_breaker_forced_open_{$this->selectedStoreId}", false);
            if ($forcedOpen) {
                \Illuminate\Support\Facades\Cache::forget("circuit_breaker_forced_open_{$this->selectedStoreId}");
            } else {
                \Illuminate\Support\Facades\Cache::put("circuit_breaker_forced_open_{$this->selectedStoreId}", true);
            }
        }
    }

    public function maskPii(?string $text): string
    {
        return app(\App\Services\Support\Security\PiiRedactor::class)->maskPii($text);
    }

    public function render()
    {
        // Yetki Kontrolü
        if ($this->selectedStoreId) {
            \App\Services\Support\TenantContext::enforceStoreAccess($this->selectedStoreId, auth()->user());
        }

        $myStores = $this->resolveAccessibleStores();

        // Son AI ledger kayıtları (Seçilen mağazaya özel)
        $aiRuns = $this->selectedStoreId
            ? SupportAiRun::where('store_id', $this->selectedStoreId)
                ->with(['conversation'])
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get()
            : collect();

        // Aktif AI taslakları (Seçilen mağazaya özel)
        $activeDrafts = $this->selectedStoreId
            ? SupportMessage::where('sender_type', 'ai')
                ->where('delivery_status', 'draft')
                ->whereHas('conversation', function ($q) {
                    $q->where('store_id', $this->selectedStoreId);
                })
                ->with(['conversation'])
                ->orderBy('id', 'desc')
                ->limit(5)
                ->get()
            : collect();

        // Readiness ve Policy Block verilerini çekelim
        $readiness = $this->selectedStoreId
            ? app(\App\Services\Support\CustomerCarePilotReadinessService::class)->checkReadiness($this->selectedStoreId)
            : ['ready' => false, 'checks' => [], 'latest_errors' => []];

        $policyBlocks = $this->selectedStoreId
            ? \App\Models\SupportAgentAction::where('action', 'policy_block')
                ->whereHas('conversation', function ($q) {
                    $q->where('store_id', $this->selectedStoreId);
                })
                ->latest()
                ->limit(5)
                ->get()
            : collect();

        $metrics = $this->selectedStoreId
            ? app(\App\Services\Support\CustomerCarePilotMonitorService::class)->getStoreMetrics($this->selectedStoreId, auth()->user())
            : null;

        return view('livewire.customer-care.pilot-dashboard', [
            'aiRuns' => $aiRuns,
            'activeDrafts' => $activeDrafts,
            'myStores' => $myStores,
            'readiness' => $readiness,
            'policyBlocks' => $policyBlocks,
            'metrics' => $metrics,
        ])->layout('layouts.app');
    }
}
