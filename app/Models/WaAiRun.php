<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaAiRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id', 'contact_id', 'store_id', 'inbound_message_id',
        'outbox_id', 'intent', 'user_message', 'ai_response',
        'tools_called', 'context_snapshot', 'status',
        'handoff_reason', 'handoff_summary', 'response_time_ms',
    ];

    protected function casts(): array
    {
        return [
            'tools_called' => 'array',
            'context_snapshot' => 'array',
            'response_time_ms' => 'decimal:2',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WaConversation::class, 'conversation_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WaContact::class, 'contact_id');
    }

    public function inboundMessage(): BelongsTo
    {
        return $this->belongsTo(WaInboundMessage::class, 'inbound_message_id');
    }

    public function outbox(): BelongsTo
    {
        return $this->belongsTo(WaOutbox::class, 'outbox_id');
    }

    public function toolCalls(): HasMany
    {
        return $this->hasMany(WaAiToolCall::class, 'ai_run_id');
    }
}
