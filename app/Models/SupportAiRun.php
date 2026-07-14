<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportAiRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'conversation_id', 'message_id', 'prompt_template_key',
        'prompt_raw', 'response_raw', 'confidence_score', 'sources_used_json',
        'token_in', 'token_out', 'latency_ms', 'status', 'shadow_match_score',
        'detected_language', 'language_confidence', 'response_language',
    ];

    protected $casts = [
        'prompt_raw' => 'encrypted',
        'response_raw' => 'encrypted',
        'sources_used_json' => 'array',
        'confidence_score' => 'integer',
        'token_in' => 'integer',
        'token_out' => 'integer',
        'latency_ms' => 'integer',
        'language_confidence' => 'float',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'conversation_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportMessage::class, 'message_id');
    }
}
