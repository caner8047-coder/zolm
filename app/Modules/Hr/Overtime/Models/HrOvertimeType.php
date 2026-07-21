<?php

namespace App\Modules\Hr\Overtime\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrOvertimeType extends Model
{
    use BelongsToLegalEntity;
    protected $fillable = ['legal_entity_id', 'code', 'name', 'multiplier', 'annual_limit_minutes', 'requires_approval', 'is_active', 'created_by', 'updated_by'];
    protected function casts(): array { return ['multiplier' => 'decimal:2', 'annual_limit_minutes' => 'integer', 'requires_approval' => 'boolean', 'is_active' => 'boolean']; }
    public function requests(): HasMany { return $this->hasMany(HrOvertimeRequest::class, 'overtime_type_id'); }
}
