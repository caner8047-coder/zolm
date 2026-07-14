<?php

namespace App\Livewire\CustomerCare;

use Livewire\Component;
use App\Models\SupportReconciliationRun;
use App\Models\SupportReconciliationFinding;
use App\Models\SupportProjectionCursor;
use App\Services\Support\CustomerCareReconciliationService;
use App\Services\Support\Security\SupportRbacService;
use App\Livewire\CustomerCare\Concerns\ResolvesAccessibleStores;

class Reconciliation extends Component
{
    use ResolvesAccessibleStores;

    public int $selectedStoreId = 0;
    public string $errorMessage = '';
    public string $successMessage = '';

    protected $queryString = ['selectedStoreId'];

    public function mount()
    {
        if (!config('customer-care.reconciliation_enabled', false)) {
            abort(404);
        }

        $user = auth()->user();
        if (!$user || !in_array($user->role, ['admin', 'operator'], true)) {
            abort(403);
        }

        $this->resolveAccessibleStores();
    }

    public function runReconciliation()
    {
        $this->enforceSelectedStoreAccess();
        $rbac = app(SupportRbacService::class);
        $user = auth()->user();

        try {
            $rbac->enforcePermission($user, $this->selectedStoreId, 'force_circuit_breaker');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            return;
        }

        $service = app(CustomerCareReconciliationService::class);
        try {
            $run = $service->runReconciliation($this->selectedStoreId, $user);
            $this->successMessage = "Reconciliation analizi tamamlandı. Bulunan sapma (drift) sayısı: " . ($run->summary_json['findings_count'] ?? 0);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function repairFinding(int $findingId)
    {
        $this->enforceSelectedStoreAccess();
        $finding = SupportReconciliationFinding::where('store_id', $this->selectedStoreId)->find($findingId);
        if (!$finding) {
            $this->errorMessage = 'Bulgu bulunamadı.';
            return;
        }

        $user = auth()->user();

        $service = app(CustomerCareReconciliationService::class);
        try {
            // Livewire action triggers direct repair with execute = true
            // This will call enforceApproval which creates approval requests if governance is active.
            $service->repairFinding($finding, $user, true);
            $this->successMessage = "Bulgu başarıyla düzeltildi.";
        } catch (\App\Exceptions\ApprovalRequiredException $e) {
            $this->successMessage = $e->getMessage() . ' Onaylandıktan sonra tekrar tetikleyebilirsiniz.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $stores = $this->resolveAccessibleStores();

        $runs = SupportReconciliationRun::where('store_id', $this->selectedStoreId)
            ->latest()
            ->limit(10)
            ->get();

        $findings = SupportReconciliationFinding::where('store_id', $this->selectedStoreId)
            ->latest()
            ->get();

        $cursors = SupportProjectionCursor::where('store_id', $this->selectedStoreId)
            ->get();

        return view('livewire.customer-care.reconciliation', [
            'stores' => $stores,
            'runs' => $runs,
            'findings' => $findings,
            'cursors' => $cursors,
        ])->layout('layouts.app');
    }
}
