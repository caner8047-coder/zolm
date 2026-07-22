<?php

namespace App\Modules\Hr\Performance\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrPerformanceGoalCheckIn extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = [
        'legal_entity_id', 'goal_id', 'previous_value', 'new_value',
        'previous_text', 'new_text', 'note', 'evidence', 'created_by',
    ];

    protected function casts(): array
    {
        return ['previous_value' => 'decimal:2', 'new_value' => 'decimal:2'];
    }

    public function goal(): BelongsTo { return $this->belongsTo(HrPerformanceGoal::class, 'goal_id'); }
}
