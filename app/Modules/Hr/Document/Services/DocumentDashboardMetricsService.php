<?php

namespace App\Modules\Hr\Document\Services;

use App\Modules\Hr\Core\Services\HrCacheKey;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Enums\DocumentStatus;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use App\Modules\Hr\Document\Models\HrDocumentRequest;

class DocumentDashboardMetricsService
{
    public function getMetrics(): array
    {
        // Tenant bazlı kısa süreli cache. Belge event'leri (InvalidateDocumentMetricsCache)
        // bu cache'i geçersiz kılar; böylece dashboard güncel kalır ve tekrarlayan sorgular azalır.
        return HrCacheKey::remember('document', 'metrics', function () {
            $tenantId = app(TenantContext::class)->getId();

            return [
                'missing_mandatory' => HrEmployeeDocument::withoutGlobalScope('tenant')
                    ->where('legal_entity_id', $tenantId)
                    ->where('status', DocumentStatus::Requested)
                    ->count(),
                'expiring_soon' => HrEmployeeDocument::withoutGlobalScope('tenant')
                    ->where('legal_entity_id', $tenantId)
                    ->where('status', DocumentStatus::Active)
                    ->whereNotNull('expiry_date')
                    ->where('expiry_date', '<=', now()->addDays(30))
                    ->where('expiry_date', '>=', now())
                    ->count(),
                'expired' => HrEmployeeDocument::withoutGlobalScope('tenant')
                    ->where('legal_entity_id', $tenantId)
                    ->where('status', DocumentStatus::Expired)
                    ->count(),
                'pending_verification' => HrEmployeeDocument::withoutGlobalScope('tenant')
                    ->where('legal_entity_id', $tenantId)
                    ->where('verification_status', 'pending')
                    ->count(),
                'overdue_requests' => HrDocumentRequest::withoutGlobalScope('tenant')
                    ->where('legal_entity_id', $tenantId)
                    ->where('status', 'overdue')
                    ->count(),
            ];
        });
    }
}
