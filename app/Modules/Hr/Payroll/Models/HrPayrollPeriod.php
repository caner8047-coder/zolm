<?php

namespace App\Modules\Hr\Payroll\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Timesheet\Models\HrTimesheetPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrPayrollPeriod extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = [
        'legal_entity_id', 'timesheet_period_id', 'name', 'status', 'source_hash',
        'source_status', 'source_stale_findings', 'source_checked_at', 'prepared_at',
        'prepared_by', 'calculated_at', 'calculated_by', 'calculation_hash',
        'preflight_status', 'preflight_findings', 'variance_status', 'variance_findings',
        'variance_checked_at', 'variance_reviewed_by', 'variance_reviewed_at',
        'variance_review_note', 'output_preflight_status',
        'output_preflight_findings', 'output_preflight_hash', 'output_preflight_at',
        'output_preflight_by', 'approved_at', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'source_stale_findings' => 'array',
            'source_checked_at' => 'datetime',
            'prepared_at' => 'datetime',
            'calculated_at' => 'datetime',
            'approved_at' => 'datetime',
            'preflight_findings' => 'array',
            'variance_findings' => 'array',
            'variance_checked_at' => 'datetime',
            'variance_reviewed_at' => 'datetime',
            'output_preflight_findings' => 'array',
            'output_preflight_at' => 'datetime',
        ];
    }

    public function timesheetPeriod(): BelongsTo
    {
        return $this->belongsTo(HrTimesheetPeriod::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(HrPayrollRecord::class, 'payroll_period_id');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(HrPayrollExport::class, 'payroll_period_id');
    }
}
