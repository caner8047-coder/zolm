<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'support_channel_id', 'external_conversation_id', 'external_customer_id',
        'store_id', 'source_type', 'status', 'priority', 'assigned_user_id',
        'last_message_at', 'last_inbound_at', 'last_outbound_at',
        'ai_mode', 'source_reference_json',
    ];

    protected function casts(): array
    {
        return [
            'source_reference_json' => 'array',
            'last_message_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'last_outbound_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(SupportChannel::class, 'support_channel_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'conversation_id');
    }

    public function agentActions(): HasMany
    {
        return $this->hasMany(SupportAgentAction::class, 'conversation_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_user_id');
    }
}
