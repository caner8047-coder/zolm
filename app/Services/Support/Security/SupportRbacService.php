<?php

namespace App\Services\Support\Security;

use App\Models\User;
use App\Models\SupportRoleAssignment;
use App\Models\SupportApprovalRequest;
use App\Models\MarketplaceStore;
use App\Services\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupportRbacService
{
    private const ROLE_PERMISSIONS = [
        'owner' => [
            'inbox_view', 'agent_reply_send', 'ai_draft_generate', 'toggle_automation',
            'manage_public_reply', 'knowledge_publish', 'manage_webhooks', 'secret_rotate',
            'approve_quality_review', 'analytics_export', 'run_compliance', 'force_circuit_breaker',
            'manage_roles', 'approve_risk_action', 'reject_risk_action',
        ],
        'admin' => [
            'inbox_view', 'agent_reply_send', 'ai_draft_generate', 'toggle_automation',
            'manage_public_reply', 'knowledge_publish', 'manage_webhooks', 'secret_rotate',
            'approve_quality_review', 'analytics_export', 'run_compliance', 'force_circuit_breaker',
            'manage_roles', 'approve_risk_action', 'reject_risk_action',
        ],
        'supervisor' => [
            'inbox_view', 'agent_reply_send', 'ai_draft_generate', 'toggle_automation',
            'knowledge_publish', 'approve_quality_review', 'analytics_export',
            'approve_risk_action', 'reject_risk_action',
        ],
        'agent' => [
            'inbox_view', 'agent_reply_send', 'ai_draft_generate',
        ],
        'analyst' => [
            'inbox_view', 'analytics_export',
        ],
        'auditor' => [
            'inbox_view', 'approve_quality_review',
        ],
        'knowledge_manager' => [
            'inbox_view', 'ai_draft_generate', 'knowledge_publish',
        ],
        'automation_manager' => [
            'inbox_view', 'ai_draft_generate', 'toggle_automation',
            'approve_quality_review', 'force_circuit_breaker',
        ],
        'read_only' => [
            'inbox_view',
        ],
    ];

    /**
     * Kullanıcının belirtilen mağazada yetkisi olup olmadığını kontrol eder.
     */
    public function hasPermission(User $user, int $storeId, string $permission): bool
    {
        if (!TenantContext::validateStoreAccess($storeId, $user)) {
            return false;
        }

        // Entegrasyon servis hesapları (service account) riskli onay/red işlemleri gerçekleştiremez
        if (in_array($permission, ['approve_risk_action', 'reject_risk_action'], true)) {
            if (\App\Services\Support\CustomerCareOrganizationContext::isServiceAccount($user)) {
                return false;
            }
        }

        // Mağazanın gerçek sahibi, merkezi kullanıcı rolünden bağımsız olarak o
        // mağaza kapsamında owner yetkilerine sahiptir.
        $isStoreOwner = MarketplaceStore::whereKey($storeId)
            ->where('user_id', $user->id)
            ->exists();
        if ($isStoreOwner) {
            return in_array($permission, self::ROLE_PERMISSIONS['owner'], true);
        }

        $enabled = config('customer-care.governance_enabled', false);

        if (!$enabled) {
            if ($user->role === 'admin') {
                return true;
            }
            // Varsayılan kısıtlı yetkiler
            return in_array($permission, ['inbox_view', 'agent_reply_send', 'ai_draft_generate'], true);
        }

        // Governance aktif ise rol sorgusu yap
        $assignment = SupportRoleAssignment::where('user_id', $user->id)
            ->where('store_id', $storeId)
            ->first();

        $role = $assignment ? $assignment->role : ($user->role === 'admin' ? 'admin' : 'agent');

        $allowedPermissions = self::ROLE_PERMISSIONS[$role] ?? [];

        return in_array($permission, $allowedPermissions, true);
    }

    /**
     * Servis seviyesinde yetki doğrulaması yapar, yetki yoksa hata fırlatır.
     */
    public function enforcePermission(User $user, int $storeId, string $permission): void
    {
        if (!$this->hasPermission($user, $storeId, $permission)) {
            Log::warning("RBAC yetkisiz erişim denemesi", [
                'user_id' => $user->id,
                'store_id' => $storeId,
                'permission' => $permission
            ]);
            throw new \Illuminate\Auth\Access\AuthorizationException("Bu işlem için yetkiniz bulunmamaktadır.");
        }
    }

    /**
     * Riskli işlemlerde iki aşamalı onay doğrulaması yapar (self-approval engelli).
     */
    public function enforceApproval(User $user, int $storeId, string $action, array $details = []): ?SupportApprovalRequest
    {
        TenantContext::enforceStoreAccess($storeId, $user);

        $enabled = config('customer-care.governance_enabled', false);
        if (!$enabled) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Riskli işlem, yönetişim ve iki aşamalı onay merkezi kapalıyken çalıştırılamaz.'
            );
        }

        $normalizedDetails = $this->normalizeApprovalDetails($details);
        $approvalMaxAgeMinutes = max(1, (int) config('customer-care.approval_max_age_minutes', 1440));
        $approvalRequest = DB::transaction(function () use ($user, $storeId, $action, $normalizedDetails, $approvalMaxAgeMinutes): ?SupportApprovalRequest {
            // Aynı aksiyona ait fakat farklı varlık/detay için verilmiş bir onay
            // kullanılamaz. Satır kilidi aynı onayın eşzamanlı iki kez tüketilmesini engeller.
            $approvedRequest = SupportApprovalRequest::where('store_id', $storeId)
                ->where('action_type', $action)
                ->where('status', 'approved')
                ->where('requester_id', $user->id)
                ->where('approved_by', '!=', $user->id)
                ->whereNotNull('approved_at')
                ->where('approved_at', '>=', now()->subMinutes($approvalMaxAgeMinutes))
                ->lockForUpdate()
                ->latest()
                ->get()
                ->first(fn (SupportApprovalRequest $request): bool =>
                    $this->normalizeApprovalDetails($request->details_json ?? []) === $normalizedDetails
                );

            if ($approvedRequest) {
                $approvedRequest->update([
                    'status' => 'consumed',
                    'consumed_at' => now(),
                    'consumed_by' => $user->id,
                ]);

                return $approvedRequest->fresh();
            }

            $pendingExists = SupportApprovalRequest::where('store_id', $storeId)
                ->where('action_type', $action)
                ->where('requester_id', $user->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->get()
                ->contains(fn (SupportApprovalRequest $request): bool =>
                    $this->normalizeApprovalDetails($request->details_json ?? []) === $normalizedDetails
                );

            if (!$pendingExists) {
                SupportApprovalRequest::create([
                    'store_id' => $storeId,
                    'action_type' => $action,
                    'requester_id' => $user->id,
                    'status' => 'pending',
                    'details_json' => $normalizedDetails,
                ]);
            }

            return null;
        });

        if ($approvalRequest) {
            return $approvalRequest;
        }

        throw new \App\Exceptions\ApprovalRequiredException("Bu riskli işlem için onay gerekiyor. Onay talebi oluşturuldu.");
    }

    private function normalizeApprovalDetails(array $details): array
    {
        foreach ($details as &$value) {
            if (is_array($value)) {
                $value = $this->normalizeApprovalDetails($value);
            }
        }
        unset($value);

        if (!array_is_list($details)) {
            ksort($details);
        }

        return $details;
    }
}
