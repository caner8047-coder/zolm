<?php

namespace App\Modules\Hr\Leave\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use App\Modules\Hr\Leave\Enums\LeaveRequestStatus;
use App\Modules\Hr\Leave\Enums\LeaveUnit;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrLeaveRequest extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = ['legal_entity_id', 'employee_id', 'leave_type_id', 'policy_id', 'status', 'start_date', 'end_date', 'start_time', 'end_time', 'requested_amount', 'unit', 'reason', 'document_id', 'delegate_employee_id', 'cancelled_by', 'cancelled_at', 'cancellation_reason', 'revision_of_id'];

    protected function casts(): array
    {
        return ['status' => LeaveRequestStatus::class, 'unit' => LeaveUnit::class, 'start_date' => 'date', 'end_date' => 'date', 'requested_amount' => 'decimal:2', 'cancelled_at' => 'datetime'];
    }

    public function employee(): BelongsTo { return $this->belongsTo(HrEmployee::class); }
    public function leaveType(): BelongsTo { return $this->belongsTo(HrLeaveType::class); }
    public function policy(): BelongsTo { return $this->belongsTo(HrLeavePolicy::class); }
    public function document(): BelongsTo { return $this->belongsTo(HrEmployeeDocument::class, 'document_id'); }
    public function delegateEmployee(): BelongsTo { return $this->belongsTo(HrEmployee::class, 'delegate_employee_id'); }
    public function revisionOf(): BelongsTo { return $this->belongsTo(self::class, 'revision_of_id'); }
    public function approvalSteps(): HasMany { return $this->hasMany(HrLeaveApprovalStep::class, 'leave_request_id')->orderBy('step_order'); }
}
