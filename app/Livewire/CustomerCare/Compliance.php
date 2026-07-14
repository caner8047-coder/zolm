<?php

namespace App\Livewire\CustomerCare;

use Livewire\Component;
use App\Models\SupportDataSubjectRequest;
use App\Models\SupportLegalHold;
use App\Models\SupportConsentRecord;
use App\Models\SupportDataLineageEvent;
use App\Services\Support\Compliance\CustomerCareComplianceService;
use App\Services\Support\Security\SupportRbacService;
use App\Livewire\CustomerCare\Concerns\ResolvesAccessibleStores;

class Compliance extends Component
{
    use ResolvesAccessibleStores;

    public int $selectedStoreId = 0;
    public string $errorMessage = '';
    public string $successMessage = '';

    // Data Subject Request creation variables
    public string $customerId = '';
    public string $requestType = 'export'; // export, rectification, anonymize, delete

    // Legal hold creation variables
    public string $holdCustomerId = '';
    public string $holdReason = '';

    // Lineage variables
    public string $searchCustomerId = '';
    public $lineageEvents = [];

    protected $queryString = ['selectedStoreId'];

    public function mount()
    {
        if (!config('customer-care.compliance_enabled', false)) {
            abort(404);
        }

        $user = auth()->user();
        if (!$user || !in_array($user->role, ['admin', 'operator'], true)) {
            abort(403);
        }

        $this->resolveAccessibleStores();
    }

    public function createDsr()
    {
        $this->enforceSelectedStoreAccess();
        $this->validate([
            'customerId' => 'required|string|min:3',
            'requestType' => 'required|in:export,rectification,anonymize,delete',
        ]);

        $rbac = app(SupportRbacService::class);
        $user = auth()->user();

        // Enforce RBAC permission checks
        try {
            $rbac->enforcePermission($user, $this->selectedStoreId, 'run_compliance');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            return;
        }

        $service = app(CustomerCareComplianceService::class);
        try {
            $result = $service->processDsrRequest(
                $this->selectedStoreId,
                $this->customerId,
                $this->requestType,
                [],
                $user
            );
            if ($this->requestType === 'export') {
                $this->successMessage = 'Veri çıkarma talebi oluşturuldu. İndirmek için tablodaki "İndir" butonunu kullanın.';
            } else {
                $this->successMessage = $result['message'] ?? 'Tepki başarıyla işlendi.';
            }
            $this->customerId = '';
        } catch (\App\Exceptions\ApprovalRequiredException $e) {
            $this->successMessage = $e->getMessage() . ' Onaylandıktan sonra tekrar çalıştırabilirsiniz.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function exportDsr(int $dsrId)
    {
        $this->enforceSelectedStoreAccess();
        $dsr = SupportDataSubjectRequest::where('store_id', $this->selectedStoreId)->find($dsrId);
        if (!$dsr) {
            $this->errorMessage = 'Talep bulunamadı.';
            return;
        }

        $rbac = app(SupportRbacService::class);
        $user = auth()->user();

        // Enforce RBAC
        try {
            $rbac->enforcePermission($user, $this->selectedStoreId, 'run_compliance');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            return;
        }

        $service = app(CustomerCareComplianceService::class);
        try {
            $content = $service->generateAccessExport(
                $this->selectedStoreId,
                $dsr->customer_id,
                $dsr->id,
                $user
            );
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            return;
        }

        $refId = 'dsr_' . $dsr->id . '_' . substr(hash('sha256', $dsr->customer_id), 0, 8);

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, "dsr-export-{$refId}.json", [
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
    }

    public function addLegalHold()
    {
        $this->enforceSelectedStoreAccess();
        $this->validate([
            'holdCustomerId' => 'required|string|min:3',
            'holdReason' => 'required|string|min:5',
        ]);

        $rbac = app(SupportRbacService::class);
        $user = auth()->user();

        try {
            $rbac->enforcePermission($user, $this->selectedStoreId, 'run_compliance');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            return;
        }

        SupportLegalHold::updateOrCreate([
            'store_id' => $this->selectedStoreId,
            'customer_hash' => hash('sha256', $this->holdCustomerId),
        ], [
            'customer_id' => $this->holdCustomerId,
            'reason' => $this->holdReason,
            'active' => true,
        ]);

        $this->successMessage = 'Yasal veri koruma engeli (Legal Hold) uygulandı.';
        $this->holdCustomerId = '';
        $this->holdReason = '';
    }

    public function releaseHold(int $holdId)
    {
        $this->enforceSelectedStoreAccess();
        $hold = SupportLegalHold::where('store_id', $this->selectedStoreId)->find($holdId);
        if (!$hold) {
            $this->errorMessage = 'Yasal koruma kaydı bulunamadı.';
            return;
        }

        $rbac = app(SupportRbacService::class);
        $user = auth()->user();
        try {
            $rbac->enforcePermission($user, $this->selectedStoreId, 'run_compliance');
            $rbac->enforceApproval($user, $this->selectedStoreId, 'legal_hold_release_' . $hold->id, [
                'hold_id' => $hold->id,
                'customer_hash' => $hold->customer_hash,
            ]);
        } catch (\App\Exceptions\ApprovalRequiredException $e) {
            $this->successMessage = $e->getMessage() . ' Onaylandıktan sonra tekrar çalıştırabilirsiniz.';
            return;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            return;
        }

        if ($hold->active) {
            $hold->update(['active' => false]);
            $this->successMessage = 'Yasal koruma engeli kaldırıldı.';
        } else {
            $this->successMessage = 'Yasal koruma engeli zaten kaldırılmış.';
        }
    }

    public function searchLineage()
    {
        $this->enforceSelectedStoreAccess();
        try {
            app(SupportRbacService::class)
                ->enforcePermission(auth()->user(), $this->selectedStoreId, 'run_compliance');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            return;
        }

        $this->validate([
            'searchCustomerId' => 'required|string',
        ]);

        $hashedSearchId = hash('sha256', $this->searchCustomerId);

        $this->lineageEvents = SupportDataLineageEvent::where('store_id', $this->selectedStoreId)
            ->where('customer_id', $hashedSearchId)
            ->latest()
            ->get()
            ->toArray();
    }

    public function render()
    {
        $stores = $this->resolveAccessibleStores();

        $dsrs = SupportDataSubjectRequest::where('store_id', $this->selectedStoreId)
            ->latest()
            ->get();

        $holds = SupportLegalHold::where('store_id', $this->selectedStoreId)
            ->where('active', true)
            ->get();

        $consents = SupportConsentRecord::where('store_id', $this->selectedStoreId)
            ->latest()
            ->limit(20)
            ->get();

        return view('livewire.customer-care.compliance', [
            'stores' => $stores,
            'dsrs' => $dsrs,
            'holds' => $holds,
            'consents' => $consents,
        ])->layout('layouts.app');
    }
}
