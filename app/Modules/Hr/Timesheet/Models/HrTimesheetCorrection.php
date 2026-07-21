<?php

namespace App\Modules\Hr\Timesheet\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrTimesheetCorrection extends Model
{
    use BelongsToLegalEntity;
    protected $fillable = ['legal_entity_id', 'timesheet_id', 'revision_number', 'old_values', 'new_values', 'reason', 'created_by'];
    protected function casts(): array { return ['old_values' => 'array', 'new_values' => 'array']; }
    public function timesheet(): BelongsTo { return $this->belongsTo(HrTimesheet::class, 'timesheet_id'); }
}
