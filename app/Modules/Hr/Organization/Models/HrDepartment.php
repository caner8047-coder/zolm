<?php

namespace App\Modules\Hr\Organization\Models;

use App\Models\LegalEntity;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrDepartment extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = [
        'legal_entity_id', 'parent_id', 'branch_id', 'cost_center_id',
        'name', 'code', 'manager_employee_id', 'is_active', 'sort_order',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(HrBranch::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(HrCostCenter::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(HrUnit::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(\App\Modules\Hr\Organization\Models\HrPosition::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }
}
