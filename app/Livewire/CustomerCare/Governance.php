<?php

namespace App\Livewire\CustomerCare;

use Livewire\Component;
use App\Models\SupportRoleAssignment;
use App\Models\SupportApprovalRequest;
use App\Livewire\CustomerCare\Concerns\ResolvesAccessibleStores;
use App\Services\Support\CustomerCareOrganizationContext;
use Illuminate\Support\Facades\DB;

class Governance extends Component
{
    use ResolvesAccessibleStores;

    public int $selectedStoreId = 0;
    public string $errorMessage = '';
    public string $successMessage = '';

    // Assigning new role variables
    public ?int $newUserId = null;
    public string $newRole = 'agent';

    protected $queryString = ['selectedStoreId'];

    public function mount()
    {
        // Enforce Feature Flag
        if (!config('customer-care.governance_enabled', false)) {
            abort(404);
        }

        $user = auth()->user();
        if (!$user || !in_array($user->role, ['admin', 'operator'], true)) {
            abort(403);
        }

        $this->resolveAccessibleStores();
    }

    public function assignRole()
    {
        $this->enforceSelectedStoreAccess();
        try {
            app(\App\Services\Support\Security\SupportRbacService::class)
                ->enforcePermission(auth()->user(), $this->selectedStoreId, 'manage_roles');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->errorMessage = $e->getMessage();
            return;
        }

        $this->validate([
            'newUserId' => 'required|exists:users,id',
            'newRole' => 'required|in:owner,admin,supervisor,agent,analyst,auditor,knowledge_manager,automation_manager,read_only',
        ]);

        $assignableUserIds = CustomerCareOrganizationContext::getAssignableUsersForStore(
            $this->selectedStoreId,
            auth()->user()
        )->pluck('id');
        if (!$assignableUserIds->contains((int) $this->newUserId)) {
            $this->errorMessage = 'Seçilen kullanıcı bu mağazanın organizasyonuna ait değil.';
            return;
        }

        DB::transaction(function (): void {
            $assignment = SupportRoleAssignment::where('store_id', $this->selectedStoreId)
                ->where('user_id', $this->newUserId)
                ->lockForUpdate()
                ->first();
            $previousRole = $assignment?->role;

            SupportRoleAssignment::updateOrCreate([
                'store_id' => $this->selectedStoreId,
                'user_id' => $this->newUserId,
            ], [
                'role' => $this->newRole,
            ]);

            \App\Models\SupportAgentAction::create([
                'conversation_id' => null,
                'user_id' => auth()->id(),
                'action' => 'support_role_assigned',
                'details_json' => [
                    'store_id' => $this->selectedStoreId,
                    'target_user_id' => $this->newUserId,
                    'previous_role' => $previousRole,
                    'new_role' => $this->newRole,
                ],
            ]);
        });

        $this->successMessage = 'Rol ataması başarıyla yapıldı.';
        $this->newUserId = null;
    }

    public function approveRequest(int $requestId)
    {
        $this->enforceSelectedStoreAccess();
        try {
            app(\App\Services\Support\Security\SupportRbacService::class)
                ->enforcePermission(auth()->user(), $this->selectedStoreId, 'approve_risk_action');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->errorMessage = $e->getMessage();
            return;
        }

        $result = DB::transaction(function () use ($requestId): string {
            $request = SupportApprovalRequest::where('store_id', $this->selectedStoreId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->find($requestId);
            if (!$request) {
                return 'missing';
            }

            if ((int) $request->requester_id === (int) auth()->id()) {
                return 'self_approval';
            }

            $request->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            return 'approved';
        });

        if ($result === 'missing') {
            $this->errorMessage = 'Onay talebi bulunamadı veya daha önce sonuçlandırıldı.';
            return;
        }
        if ($result === 'self_approval') {
            $this->errorMessage = 'Kendinize ait onay taleplerini onaylayamazsınız (Self-Approval Blocked).';
            return;
        }

        $this->successMessage = 'İşlem başarıyla onaylandı.';
    }

    public function rejectRequest(int $requestId, string $reason = '')
    {
        $this->enforceSelectedStoreAccess();
        try {
            app(\App\Services\Support\Security\SupportRbacService::class)
                ->enforcePermission(auth()->user(), $this->selectedStoreId, 'reject_risk_action');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->errorMessage = $e->getMessage();
            return;
        }

        $updated = DB::transaction(function () use ($requestId, $reason): bool {
            $request = SupportApprovalRequest::where('store_id', $this->selectedStoreId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->find($requestId);
            if (!$request) {
                return false;
            }

            $request->update([
                'status' => 'rejected',
                'reason' => $reason ?: 'Reddedildi.',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            return true;
        });

        if (!$updated) {
            $this->errorMessage = 'Onay talebi bulunamadı veya daha önce sonuçlandırıldı.';
            return;
        }

        $this->successMessage = 'İşlem reddedildi.';
    }

    public function render()
    {
        $stores = $this->resolveAccessibleStores();
        $users = $this->selectedStoreId
            ? CustomerCareOrganizationContext::getAssignableUsersForStore($this->selectedStoreId, auth()->user())
            : collect();

        $roleAssignments = SupportRoleAssignment::where('store_id', $this->selectedStoreId)
            ->with('user')
            ->get();

        $pendingApprovals = SupportApprovalRequest::where('store_id', $this->selectedStoreId)
            ->where('status', 'pending')
            ->with('requester')
            ->get();

        $decisionHistory = SupportApprovalRequest::where('store_id', $this->selectedStoreId)
            ->whereIn('status', ['approved', 'rejected'])
            ->with(['requester', 'approver'])
            ->latest()
            ->limit(10)
            ->get();

        return view('livewire.customer-care.governance', [
            'stores' => $stores,
            'users' => $users,
            'roleAssignments' => $roleAssignments,
            'pendingApprovals' => $pendingApprovals,
            'decisionHistory' => $decisionHistory,
        ])->layout('layouts.app');
    }
}
