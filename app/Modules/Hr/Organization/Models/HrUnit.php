<?php

namespace App\Modules\Hr\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrUnit extends Model
{
    protected $fillable = [
        'department_id', 'name', 'code', 'manager_employee_id',
        'is_active', 'sort_order', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Hr\Organization\Models\HrDepartment::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(HrTeam::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
