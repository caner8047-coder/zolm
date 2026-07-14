<?php

namespace App\Livewire\CustomerCare;

use Livewire\Component;
use App\Models\MarketplaceStore;
use App\Models\SupportProductionReadinessRun;
use App\Models\SupportProductionFreezeSnapshot;
use App\Services\Support\CustomerCareProductionReadinessService;
use App\Services\Support\CustomerCareOrganizationContext;
use App\Services\Support\TenantContext;
use Illuminate\Support\Facades\Auth;

class Production extends Component
{
    public ?int $selectedStoreId = null;
    public array $drillResult = [];

    public function mount(): void
    {
        $accessibleStores = CustomerCareOrganizationContext::getAccessibleStores(Auth::user())->get();
        if ($accessibleStores->isNotEmpty()) {
            $this->selectedStoreId = $accessibleStores->first()->id;
        }
    }

    public function checkReadiness(): void
    {
        if (!$this->selectedStoreId) return;

        $user = Auth::user();
        TenantContext::enforceStoreAccess($this->selectedStoreId, $user);

        app(CustomerCareProductionReadinessService::class)->checkReadiness($this->selectedStoreId, $user);
        session()->flash('readiness_success', 'Hazırlık denetimi başarıyla tamamlandı.');
    }

    public function freezeConfiguration(int $runId): void
    {
        if (!$this->selectedStoreId) return;

        $user = Auth::user();
        TenantContext::enforceStoreAccess($this->selectedStoreId, $user);

        app(CustomerCareProductionReadinessService::class)->freezeConfiguration($this->selectedStoreId, $runId, $user);
        session()->flash('freeze_success', 'Konfigürasyon anlık görüntüsü başarıyla donduruldu (Freeze Snapshot).');
    }

    public function approveFreeze(int $snapshotId): void
    {
        try {
            app(CustomerCareProductionReadinessService::class)->approveFreeze($snapshotId, Auth::id());
            session()->flash('approve_success', 'Freeze snapshot yetkili onay mekanizması tarafından onaylandı.');
        } catch (\Throwable $e) {
            session()->flash('approve_error', $e->getMessage());
        }
    }

    public function runRollbackDrill(): void
    {
        if (!$this->selectedStoreId) return;

        $user = Auth::user();
        TenantContext::enforceStoreAccess($this->selectedStoreId, $user);

        $this->drillResult = app(CustomerCareProductionReadinessService::class)->runRollbackDrill($this->selectedStoreId, $user);
    }

    public function render()
    {
        $user = Auth::user();
        $accessibleStores = CustomerCareOrganizationContext::getAccessibleStores($user)->get();

        if ($this->selectedStoreId && !$accessibleStores->contains('id', $this->selectedStoreId)) {
            $this->selectedStoreId = $accessibleStores->first()?->id;
        }

        $readinessRuns = collect();
        $freezeSnapshots = collect();

        if ($this->selectedStoreId) {
            $readinessRuns = SupportProductionReadinessRun::where('store_id', $this->selectedStoreId)
                ->latest()
                ->get();
            $freezeSnapshots = SupportProductionFreezeSnapshot::where('store_id', $this->selectedStoreId)
                ->with('run')
                ->latest()
                ->get();
        }

        return view('livewire.customer-care.production', [
            'accessibleStores' => $accessibleStores,
            'readinessRuns'     => $readinessRuns,
            'freezeSnapshots'   => $freezeSnapshots,
        ])->layout('layouts.app');
    }
}
