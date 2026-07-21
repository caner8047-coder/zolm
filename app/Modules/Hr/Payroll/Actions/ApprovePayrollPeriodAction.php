<?php

namespace App\Modules\Hr\Payroll\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\HrIntegrationOutboxService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Payroll\Models\HrPayrollPeriod;
use Illuminate\Support\Facades\DB;

class ApprovePayrollPeriodAction
{
    public function __construct(private HrAuditService $audit, private HrIntegrationOutboxService $outbox) {}

    public function execute(HrPayrollPeriod $period): HrPayrollPeriod
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.payroll.approve'), 403);
        abort_unless($period->legal_entity_id === app(TenantContext::class)->getId(), 404);
        abort_unless(
            $period->status === 'calculated'
            && $period->preflight_status === 'passed'
            && $period->records()->where('status', 'calculated')->exists(),
            422,
            'Hesabı ve ön kontrolleri tamamlanmamış dönem onaylanamaz.',
        );
        abort_if($period->calculated_by === auth()->id(), 422, 'Bordroyu hesaplayan kişi onaylayamaz.');

        return DB::transaction(function () use ($period) {
            $period->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => auth()->id()]);
            $period->records()->update(['status' => 'approved']);
            $this->outbox->enqueue('finance', 'payroll_period_approved', $period, 'hr-payroll-approved-'.$period->id, [
                'payroll_period_id' => $period->id,
                'record_count' => $period->records()->count(),
                'calculation_hash' => $period->calculation_hash,
                'approved_at' => $period->approved_at->toIso8601String(),
            ]);
            $this->audit->log('payroll_period_approved', $period, null, ['calculation_hash' => $period->calculation_hash]);

            return $period->fresh();
        });
    }
}
