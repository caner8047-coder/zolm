<?php

namespace App\Modules\Hr\Personnel\Models;

use App\Models\HrFile;
use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Personnel\Enums\EmployeeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrEmployee extends Model
{
    use BelongsToLegalEntity, HasFactory, SoftDeletes;

    protected static function newFactory()
    {
        return \Database\Factories\Hr\HrEmployeeFactory::new();
    }

    protected $fillable = [
        'legal_entity_id', 'user_id', 'employee_number',
        'national_id_encrypted', 'national_id_hash', 'national_id_last_four',
        'first_name', 'last_name', 'middle_name',
        'gender', 'date_of_birth', 'marital_status',
        'photo_file_id', 'phone', 'personal_email',
        'address', 'city', 'district', 'postal_code',
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
        'blood_type', 'status',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'national_id_encrypted' => 'encrypted',
            'status' => EmployeeStatus::class,
        ];
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(HrFile::class, 'photo_file_id');
    }

    public function employmentRecords(): HasMany
    {
        return $this->hasMany(HrEmploymentRecord::class, 'employee_id');
    }

    public function activeEmployment(): HasOne
    {
        return $this->hasOne(HrEmploymentRecord::class, 'employee_id')->where('status', 'active');
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(\App\Modules\Hr\Shift\Models\HrShiftAssignment::class, 'employee_id');
    }

    public function shiftAvailabilities(): HasMany
    {
        return $this->hasMany(\App\Modules\Hr\Shift\Models\HrShiftAvailability::class, 'employee_id');
    }

    public function attendanceEvents(): HasMany
    {
        return $this->hasMany(\App\Modules\Hr\Attendance\Models\HrAttendanceEvent::class, 'employee_id');
    }

    public function attendanceAnomalies(): HasMany
    {
        return $this->hasMany(\App\Modules\Hr\Attendance\Models\HrAttendanceAnomaly::class, 'employee_id');
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(\App\Modules\Hr\Timesheet\Models\HrTimesheet::class, 'employee_id');
    }

    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(\App\Modules\Hr\Overtime\Models\HrOvertimeRequest::class, 'employee_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(\App\Modules\Hr\Expense\Models\HrExpense::class, 'employee_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', EmployeeStatus::Active);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('employee_number', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('personal_email', 'like', "%{$search}%")
                ->orWhere('national_id_last_four', 'like', "%{$search}%");
        });
    }

    public function getFullNameAttribute(): string
    {
        return preg_replace('/\s+/u', ' ', trim($this->first_name . ' ' . $this->middle_name . ' ' . $this->last_name));
    }

    public function getTenureAttribute(): ?string
    {
        $activeRecord = $this->activeEmployment;
        if (!$activeRecord || !$activeRecord->start_date) {
            return null;
        }

        $start = \Carbon\Carbon::parse($activeRecord->start_date);
        $now = \Carbon\Carbon::now();
        $months = $start->diffInMonths($now);
        $years = floor($months / 12);
        $remainingMonths = $months % 12;

        if ($years > 0) {
            return "{$years}y {$remainingMonths}a";
        }

        return "{$remainingMonths}a";
    }
}
