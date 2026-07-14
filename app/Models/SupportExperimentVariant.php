<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportExperimentVariant extends Model
{
    protected $fillable = [
        'experiment_id', 'label', 'artifact_type',
        'artifact_version_id', 'config_override', 'is_winner_candidate',
    ];

    protected $casts = [
        'config_override' => 'array',
        'is_winner_candidate' => 'boolean',
    ];

    public function experiment(): BelongsTo
    {
        return $this->belongsTo(SupportExperiment::class, 'experiment_id');
    }

    public function artifactVersion(): BelongsTo
    {
        return $this->belongsTo(SupportArtifactVersion::class, 'artifact_version_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(SupportExperimentRun::class, 'variant_id');
    }
}
