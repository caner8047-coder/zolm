<?php

namespace App\Modules\Hr\Timesheet\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Shift\Models\HrShiftAssignment;
use App\Modules\Hr\Timesheet\Enums\TimesheetStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HrTimesheet extends Model
{
    use BelongsToLegalEntity;
    protected $fillable = ['legal_entity_id', 'timesheet_period_id', 'employee_id', 'shift_assignment_id', 'work_date', 'scheduled_minutes', 'worked_minutes', 'break_minutes', 'leave_minutes', 'overtime_minutes', 'missing_minutes', 'first_in_at', 'last_out_at', 'status', 'source_revision', 'calculated_at', 'confirmed_by', 'confirmed_at'];
    protected function casts(): array { return ['work_date' => 'date', 'first_in_at' => 'datetime', 'last_out_at' => 'datetime', 'status' => TimesheetStatus::class, 'calculated_at' => 'datetime', 'confirmed_at' => 'datetime']; }
    public function period(): BelongsTo { return $this->belongsTo(HrTimesheetPeriod::class, 'timesheet_period_id'); }
    public function employee(): BelongsTo { return $this->belongsTo(HrEmployee::class); }
    public function shiftAssignment(): BelongsTo { return $this->belongsTo(HrShiftAssignment::class); }
    public function corrections(): HasMany { return $this->hasMany(HrTimesheetCorrection::class, 'timesheet_id'); }
    public function latestCorrection(): HasOne { return $this->hasOne(HrTimesheetCorrection::class, 'timesheet_id')->latestOfMany('revision_number'); }
    public function effective(string $field): mixed { return $this->latestCorrection?->new_values[$field] ?? $this->{$field}; }
}
