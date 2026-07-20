<?php

namespace App\Modules\Hr\Organization\Models;

use App\Models\LegalEntity;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrPosition extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = [
        'legal_entity_id', 'department_id', 'title', 'code', 'level',
        'min_salary', 'max_salary', 'job_description', 'is_active', 'sort_order',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'min_salary' => 'decimal:2',
            'max_salary' => 'decimal:2',
        ];
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrDepartment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }
}
