<?php

namespace App\Modules\Hr\Shift\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrShiftTemplate extends Model
{
    use BelongsToLegalEntity;
    protected $fillable = ['legal_entity_id', 'code', 'name', 'starts_at', 'ends_at', 'break_minutes', 'crosses_midnight', 'color', 'is_active', 'created_by', 'updated_by'];
    protected function casts(): array { return ['crosses_midnight' => 'boolean', 'is_active' => 'boolean', 'break_minutes' => 'integer']; }
    public function assignments(): HasMany { return $this->hasMany(HrShiftAssignment::class, 'shift_template_id'); }
}
