<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportExperimentRun extends Model
{
    protected $fillable = [
        'experiment_id', 'variant_id', 'status',
        'case_count', 'started_at', 'completed_at', 'summary',
    ];

    protected $casts = [
        'summary' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function experiment(): BelongsTo
    {
        return $this->belongsTo(SupportExperiment::class, 'experiment_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(SupportExperimentVariant::class, 'variant_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(SupportExperimentResult::class, 'run_id');
    }
}
