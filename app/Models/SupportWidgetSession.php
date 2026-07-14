<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupportWidgetSession extends Model
{
    protected $fillable = [
        'support_channel_id', 'conversation_id', 'session_hash', 'token_hash', 'origin',
        'consent_granted', 'marketing_consent_granted', 'privacy_notice_version', 'marketing_notice_version',
        'consented_at', 'marketing_consented_at', 'last_seen_at',
        'expires_at', 'status', 'metadata_json',
    ];

    protected $casts = [
        'consent_granted' => 'boolean',
        'marketing_consent_granted' => 'boolean',
        'consented_at' => 'datetime',
        'marketing_consented_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata_json' => 'array',
    ];

    public function channel(): BelongsTo { return $this->belongsTo(SupportChannel::class, 'support_channel_id'); }
    public function conversation(): BelongsTo { return $this->belongsTo(SupportConversation::class); }
    public function lead(): HasOne { return $this->hasOne(SupportWebLead::class); }
}
