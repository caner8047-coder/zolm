<?php

namespace App\Modules\Hr\Payroll\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrPayrollAdjustment extends Model
{
    use BelongsToLegalEntity;

    public const TYPES = ['earning', 'deduction', 'employer_incentive', 'employer_benefit'];

    protected $fillable = ['legal_entity_id', 'payroll_period_id', 'employee_id', 'code', 'name', 'type', 'amount_encrypted', 'social_security_exempt', 'income_tax_exempt', 'pre_tax_deduction', 'status', 'reason', 'created_by', 'approved_by', 'approved_at'];
    protected $hidden = ['amount_encrypted'];
    protected function casts(): array { return ['amount_encrypted' => 'encrypted', 'social_security_exempt' => 'boolean', 'income_tax_exempt' => 'boolean', 'pre_tax_deduction' => 'boolean', 'approved_at' => 'datetime']; }
    public function period(): BelongsTo { return $this->belongsTo(HrPayrollPeriod::class, 'payroll_period_id'); }
    public function employee(): BelongsTo { return $this->belongsTo(HrEmployee::class); }
    public function amountCents(): int { return (int) $this->amount_encrypted; }
}
