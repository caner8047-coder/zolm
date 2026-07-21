<?php

namespace App\Modules\Hr\Shift\Models;

use App\Models\User;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Shift\Enums\ShiftChangeRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrShiftChangeRequest extends Model
{
    use BelongsToLegalEntity;
    protected $fillable = ['legal_entity_id', 'employee_id', 'shift_assignment_id', 'desired_shift_template_id', 'desired_shift_date', 'status', 'reason', 'decision_note', 'decided_by', 'decided_at', 'created_by'];
    protected function casts(): array { return ['desired_shift_date' => 'date', 'status' => ShiftChangeRequestStatus::class, 'decided_at' => 'datetime']; }
    public function employee(): BelongsTo { return $this->belongsTo(HrEmployee::class); }
    public function assignment(): BelongsTo { return $this->belongsTo(HrShiftAssignment::class, 'shift_assignment_id'); }
    public function desiredTemplate(): BelongsTo { return $this->belongsTo(HrShiftTemplate::class, 'desired_shift_template_id'); }
    public function decidedBy(): BelongsTo { return $this->belongsTo(User::class, 'decided_by'); }
}
