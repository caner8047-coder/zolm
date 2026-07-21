<?php

namespace App\Modules\Hr\Leave\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrLeaveBalance extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = ['legal_entity_id', 'employee_id', 'leave_type_id', 'period_year', 'entitled_amount', 'used_amount', 'adjustment_amount', 'carried_amount', 'remaining_amount'];

    protected function casts(): array
    {
        return ['entitled_amount' => 'decimal:2', 'used_amount' => 'decimal:2', 'adjustment_amount' => 'decimal:2', 'carried_amount' => 'decimal:2', 'remaining_amount' => 'decimal:2'];
    }

    public function employee(): BelongsTo { return $this->belongsTo(HrEmployee::class); }
    public function leaveType(): BelongsTo { return $this->belongsTo(HrLeaveType::class); }
}
