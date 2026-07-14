<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportDispatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'support_channel_id',
        'conversation_id',
        'message_id',
        'idempotency_key',
        'status',
        'attempt_count',
        'retry_at',
        'channel_message_id',
        'last_error',
        'payload_json',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'retry_at' => 'datetime',
            'attempt_count' => 'integer',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(SupportChannel::class, 'support_channel_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'conversation_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportMessage::class, 'message_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(SupportDispatchAttempt::class, 'support_dispatch_id');
    }
}
