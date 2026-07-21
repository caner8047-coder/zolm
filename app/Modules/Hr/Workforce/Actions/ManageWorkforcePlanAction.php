<?php

namespace App\Modules\Hr\Workforce\Actions;

use App\Modules\Hr\Compensation\Models\HrSalaryRecord;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\HrIntegrationOutboxService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Organization\Models\HrDepartment;
use App\Modules\Hr\Organization\Models\HrPosition;
use App\Modules\Hr\Personnel\Models\HrEmploymentRecord;
use App\Modules\Hr\Workforce\Models\HrWorkforcePlan;
use App\Modules\Hr\Workforce\Models\HrWorkforcePlanLine;
use Illuminate\Support\Facades\DB;

class ManageWorkforcePlanAction
{
    public function __construct(private HrAuditService $audit, private HrIntegrationOutboxService $outbox) {}

    public function create(array $data): HrWorkforcePlan
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.workforce.manage'), 403);

        $validated = validator($data, [
            'name' => 'required|string|max:160',
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after_or_equal:starts_on',
            'budget' => 'required|numeric|min:0',
            'currency' => 'required|in:TRY,EUR,USD,GBP',
        ])->validate();

        $plan = HrWorkforcePlan::create([
            'legal_entity_id' => app(TenantContext::class)->getId(),
            'name' => trim($validated['name']),
            'starts_on' => $validated['starts_on'],
            'ends_on' => $validated['ends_on'],
            'budget_encrypted' => (string) $validated['budget'],
            'currency' => $validated['currency'],
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        $this->audit->log('workforce_plan_created', $plan, null, ['budget' => '[MASKED]']);

        return $plan;
    }

    public function addLine(
        HrWorkforcePlan $plan,
        HrDepartment $department,
        HrPosition $position,
        float $fte,
        float $cost,
        ?string $notes = null,
    ): HrWorkforcePlanLine {
        $tenantId = app(TenantContext::class)->getId();

        abort_unless(auth()->user()?->hasHrPermission('hr.workforce.manage'), 403);
        abort_unless(
            $plan->legal_entity_id === $tenantId
            && $department->legal_entity_id === $tenantId
            && $position->legal_entity_id === $tenantId,
            404,
        );
        abort_unless($plan->status === 'draft', 422, 'Yalnız taslak plana satır eklenebilir.');
        abort_if($fte <= 0 || $cost < 0 || $position->department_id !== $department->id, 422, 'Kadro satırı geçersiz.');

        $line = HrWorkforcePlanLine::updateOrCreate([
            'workforce_plan_id' => $plan->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
        ], [
            'legal_entity_id' => $tenantId,
            'planned_fte' => $fte,
            'planned_monthly_cost_encrypted' => (string) $cost,
            'notes' => $notes,
        ]);

        $this->audit->log('workforce_plan_line_saved', $line, null, [
            'planned_fte' => $fte,
            'planned_cost' => '[MASKED]',
        ]);

        return $line;
    }

    public function submit(HrWorkforcePlan $plan): HrWorkforcePlan
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.workforce.manage'), 403);
        abort_unless(
            $plan->legal_entity_id === app(TenantContext::class)->getId()
            && $plan->status === 'draft'
            && $plan->lines()->exists(),
            422,
        );

        $plan->update(['status' => 'pending_approval']);
        $this->audit->log('workforce_plan_submitted', $plan);

        return $plan->fresh();
    }

    public function approve(HrWorkforcePlan $plan): HrWorkforcePlan
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.workforce.approve'), 403);
        abort_unless(
            $plan->legal_entity_id === app(TenantContext::class)->getId()
            && $plan->status === 'pending_approval',
            422,
        );
        abort_if($plan->created_by === auth()->id(), 422, 'Kadro planını hazırlayan kişi onaylayamaz.');

        return DB::transaction(function () use ($plan) {
            $snapshot = [];

            foreach ($plan->lines()->lockForUpdate()->get() as $line) {
                $employeeIds = HrEmploymentRecord::withoutGlobalScope('tenant')
                    ->where('legal_entity_id', $plan->legal_entity_id)
                    ->current()
                    ->where('department_id', $line->department_id)
                    ->where('position_id', $line->position_id)
                    ->pluck('employee_id');

                $actualFte = $employeeIds->count();
                $actualCost = HrSalaryRecord::withoutGlobalScope('tenant')
                    ->where('legal_entity_id', $plan->legal_entity_id)
                    ->whereIn('employee_id', $employeeIds)
                    ->whereIn('status', ['approved', 'superseded'])
                    ->where('currency', $plan->currency)
                    ->whereDate('effective_from', '<=', today())
                    ->orderByDesc('effective_from')
                    ->orderByDesc('version')
                    ->get()
                    ->unique('employee_id')
                    ->sum(fn (HrSalaryRecord $record) => $record->grossSalary());

                $line->update([
                    'actual_fte_snapshot' => $actualFte,
                    'actual_monthly_cost_encrypted' => (string) $actualCost,
                ]);

                $snapshot[] = [
                    'line_id' => $line->id,
                    'actual_fte' => $actualFte,
                    'actual_cost' => $actualCost,
                ];
            }

            $sourceHash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));

            $plan->update([
                'status' => 'approved',
                'source_hash' => $sourceHash,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
            $this->audit->log('workforce_plan_approved', $plan, null, ['source_hash' => $sourceHash]);
            $this->outbox->enqueue('production', 'workforce_plan_approved', $plan, 'hr-workforce-plan-approved-'.$plan->id, [
                'workforce_plan_id' => $plan->id,
                'name' => $plan->name,
                'starts_on' => $plan->starts_on->toDateString(),
                'ends_on' => $plan->ends_on->toDateString(),
                'source_hash' => $sourceHash,
                'lines' => $plan->lines()->get()->map(fn (HrWorkforcePlanLine $line) => [
                    'department_id' => $line->department_id,
                    'position_id' => $line->position_id,
                    'planned_fte' => (string) $line->planned_fte,
                    'actual_fte' => (string) $line->actual_fte_snapshot,
                ])->all(),
            ]);

            return $plan->fresh('lines');
        });
    }
}
