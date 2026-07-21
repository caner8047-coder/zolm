<?php

namespace App\Modules\Hr\Leave\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Leave\Enums\LeavePolicyScope;
use App\Modules\Hr\Organization\Models\HrBranch;
use App\Modules\Hr\Organization\Models\HrDepartment;
use App\Modules\Hr\Organization\Models\HrPosition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrLeavePolicy extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = ['legal_entity_id', 'leave_type_id', 'scope', 'branch_id', 'department_id', 'position_id', 'employment_type', 'annual_entitlement', 'max_carryover', 'allows_negative_balance', 'requires_hr_approval', 'effective_from', 'effective_until', 'is_active', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['scope' => LeavePolicyScope::class, 'annual_entitlement' => 'decimal:2', 'max_carryover' => 'decimal:2', 'allows_negative_balance' => 'boolean', 'requires_hr_approval' => 'boolean', 'effective_from' => 'date', 'effective_until' => 'date', 'is_active' => 'boolean'];
    }

    public function leaveType(): BelongsTo { return $this->belongsTo(HrLeaveType::class); }
    public function branch(): BelongsTo { return $this->belongsTo(HrBranch::class); }
    public function department(): BelongsTo { return $this->belongsTo(HrDepartment::class); }
    public function position(): BelongsTo { return $this->belongsTo(HrPosition::class); }
}
