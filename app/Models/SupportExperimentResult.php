<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportExperimentResult extends Model
{
    protected $fillable = [
        'run_id', 'store_id', 'eval_case_id',
        'policy_violation', 'hallucination_detected',
        'brand_voice_score', 'latency_ms', 'total_tokens',
        'estimated_cost', 'human_verdict', 'redacted_response_sample',
    ];

    protected $casts = [
        'policy_violation' => 'boolean',
        'hallucination_detected' => 'boolean',
        'estimated_cost' => 'decimal:6',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(SupportExperimentRun::class, 'run_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }
}
