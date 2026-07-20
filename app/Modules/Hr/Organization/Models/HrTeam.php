<?php

namespace App\Modules\Hr\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrTeam extends Model
{
    protected $fillable = [
        'unit_id', 'name', 'lead_employee_id', 'is_active', 'sort_order',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(HrUnit::class);
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
