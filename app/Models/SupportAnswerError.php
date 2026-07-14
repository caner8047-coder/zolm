<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupportAnswerError extends Model
{
    protected $fillable = [
        'store_id', 'conversation_id', 'message_id', 'support_ai_run_id', 'reported_by',
        'severity', 'affected_claim_encrypted', 'root_cause_encrypted', 'correction_strategy',
        'status', 'correction_message_id', 'detected_at', 'corrected_at',
    ];

    protected $casts = [
        'affected_claim_encrypted' => 'encrypted',
        'root_cause_encrypted' => 'encrypted',
        'detected_at' => 'datetime',
        'corrected_at' => 'datetime',
    ];

    public function conversation(): BelongsTo { return $this->belongsTo(SupportConversation::class); }
    public function message(): BelongsTo { return $this->belongsTo(SupportMessage::class); }
    public function aiRun(): BelongsTo { return $this->belongsTo(SupportAiRun::class, 'support_ai_run_id'); }
    public function tasks(): HasMany { return $this->hasMany(SupportCorrectionTask::class); }
    public function regressionCase(): HasOne { return $this->hasOne(SupportRegressionCase::class); }
}
