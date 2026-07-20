<?php

namespace App\Modules\Hr\Personnel\Models;

use App\Models\LegalEntity;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Organization\Models\HrBranch;
use App\Modules\Hr\Organization\Models\HrCostCenter;
use App\Modules\Hr\Organization\Models\HrDepartment;
use App\Modules\Hr\Organization\Models\HrPosition;
use App\Modules\Hr\Organization\Models\HrSgkWorkplace;
use App\Modules\Hr\Organization\Models\HrTeam;
use App\Modules\Hr\Organization\Models\HrUnit;
use App\Modules\Hr\Personnel\Enums\EmploymentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrEmploymentRecord extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = [
        'employee_id', 'legal_entity_id', 'sgk_workplace_id', 'branch_id',
        'department_id', 'unit_id', 'team_id', 'position_id',
        'manager_employee_id', 'second_manager_employee_id', 'cost_center_id',
        'employment_type', 'work_model', 'contract_type',
        'start_date', 'end_date', 'probation_end_date', 'weekly_work_hours',
        'status', 'termination_reason',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'probation_end_date' => 'date',
            'weekly_work_hours' => 'decimal:1',
            'employment_type' => EmploymentType::class,
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class);
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function sgkWorkplace(): BelongsTo
    {
        return $this->belongsTo(HrSgkWorkplace::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(HrBranch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrDepartment::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(HrUnit::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(HrTeam::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(HrPosition::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(HrCostCenter::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'manager_employee_id');
    }

    public function secondManager(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'second_manager_employee_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCurrent($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            });
    }
}
