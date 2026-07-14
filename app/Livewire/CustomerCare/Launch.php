<?php

namespace App\Livewire\CustomerCare;

use Livewire\Component;
use App\Models\SupportLaunchPlan;
use App\Models\SupportLaunchEvent;
use App\Livewire\CustomerCare\Concerns\ResolvesAccessibleStores;
use App\Services\Support\CustomerCareLaunchService;
use App\Services\Support\Security\SupportRbacService;

class Launch extends Component
{
    use ResolvesAccessibleStores;

    public int $selectedStoreId = 0;
    public string $errorMessage = '';
    public string $successMessage = '';

    // Create Plan Fields
    public array $targetChannels = [];
    public string $initialMode = 'manual';
    public int $canaryPercentage = 100;
    public ?int $conversationLimit = null;
    public string $allowedCategoriesRaw = '';
    public string $rollbackRulesRaw = '';

    protected $queryString = ['selectedStoreId'];

    public function mount()
    {
        if (!config('customer-care.launch_center_enabled', false)) {
            abort(404);
        }

        $user = auth()->user();
        if (!$user || !in_array($user->role, ['admin', 'operator'], true)) {
            abort(403);
        }

        $this->resolveAccessibleStores();
    }

    public function createPlan()
    {
        $this->validate([
            'targetChannels' => 'required|array|min:1',
            'initialMode' => 'required|in:manual,copilot,automatic',
            'canaryPercentage' => 'required|integer|min:1|max:100',
            'conversationLimit' => 'nullable|integer|min:1',
        ]);

        $rbac = app(SupportRbacService::class);
        $user = auth()->user();

        try {
            $rbac->enforcePermission($user, $this->selectedStoreId, 'force_circuit_breaker');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            return;
        }

        $categories = array_filter(array_map('trim', explode(',', $this->allowedCategoriesRaw)));
        $rules = array_filter(array_map('trim', explode(',', $this->rollbackRulesRaw)));

        $service = app(CustomerCareLaunchService::class);
        try {
            $service->createPlan($this->selectedStoreId, [
                'target_channels' => $this->targetChannels,
                'initial_mode' => $this->initialMode,
                'canary_percentage' => $this->canaryPercentage,
                'conversation_limit' => $this->conversationLimit,
                'allowed_categories' => $categories,
                'rollback_rules' => $rules,
            ], $user);

            $this->successMessage = 'Pilot lansman planı başarıyla oluşturuldu.';
            $this->resetPlanFields();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function transitionPlan(int $planId, string $status)
    {
        $plan = SupportLaunchPlan::where('store_id', $this->selectedStoreId)->find($planId);
        if (!$plan) {
            $this->errorMessage = 'Lansman planı bulunamadı.';
            return;
        }

        $user = auth()->user();

        // Enforce no self-approval for approval action
        if (in_array($status, ['approved', 'canary', 'completed'], true)) {
            // Check if user is requester of approval
            // Actually, transitionTo will call enforceApproval which automatically handles self-approval check!
        }

        $service = app(CustomerCareLaunchService::class);
        try {
            $service->transitionTo($plan, $status, $user);
            $this->successMessage = "Lansman planı durumu güncellendi: {$status}";
        } catch (\App\Exceptions\ApprovalRequiredException $e) {
            $this->successMessage = $e->getMessage() . ' Onaylandıktan sonra işleme devam edebilirsiniz.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function triggerRollback(int $planId)
    {
        $plan = SupportLaunchPlan::where('store_id', $this->selectedStoreId)->find($planId);
        if (!$plan) {
            $this->errorMessage = 'Lansman planı bulunamadı.';
            return;
        }

        $user = auth()->user();
        $rbac = app(SupportRbacService::class);

        try {
            $rbac->enforcePermission($user, $this->selectedStoreId, 'force_circuit_breaker');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            return;
        }

        $service = app(CustomerCareLaunchService::class);
        try {
            $service->rollback($plan, $user);
            $this->successMessage = 'Emergency Rollback başarıyla tetiklendi. Mağaza AI modları kapatıldı, pending AI kuyruğu temizlendi.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    protected function resetPlanFields()
    {
        $this->targetChannels = [];
        $this->initialMode = 'manual';
        $this->canaryPercentage = 100;
        $this->conversationLimit = null;
        $this->allowedCategoriesRaw = '';
        $this->rollbackRulesRaw = '';
    }

    public function render()
    {
        $stores = $this->resolveAccessibleStores();

        $plans = SupportLaunchPlan::where('store_id', $this->selectedStoreId)
            ->with(['steps', 'approver'])
            ->latest()
            ->get();

        $events = SupportLaunchEvent::where('store_id', $this->selectedStoreId)
            ->latest()
            ->limit(20)
            ->get();

        // Get checklist output
        $checklist = $this->selectedStoreId
            ? app(CustomerCareLaunchService::class)->checkChecklist($this->selectedStoreId)
            : ['allowed' => false, 'checks' => []];

        return view('livewire.customer-care.launch', [
            'stores' => $stores,
            'plans' => $plans,
            'events' => $events,
            'checklist' => $checklist,
        ])->layout('layouts.app');
    }
}
