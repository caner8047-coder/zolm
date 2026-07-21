<?php

namespace App\Modules\Hr\Shift\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Shift\Enums\ShiftAssignmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrShiftAssignment extends Model
{
    use BelongsToLegalEntity;
    protected $fillable = ['legal_entity_id', 'employee_id', 'shift_template_id', 'shift_date', 'status', 'note', 'published_at', 'published_by', 'created_by', 'updated_by'];
    protected function casts(): array { return ['shift_date' => 'date', 'status' => ShiftAssignmentStatus::class, 'published_at' => 'datetime']; }
    public function employee(): BelongsTo { return $this->belongsTo(HrEmployee::class); }
    public function template(): BelongsTo { return $this->belongsTo(HrShiftTemplate::class, 'shift_template_id'); }
}
