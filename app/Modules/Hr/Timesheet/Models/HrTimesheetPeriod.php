<?php

namespace App\Modules\Hr\Timesheet\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Timesheet\Enums\TimesheetPeriodStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrTimesheetPeriod extends Model
{
    use BelongsToLegalEntity;
    protected $fillable = ['legal_entity_id', 'name', 'starts_on', 'ends_on', 'status', 'calculated_at', 'calculated_by', 'closed_at', 'closed_by'];
    protected function casts(): array { return ['starts_on' => 'date', 'ends_on' => 'date', 'status' => TimesheetPeriodStatus::class, 'calculated_at' => 'datetime', 'closed_at' => 'datetime']; }
    public function timesheets(): HasMany { return $this->hasMany(HrTimesheet::class, 'timesheet_period_id'); }
}
