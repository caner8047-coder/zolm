<?php

namespace App\Modules\Hr\Payroll\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrPayrollTaxOpening extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = ['legal_entity_id', 'employee_id', 'tax_year', 'opening_tax_base_encrypted', 'source_reference', 'created_by'];
    protected $hidden = ['opening_tax_base_encrypted'];
    protected function casts(): array { return ['tax_year' => 'integer', 'opening_tax_base_encrypted' => 'encrypted']; }
    public function employee(): BelongsTo { return $this->belongsTo(HrEmployee::class); }
    public function openingTaxBaseCents(): int { return (int) $this->opening_tax_base_encrypted; }
}
