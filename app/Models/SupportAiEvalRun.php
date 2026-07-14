<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportAiEvalRun extends Model
{
    use HasFactory;

    protected $table = 'support_ai_eval_runs';

    protected $fillable = [
        'store_id',
        'run_type',
        'provider',
        'model',
        'dataset_version',
        'average_score',
        'passed_gate',
        'status',
        'triggered_by_user_id',
        'started_at',
        'finished_at',
        'summary_json',
        'language',
        'dataset_profile',
    ];

    protected $casts = [
        'passed_gate' => 'boolean',
        'average_score' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'summary_json' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function caseResults(): HasMany
    {
        return $this->hasMany(SupportAiEvalCaseResult::class, 'support_ai_eval_run_id');
    }
}
