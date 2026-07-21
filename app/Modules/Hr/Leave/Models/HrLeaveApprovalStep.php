<?php

namespace App\Modules\Hr\Leave\Models;

use App\Models\User;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Leave\Enums\LeaveApprovalStatus;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrLeaveApprovalStep extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = ['legal_entity_id', 'leave_request_id', 'step_order', 'approver_type', 'approver_employee_id', 'approver_user_id', 'status', 'comment', 'decided_at'];

    protected function casts(): array { return ['status' => LeaveApprovalStatus::class, 'decided_at' => 'datetime']; }
    public function leaveRequest(): BelongsTo { return $this->belongsTo(HrLeaveRequest::class); }
    public function approverEmployee(): BelongsTo { return $this->belongsTo(HrEmployee::class, 'approver_employee_id'); }
    public function approverUser(): BelongsTo { return $this->belongsTo(User::class, 'approver_user_id'); }
}
