<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportKnowledgeSuggestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'source_conversation_id', 'source_message_id',
        'category', 'title', 'proposed_answer', 'confidence',
        'status', 'reviewed_by_user_id', 'reviewed_at', 'hash_key',
        'cluster_key', 'source_conversation_ids', 'source_message_ids',
        'scope', 'version', 'effective_until', 'owner_user_id',
    ];

    protected $casts = [
        'confidence' => 'integer',
        'reviewed_at' => 'datetime',
        'source_conversation_ids' => 'array',
        'source_message_ids' => 'array',
        'effective_until' => 'datetime',
        'version' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'source_conversation_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportMessage::class, 'source_message_id');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
