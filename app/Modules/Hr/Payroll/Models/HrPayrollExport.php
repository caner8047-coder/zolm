<?php

namespace App\Modules\Hr\Payroll\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrPayrollExport extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = ['legal_entity_id', 'payroll_period_id', 'classification', 'format', 'preflight_hash', 'content_hash', 'generated_by', 'generated_at'];
    protected function casts(): array { return ['generated_at' => 'datetime']; }
    public function period(): BelongsTo { return $this->belongsTo(HrPayrollPeriod::class, 'payroll_period_id'); }
}
