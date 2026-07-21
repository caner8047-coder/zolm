<?php

namespace App\Modules\Hr\Leave\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Leave\Enums\LeaveTransactionType;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrLeaveTransaction extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = ['legal_entity_id', 'employee_id', 'leave_type_id', 'leave_request_id', 'period_year', 'transaction_type', 'amount', 'source_type', 'source_id', 'note', 'created_by'];

    protected function casts(): array { return ['transaction_type' => LeaveTransactionType::class, 'amount' => 'decimal:2']; }
    public function employee(): BelongsTo { return $this->belongsTo(HrEmployee::class); }
    public function leaveType(): BelongsTo { return $this->belongsTo(HrLeaveType::class); }
    public function leaveRequest(): BelongsTo { return $this->belongsTo(HrLeaveRequest::class); }
}
