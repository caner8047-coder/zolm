<?php

namespace App\Livewire\CustomerCare;

use Livewire\Component;
use App\Models\SupportSecurityAuditRun;
use App\Services\Support\CustomerCareSecurityService;
use App\Livewire\CustomerCare\Concerns\ResolvesAccessibleStores;

class Security extends Component
{
    use ResolvesAccessibleStores;

    public int $selectedStoreId = 0;
    public bool $isDryRun = true;
    public string $errorMessage = '';
    public string $successMessage = '';
    public string $evidencePack = '';

    protected $queryString = ['selectedStoreId'];

    public function mount(): void
    {
        if (!config('customer-care.security_center_enabled', false)) {
            abort(404);
        }

        $user = auth()->user();
        if (!$user || !in_array($user->role, ['admin', 'operator'], true)) {
            abort(403);
        }

        $this->resolveAccessibleStores();
    }

    public function runAudit(): void
    {
        $this->enforceSelectedStoreAccess();
        $user = auth()->user();
        if (!$user) { abort(403); }

        try {
            $service = app(CustomerCareSecurityService::class);
            $run     = $service->runAudit($this->selectedStoreId, $this->isDryRun, $user);

            $severity = strtoupper($run->overall_severity ?? 'unknown');
            $this->successMessage = "Denetim tamamlandı — Seviye: {$severity}, Bulgular: {$run->findings_count}";
            $this->errorMessage   = '';
        } catch (\Throwable $e) {
            $this->errorMessage   = $e->getMessage();
            $this->successMessage = '';
        }
    }

    public function generateEvidencePack(): void
    {
        $this->enforceSelectedStoreAccess();
        $user = auth()->user();
        if (!$user) { abort(403); }

        try {
            $service            = app(CustomerCareSecurityService::class);
            $this->evidencePack = $service->generateEvidencePack($this->selectedStoreId, $user);
            $this->successMessage = 'Kanıt paketi oluşturuldu.';
            $this->errorMessage   = '';
        } catch (\Throwable $e) {
            $this->errorMessage   = $e->getMessage();
            $this->successMessage = '';
        }
    }

    public function render()
    {
        $stores = $this->resolveAccessibleStores();
        $runs = collect();

        if ($this->selectedStoreId) {
            $runs = SupportSecurityAuditRun::where('store_id', $this->selectedStoreId)
                ->with(['findings', 'evidenceItems'])
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();
        }

        return view('livewire.customer-care.security', [
            'runs'   => $runs,
            'stores' => $stores,
        ])->layout('layouts.app');
    }
}
