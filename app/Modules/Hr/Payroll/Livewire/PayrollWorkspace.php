<?php

namespace App\Modules\Hr\Payroll\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Payroll\Actions\ApprovePayrollPeriodAction;
use App\Modules\Hr\Payroll\Actions\CalculatePayrollPeriodAction;
use App\Modules\Hr\Payroll\Actions\ManagePayrollAdjustmentAction;
use App\Modules\Hr\Payroll\Actions\PreparePayrollPeriodAction;
use App\Modules\Hr\Payroll\Actions\ReviewPayrollVarianceAction;
use App\Modules\Hr\Payroll\Models\HrPayrollAdjustment;
use App\Modules\Hr\Payroll\Models\HrPayrollPeriod;
use App\Modules\Hr\Payroll\Services\PayrollItemCatalog;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Timesheet\Enums\TimesheetPeriodStatus;
use App\Modules\Hr\Timesheet\Models\HrTimesheetPeriod;
use Livewire\Component;

class PayrollWorkspace extends Component
{
    public ?int $timesheetPeriodId = null;
    public ?int $selectedPeriodId = null;
    public ?int $adjustmentEmployeeId = null;
    public string $adjustmentCode = '';
    public string $adjustmentName = '';
    public string $adjustmentType = 'earning';
    public string $adjustmentAmount = '';
    public string $adjustmentReason = '';
    public bool $socialSecurityExempt = false;
    public bool $incomeTaxExempt = false;
    public bool $preTaxDeduction = false;
    public string $varianceReviewNote = '';

    public function prepare(PreparePayrollPeriodAction $action): void
    {
        $this->validate(['timesheetPeriodId' => 'required|integer']);
        $period = HrTimesheetPeriod::withoutGlobalScope('tenant')
            ->where('legal_entity_id', app(TenantContext::class)->getId())
            ->findOrFail($this->timesheetPeriodId);
        $prepared = $action->execute($period);
        $this->selectedPeriodId = $prepared->id;
        session()->flash('success', 'Bordro hazırlık paketi oluşturuldu.');
    }

    public function refreshSource(PreparePayrollPeriodAction $action): void
    {
        $payrollPeriod = $this->payrollPeriod($this->selectedPeriodId);
        $prepared = $action->execute($payrollPeriod->timesheetPeriod);
        $this->selectedPeriodId = $prepared->id;
        session()->flash('success', 'Bordro kaynak paketi güncel puantaj ve onaylarla yenilendi.');
    }

    public function calculate(CalculatePayrollPeriodAction $action): void
    {
        $action->execute($this->payrollPeriod($this->selectedPeriodId));
        session()->flash('success', 'Brüt–net hesap ve önceki dönem fark analizi tamamlandı.');
    }

    public function reviewVariance(ReviewPayrollVarianceAction $action): void
    {
        $this->validate(['varianceReviewNote' => 'required|string|min:10|max:1000']);
        $action->execute($this->payrollPeriod($this->selectedPeriodId), $this->varianceReviewNote);
        $this->reset('varianceReviewNote');
        session()->flash('success', 'Dönem farkları incelendi ve kayıt altına alındı.');
    }

    public function approve(ApprovePayrollPeriodAction $action): void
    {
        $action->execute($this->payrollPeriod($this->selectedPeriodId));
        session()->flash('success', 'Bordro hazırlık paketi onaylandı ve donduruldu.');
    }

    public function proposeAdjustment(ManagePayrollAdjustmentAction $action): void
    {
        $this->validate([
            'adjustmentEmployeeId' => 'required|integer',
            'adjustmentCode' => 'required|string|max:60',
            'adjustmentAmount' => 'required|numeric|min:0.01',
            'adjustmentReason' => 'required|string|max:1000',
        ]);
        $period = $this->payrollPeriod($this->selectedPeriodId);
        $employee = HrEmployee::withoutGlobalScope('tenant')
            ->where('legal_entity_id', app(TenantContext::class)->getId())
            ->findOrFail($this->adjustmentEmployeeId);
        $action->propose($period, $employee, [
            'code' => $this->adjustmentCode,
            'amount_cents' => (int) round((float) $this->adjustmentAmount * 100),
            'reason' => $this->adjustmentReason,
        ]);
        $this->reset([
            'adjustmentEmployeeId', 'adjustmentCode', 'adjustmentName',
            'adjustmentAmount', 'adjustmentReason', 'socialSecurityExempt',
            'incomeTaxExempt', 'preTaxDeduction',
        ]);
        $this->adjustmentType = 'earning';
        session()->flash('success', 'Bordro kalemi onaya gönderildi.');
    }

    public function updatedAdjustmentCode(string $code): void
    {
        if ($code === '') {
            return;
        }
        $item = app(PayrollItemCatalog::class)->get($code);
        $this->adjustmentName = $item['name'];
        $this->adjustmentType = $item['type'];
        $this->socialSecurityExempt = $item['social_security_exempt'];
        $this->incomeTaxExempt = $item['income_tax_exempt'];
        $this->preTaxDeduction = $item['pre_tax_deduction'];
    }

    public function approveAdjustment(int $id, ManagePayrollAdjustmentAction $action): void
    {
        $adjustment = HrPayrollAdjustment::withoutGlobalScope('tenant')
            ->where('legal_entity_id', app(TenantContext::class)->getId())
            ->findOrFail($id);
        $action->approve($adjustment);
        session()->flash('success', 'Bordro kalemi onaylandı.');
    }

    public function select(int $id): void
    {
        $this->selectedPeriodId = $this->payrollPeriod($id)->id;
    }

    public function render()
    {
        $tenantId = app(TenantContext::class)->getId();
        $periods = HrPayrollPeriod::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->withCount('records')
            ->orderByDesc('id')
            ->get();
        $selected = $this->selectedPeriodId
            ? $this->payrollPeriod($this->selectedPeriodId)->load(['records.employee', 'timesheetPeriod'])
            : $periods->first()?->load(['records.employee', 'timesheetPeriod']);
        if ($selected) {
            $this->selectedPeriodId = $selected->id;
        }
        $adjustments = $selected
            ? HrPayrollAdjustment::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->where('payroll_period_id', $selected->id)
                ->with('employee')
                ->orderByDesc('id')
                ->get()
            : collect();

        return view('livewire.hr.payroll.payroll-workspace', [
            'periods' => $periods,
            'selected' => $selected,
            'adjustments' => $adjustments,
            'payrollItems' => app(PayrollItemCatalog::class)->all(),
            'closedTimesheets' => HrTimesheetPeriod::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->where('status', TimesheetPeriodStatus::Closed->value)
                ->orderByDesc('starts_on')
                ->get(),
        ])->layout('layouts.app');
    }

    private function payrollPeriod(int $id): HrPayrollPeriod
    {
        return HrPayrollPeriod::withoutGlobalScope('tenant')
            ->where('legal_entity_id', app(TenantContext::class)->getId())
            ->findOrFail($id);
    }
}
