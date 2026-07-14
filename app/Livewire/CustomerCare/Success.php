<?php

namespace App\Livewire\CustomerCare;

use Livewire\Component;
use App\Models\SupportSuccessSnapshot;
use App\Models\SupportSuccessTask;
use App\Services\Support\CustomerCareSuccessService;
use App\Services\Support\TenantContext;
use App\Livewire\CustomerCare\Concerns\ResolvesAccessibleStores;

class Success extends Component
{
    use ResolvesAccessibleStores;

    public int $selectedStoreId = 0;
    public string $errorMessage = '';
    public string $successMessage = '';
    public string $newNoteBody = '';

    protected $queryString = ['selectedStoreId'];

    public function mount(): void
    {
        if (!config('customer-care.success_center_enabled', false)) {
            abort(404);
        }

        $user = auth()->user();
        if (!$user || !in_array($user->role, ['admin', 'operator'], true)) {
            abort(403);
        }

        $this->resolveAccessibleStores();
    }

    public function refreshSnapshot(): void
    {
        $this->enforceSelectedStoreAccess();
        $user = auth()->user();
        if (!$user) { abort(403); }

        try {
            $service  = app(CustomerCareSuccessService::class);
            $snapshot = $service->computeSnapshot($this->selectedStoreId, $user);

            $label = $snapshot->health_label ?? 'unknown';
            $score = $snapshot->health_score ?? '?';
            $this->successMessage = "Snapshot güncellendi — Skor: {$score}, Durum: {$label}";
            $this->errorMessage   = '';
        } catch (\Throwable $e) {
            $this->errorMessage   = $e->getMessage();
            $this->successMessage = '';
        }
    }

    public function resolveTask(int $taskId): void
    {
        $user = auth()->user();
        if (!$user) { abort(403); }

        try {
            $service = app(CustomerCareSuccessService::class);
            $service->resolveTask($taskId, $user);
            $this->successMessage = 'Görev kapatıldı.';
            $this->errorMessage   = '';
        } catch (\Throwable $e) {
            $this->errorMessage   = $e->getMessage();
            $this->successMessage = '';
        }
    }

    public function addNote(): void
    {
        $this->enforceSelectedStoreAccess();
        $user = auth()->user();
        if (!$user || empty(trim($this->newNoteBody))) { return; }

        try {
            $service = app(CustomerCareSuccessService::class);
            $service->addNote($this->selectedStoreId, $user, $this->newNoteBody);
            $this->newNoteBody    = '';
            $this->successMessage = 'Not eklendi (PII maskelendi ve şifrelendi).';
            $this->errorMessage   = '';
        } catch (\Throwable $e) {
            $this->errorMessage   = $e->getMessage();
            $this->successMessage = '';
        }
    }

    public function render()
    {
        $stores   = $this->resolveAccessibleStores();
        $user     = auth()->user();
        $snapshot = null;
        $tasks    = collect();
        $portfolio = [];

        if ($this->selectedStoreId && $user) {
            try {
                $service  = app(CustomerCareSuccessService::class);
                $snapshot = $service->getLatestSnapshot($this->selectedStoreId, $user);
                $tasks    = SupportSuccessTask::where('store_id', $this->selectedStoreId)
                    ->where('status', '!=', 'resolved')
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get();
                $portfolio = $service->getPortfolioSnapshots($user);
            } catch (\Throwable) {
                // erişim hatası — boş görünüm
            }
        }

        return view('livewire.customer-care.success', [
            'snapshot'  => $snapshot,
            'tasks'     => $tasks,
            'portfolio' => $portfolio,
            'stores'    => $stores,
        ])->layout('layouts.app');
    }
}
