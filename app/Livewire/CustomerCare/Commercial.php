<?php

namespace App\Livewire\CustomerCare;

use Livewire\Component;
use App\Models\MarketplaceStore;
use App\Models\SupportCommercialPlan;
use App\Models\SupportCommercialSubscription;
use App\Models\SupportEntitlementEvent;
use App\Services\Support\CustomerCareEntitlementService;
use App\Services\Support\CustomerCareOrganizationContext;
use App\Services\Support\TenantContext;

class Commercial extends Component
{
    public int $selectedStoreId = 0;
    public string $errorMessage = '';
    public string $successMessage = '';
    public string $exportMonth = '2026-07';

    // Plan geçiş
    public int $newPlanId = 0;

    protected $queryString = ['selectedStoreId'];

    public function mount(): void
    {
        if (!config('customer-care.commercial_center_enabled', false)) {
            abort(404);
        }

        $user = auth()->user();
        if (!$user || !in_array($user->role, ['admin', 'operator'], true)) {
            abort(403);
        }

        $stores = CustomerCareOrganizationContext::getAccessibleStores($user)->get();
        if ($stores->isEmpty()) {
            $this->selectedStoreId = 0;
        } else {
            if ($this->selectedStoreId && $stores->contains('id', $this->selectedStoreId)) {
                // keep it
            } else {
                $this->selectedStoreId = $stores->first()->id;
            }
        }
    }

    public function requestPlanChange(): void
    {
        $user = auth()->user();
        if (!$user) { abort(403); }

        try {
            $service = app(CustomerCareEntitlementService::class);
            $service->requestPlanChange($this->selectedStoreId, $this->newPlanId, $user);

            $this->successMessage = 'Plan geçişi başarıyla gerçekleştirildi.';
            $this->errorMessage = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->successMessage = '';
        }
    }

    public function exportBillingData(): mixed
    {
        $user = auth()->user();
        if (!$user) { abort(403); }

        try {
            TenantContext::enforceStoreAccess($this->selectedStoreId, $user);

            $service = app(CustomerCareEntitlementService::class);
            $csv = $service->generateBillingExport($this->selectedStoreId, $this->exportMonth, $user);

            $filename = "billing-export-{$this->selectedStoreId}-{$this->exportMonth}.csv";

            // XML/Sanitization kontrolü zaten servis içinde yapıldı
            // Livewire file download döner
            $this->successMessage = 'Fatura detay dosyası başarıyla dışa aktarıldı.';
            $this->errorMessage = '';

            // Livewire ile string stream olarak indir
            return response()->streamDownload(function() use ($csv) {
                echo $csv;
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->successMessage = '';
        }
    }

    public function render()
    {
        $user = auth()->user();
        $subscription = null;
        $events = collect();

        if ($this->selectedStoreId && $user) {
            try {
                TenantContext::enforceStoreAccess($this->selectedStoreId, $user);

                $subscription = SupportCommercialSubscription::where('store_id', $this->selectedStoreId)
                    ->where('status', 'active')
                    ->with('plan')
                    ->first();

                $events = SupportEntitlementEvent::where('store_id', $this->selectedStoreId)
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get();
            } catch (\Throwable) {
                // yetki dışı durum - listeleri boşaltıp selectedStoreId'yi sıfırla
                $this->selectedStoreId = 0;
                $subscription = null;
                $events = collect();
            }
        }

        return view('livewire.customer-care.commercial', [
            'stores'       => $user ? CustomerCareOrganizationContext::getAccessibleStores($user)->get() : collect(),
            'plans'        => SupportCommercialPlan::all(),
            'subscription' => $subscription,
            'events'       => $events,
        ])->layout('layouts.app');
    }
}
