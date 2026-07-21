<?php
namespace App\Modules\Hr\Payroll\Models;
use App\Modules\Hr\Compensation\Models\HrSalaryRecord; use App\Modules\Hr\Core\Traits\BelongsToLegalEntity; use App\Modules\Hr\Personnel\Models\HrEmployee; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class HrPayrollRecord extends Model
{
 use BelongsToLegalEntity;
 protected $fillable=['legal_entity_id','payroll_period_id','employee_id','salary_record_id','scheduled_minutes','worked_minutes','leave_minutes','overtime_minutes','approved_overtime_minutes','missing_minutes','source_snapshot','source_hash','rule_snapshot','calculation_trace','gross_pay_encrypted','employee_deductions_encrypted','employer_contributions_encrypted','income_tax_encrypted','stamp_tax_encrypted','net_pay_encrypted','calculation_hash','calculated_at','status'];
 protected $hidden=['source_hash','calculation_trace','gross_pay_encrypted','employee_deductions_encrypted','employer_contributions_encrypted','income_tax_encrypted','stamp_tax_encrypted','net_pay_encrypted','calculation_hash'];
 protected function casts():array{return ['source_snapshot'=>'array','rule_snapshot'=>'array','calculation_trace'=>'encrypted:array','gross_pay_encrypted'=>'encrypted','employee_deductions_encrypted'=>'encrypted','employer_contributions_encrypted'=>'encrypted','income_tax_encrypted'=>'encrypted','stamp_tax_encrypted'=>'encrypted','net_pay_encrypted'=>'encrypted','calculated_at'=>'datetime'];}
 public function period():BelongsTo{return $this->belongsTo(HrPayrollPeriod::class,'payroll_period_id');}
 public function employee():BelongsTo{return $this->belongsTo(HrEmployee::class);}
 public function salaryRecord():BelongsTo{return $this->belongsTo(HrSalaryRecord::class,'salary_record_id');}
 public function grossPay():float{return(float)$this->gross_pay_encrypted;}
 public function netPay():float{return(float)$this->net_pay_encrypted;}
 public function employeeDeductions():float{return(float)$this->employee_deductions_encrypted;}
 public function employerContributions():float{return(float)$this->employer_contributions_encrypted;}
}
