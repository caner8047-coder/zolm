<?php

namespace App\Modules\Hr\Performance\Models;

use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrPerformanceResult extends Model
{
    use BelongsToLegalEntity;

    protected $fillable = [
        'legal_entity_id', 'cycle_id', 'employee_id', 'overall_score',
        'expected_responses', 'completed_responses', 'status', 'reviewer_breakdown',
        'competency_breakdown', 'calculation_hash', 'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'overall_score' => 'decimal:2',
            'expected_responses' => 'integer',
            'completed_responses' => 'integer',
            'reviewer_breakdown' => 'array',
            'competency_breakdown' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    public function cycle(): BelongsTo { return $this->belongsTo(HrPerformanceCycle::class, 'cycle_id'); }
    public function employee(): BelongsTo { return $this->belongsTo(HrEmployee::class); }
}
