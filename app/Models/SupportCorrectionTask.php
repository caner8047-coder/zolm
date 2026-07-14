<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportCorrectionTask extends Model
{
    protected $fillable = [
        'support_answer_error_id', 'assigned_user_id', 'task_type', 'status',
        'due_at', 'completed_at', 'result_json',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'result_json' => 'array',
    ];

    public function error(): BelongsTo { return $this->belongsTo(SupportAnswerError::class, 'support_answer_error_id'); }
}
