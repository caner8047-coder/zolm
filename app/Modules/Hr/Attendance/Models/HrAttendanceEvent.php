<?php

namespace App\Modules\Hr\Attendance\Models;

use App\Modules\Hr\Attendance\Enums\AttendanceEventType;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrAttendanceEvent extends Model
{
    use BelongsToLegalEntity;
    protected $fillable = ['legal_entity_id', 'employee_id', 'attendance_device_id', 'event_type', 'occurred_at', 'source', 'source_key', 'payload_hash', 'latitude', 'longitude', 'metadata', 'is_manual', 'manual_reason', 'created_by'];
    protected $hidden = ['payload_hash'];
    protected function casts(): array { return ['event_type' => AttendanceEventType::class, 'occurred_at' => 'datetime', 'metadata' => 'array', 'is_manual' => 'boolean', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7']; }
    public function employee(): BelongsTo { return $this->belongsTo(HrEmployee::class); }
    public function device(): BelongsTo { return $this->belongsTo(HrAttendanceDevice::class, 'attendance_device_id'); }
}
