<?php

namespace App\Modules\Hr\Payroll\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrPayrollEmployeeProfile extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = [
        'legal_entity_id', 'employee_id', 'version', 'effective_from', 'effective_until',
        'payroll_group_code', 'payment_method', 'iban_encrypted', 'iban_hash',
        'iban_last_four', 'bank_name', 'bank_account_holder', 'social_security_status',
        'insurance_branch_code', 'incentive_law_code', 'missing_day_default_code',
        'disability_degree', 'is_retired', 'is_rd_employee', 'is_technopark_employee',
        'status', 'change_reason', 'created_by', 'approved_by', 'approved_at',
    ];

    protected $hidden = ['iban_encrypted', 'iban_hash'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'iban_encrypted' => 'encrypted',
            'disability_degree' => 'integer',
            'is_retired' => 'boolean',
            'is_rd_employee' => 'boolean',
            'is_technopark_employee' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class);
    }

    public function maskedIban(): ?string
    {
        return $this->iban_last_four ? 'TR•• •••• •••• •••• •••• ••'.$this->iban_last_four : null;
    }
}
