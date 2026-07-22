<?php

namespace App\Modules\Hr\Payroll\Models;

use App\Modules\Hr\Compensation\Models\HrSalaryRecord;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrPayrollRecord extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = [
        'legal_entity_id', 'payroll_period_id', 'employee_id', 'salary_record_id', 'payroll_profile_id',
        'scheduled_minutes', 'worked_minutes', 'leave_minutes', 'overtime_minutes',
        'holiday_work_minutes', 'weekly_rest_work_minutes', 'approved_overtime_minutes',
        'approved_regular_overtime_minutes', 'approved_holiday_work_minutes',
        'approved_weekly_rest_work_minutes', 'missing_minutes', 'source_snapshot',
        'source_hash', 'rule_snapshot', 'payroll_profile_snapshot', 'calculation_trace', 'gross_pay_encrypted',
        'employee_deductions_encrypted', 'employer_contributions_encrypted',
        'income_tax_encrypted', 'stamp_tax_encrypted', 'net_pay_encrypted',
        'calculation_hash', 'calculated_at', 'status',
    ];

    protected $hidden = [
        'source_hash', 'calculation_trace', 'gross_pay_encrypted',
        'employee_deductions_encrypted', 'employer_contributions_encrypted',
        'income_tax_encrypted', 'stamp_tax_encrypted', 'net_pay_encrypted',
        'calculation_hash',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_minutes' => 'integer',
            'worked_minutes' => 'integer',
            'leave_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'holiday_work_minutes' => 'integer',
            'weekly_rest_work_minutes' => 'integer',
            'approved_overtime_minutes' => 'integer',
            'approved_regular_overtime_minutes' => 'integer',
            'approved_holiday_work_minutes' => 'integer',
            'approved_weekly_rest_work_minutes' => 'integer',
            'missing_minutes' => 'integer',
            'source_snapshot' => 'array',
            'rule_snapshot' => 'array',
            'payroll_profile_snapshot' => 'encrypted:array',
            'calculation_trace' => 'encrypted:array',
            'gross_pay_encrypted' => 'encrypted',
            'employee_deductions_encrypted' => 'encrypted',
            'employer_contributions_encrypted' => 'encrypted',
            'income_tax_encrypted' => 'encrypted',
            'stamp_tax_encrypted' => 'encrypted',
            'net_pay_encrypted' => 'encrypted',
            'calculated_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(HrPayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class);
    }

    public function salaryRecord(): BelongsTo
    {
        return $this->belongsTo(HrSalaryRecord::class, 'salary_record_id');
    }

    public function payrollProfile(): BelongsTo
    {
        return $this->belongsTo(HrPayrollEmployeeProfile::class, 'payroll_profile_id');
    }

    public function grossPay(): float
    {
        return (float) $this->gross_pay_encrypted;
    }

    public function netPay(): float
    {
        return (float) $this->net_pay_encrypted;
    }

    public function employeeDeductions(): float
    {
        return (float) $this->employee_deductions_encrypted;
    }

    public function employerContributions(): float
    {
        return (float) $this->employer_contributions_encrypted;
    }

    public function incomeTax(): float
    {
        return (float) $this->income_tax_encrypted;
    }

    public function stampTax(): float
    {
        return (float) $this->stamp_tax_encrypted;
    }
}
