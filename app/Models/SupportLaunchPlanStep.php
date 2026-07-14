<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportLaunchPlanStep extends Model
{
    protected $fillable = [
        'launch_plan_id', 'step_number', 'title', 'status',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SupportLaunchPlan::class, 'launch_plan_id');
    }
}
