<?php

namespace App\Modules\Hr\Attendance\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrAttendanceAnomaly extends Model
{
    use BelongsToLegalEntity;
    protected $fillable = ['legal_entity_id', 'employee_id', 'work_date', 'type', 'severity', 'status', 'details', 'resolution_note', 'resolved_by', 'resolved_at'];
    protected function casts(): array { return ['work_date' => 'date', 'details' => 'array', 'resolved_at' => 'datetime']; }
    public function employee(): BelongsTo { return $this->belongsTo(HrEmployee::class); }
}
