<?php

namespace App\Modules\Hr\Payroll\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrPayrollTaxLedger extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = [
        'legal_entity_id', 'payroll_period_id', 'payroll_record_id', 'employee_id', 'tax_year',
        'opening_tax_base_encrypted', 'period_tax_base_encrypted', 'closing_tax_base_encrypted', 'calculation_hash',
    ];

    protected $hidden = ['opening_tax_base_encrypted', 'period_tax_base_encrypted', 'closing_tax_base_encrypted', 'calculation_hash'];

    protected function casts(): array
    {
        return [
            'tax_year' => 'integer',
            'opening_tax_base_encrypted' => 'encrypted',
            'period_tax_base_encrypted' => 'encrypted',
            'closing_tax_base_encrypted' => 'encrypted',
        ];
    }

    public function period(): BelongsTo { return $this->belongsTo(HrPayrollPeriod::class, 'payroll_period_id'); }
    public function record(): BelongsTo { return $this->belongsTo(HrPayrollRecord::class, 'payroll_record_id'); }
    public function employee(): BelongsTo { return $this->belongsTo(HrEmployee::class); }
    public function closingTaxBaseCents(): int { return (int) $this->closing_tax_base_encrypted; }
}
