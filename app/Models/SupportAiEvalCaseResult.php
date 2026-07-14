<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportAiEvalCaseResult extends Model
{
    use HasFactory;

    protected $table = 'support_ai_eval_case_results';

    protected $fillable = [
        'support_ai_eval_run_id',
        'category',
        'question_hash',
        'expected_keywords',
        'response_preview',
        'score',
        'status',
        'error',
    ];

    protected $casts = [
        'expected_keywords' => 'array',
        'score' => 'integer',
    ];

    public function evalRun(): BelongsTo
    {
        return $this->belongsTo(SupportAiEvalRun::class, 'support_ai_eval_run_id');
    }
}
