<?php

namespace App\Livewire\CustomerCare;

use Livewire\Component;
use App\Models\SupportExperiment;
use App\Services\Support\CustomerCareExperimentService;
use App\Livewire\CustomerCare\Concerns\ResolvesAccessibleStores;

class Experiments extends Component
{
    use ResolvesAccessibleStores;

    public int $selectedStoreId = 0;
    public string $errorMessage = '';
    public string $successMessage = '';

    protected $queryString = ['selectedStoreId'];

    public function mount(): void
    {
        if (!config('customer-care.experiments_enabled', false)) {
            abort(404);
        }

        $user = auth()->user();
        if (!$user || !in_array($user->role, ['admin', 'operator'], true)) {
            abort(403);
        }

        $this->resolveAccessibleStores();
    }

    public function runExperiment(int $experimentId, bool $dryRun = true): void
    {
        $this->enforceSelectedStoreAccess();
        $user = auth()->user();
        if (!$user) { abort(403); }

        try {
            $service = app(CustomerCareExperimentService::class);
            $results = $service->runExperiment($this->selectedStoreId, $experimentId, $user, $dryRun);
            $count   = count($results);
            $this->successMessage = "{$count} varyant işlendi" . ($dryRun ? ' (dry-run).' : '.');
            $this->errorMessage   = '';
        } catch (\Throwable $e) {
            $this->errorMessage   = $e->getMessage();
            $this->successMessage = '';
        }
    }

    public function render()
    {
        $stores = $this->resolveAccessibleStores();
        $experiments = collect();

        if ($this->selectedStoreId) {
            $experiments = SupportExperiment::where('store_id', $this->selectedStoreId)
                ->orderByDesc('created_at')
                ->limit(30)
                ->get();
        }

        return view('livewire.customer-care.experiments', [
            'experiments' => $experiments,
            'stores'      => $stores,
        ])->layout('layouts.app');
    }
}
