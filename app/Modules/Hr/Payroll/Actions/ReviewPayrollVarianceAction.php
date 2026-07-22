<?php

namespace App\Modules\Hr\Payroll\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Payroll\Models\HrPayrollPeriod;

class ReviewPayrollVarianceAction
{
    public function __construct(private HrAuditService $audit) {}

    public function execute(HrPayrollPeriod $period, string $note): HrPayrollPeriod
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.payroll.approve'), 403);
        abort_unless($period->legal_entity_id === app(TenantContext::class)->getId(), 404);
        abort_unless(in_array($period->variance_status, ['warning', 'critical'], true), 422, 'İncelenecek dönem farkı bulunmuyor.');
        abort_if($period->calculated_by === auth()->id(), 422, 'Bordroyu hesaplayan kişi dönem farklarını onaylayamaz.');
        abort_if(mb_strlen(trim($note)) < 10, 422, 'Dönem farkı inceleme notu en az 10 karakter olmalı.');

        $period->update([
            'variance_reviewed_by' => auth()->id(),
            'variance_reviewed_at' => now(),
            'variance_review_note' => trim($note),
        ]);
        $this->audit->log('payroll_variance_reviewed', $period, null, [
            'variance_status' => $period->variance_status,
            'finding_count' => count($period->variance_findings ?? []),
        ]);

        return $period->fresh();
    }
}
