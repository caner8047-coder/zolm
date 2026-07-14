<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportRegressionCase extends Model
{
    protected $fillable = [
        'store_id', 'support_answer_error_id', 'language', 'intent', 'question_encrypted',
        'wrong_answer_encrypted', 'expected_answer_encrypted', 'status', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'question_encrypted' => 'encrypted',
        'wrong_answer_encrypted' => 'encrypted',
        'expected_answer_encrypted' => 'encrypted',
        'approved_at' => 'datetime',
    ];

    public function error(): BelongsTo { return $this->belongsTo(SupportAnswerError::class, 'support_answer_error_id'); }
}
