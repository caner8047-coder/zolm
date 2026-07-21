<?php

namespace App\Modules\Hr\Overtime\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Overtime\Enums\OvertimeRequestStatus;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrOvertimeRequest extends Model
{
    use BelongsToLegalEntity;
    protected $fillable = ['legal_entity_id', 'employee_id', 'overtime_type_id', 'work_date', 'starts_at', 'ends_at', 'requested_minutes', 'approved_minutes', 'status', 'reason', 'project_reference', 'production_order_reference', 'requested_by', 'decided_by', 'decided_at', 'decision_note'];
    protected function casts(): array { return ['work_date' => 'date', 'requested_minutes' => 'integer', 'approved_minutes' => 'integer', 'status' => OvertimeRequestStatus::class, 'decided_at' => 'datetime']; }
    public function employee(): BelongsTo { return $this->belongsTo(HrEmployee::class); }
    public function overtimeType(): BelongsTo { return $this->belongsTo(HrOvertimeType::class, 'overtime_type_id'); }
}
