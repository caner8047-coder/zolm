<?php

namespace App\Modules\Hr\Shift\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Shift\Enums\ShiftAvailabilityStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrShiftAvailability extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = ['legal_entity_id', 'employee_id', 'availability_date', 'status', 'preferred_start', 'preferred_end', 'note', 'created_by', 'updated_by'];
    protected function casts(): array { return ['availability_date' => 'date', 'status' => ShiftAvailabilityStatus::class]; }
    public function employee(): BelongsTo { return $this->belongsTo(HrEmployee::class); }
}
