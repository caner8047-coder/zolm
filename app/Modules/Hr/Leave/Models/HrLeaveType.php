<?php

namespace App\Modules\Hr\Leave\Models;

use App\Models\LegalEntity;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Leave\Enums\LeaveUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrLeaveType extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = ['legal_entity_id', 'code', 'name', 'unit', 'is_paid', 'requires_document', 'allows_negative_balance', 'is_active', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['unit' => LeaveUnit::class, 'is_paid' => 'boolean', 'requires_document' => 'boolean', 'allows_negative_balance' => 'boolean', 'is_active' => 'boolean'];
    }

    public function legalEntity(): BelongsTo { return $this->belongsTo(LegalEntity::class); }
    public function policies(): HasMany { return $this->hasMany(HrLeavePolicy::class, 'leave_type_id'); }
    public function balances(): HasMany { return $this->hasMany(HrLeaveBalance::class, 'leave_type_id'); }
    public function requests(): HasMany { return $this->hasMany(HrLeaveRequest::class, 'leave_type_id'); }
}
