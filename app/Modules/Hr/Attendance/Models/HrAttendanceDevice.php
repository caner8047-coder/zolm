<?php

namespace App\Modules\Hr\Attendance\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrAttendanceDevice extends Model
{
    use BelongsToLegalEntity;
    protected $fillable = ['legal_entity_id', 'code', 'name', 'type', 'location', 'secret_hash', 'is_active', 'last_seen_at', 'created_by', 'updated_by'];
    protected $hidden = ['secret_hash'];
    protected function casts(): array { return ['is_active' => 'boolean', 'last_seen_at' => 'datetime']; }
    public function events(): HasMany { return $this->hasMany(HrAttendanceEvent::class, 'attendance_device_id'); }
}
