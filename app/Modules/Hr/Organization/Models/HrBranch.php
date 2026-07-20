<?php

namespace App\Modules\Hr\Organization\Models;

use App\Models\LegalEntity;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrBranch extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = [
        'legal_entity_id', 'sgk_workplace_id', 'name', 'code',
        'address', 'city', 'phone', 'is_active', 'sort_order', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function sgkWorkplace(): BelongsTo
    {
        return $this->belongsTo(HrSgkWorkplace::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(HrDepartment::class);
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
