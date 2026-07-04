<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id', 'external_message_id', 'direction', 'sender_type',
        'message_type', 'body_encrypted', 'body_preview', 'payload_json',
        'sent_at', 'received_at', 'delivery_status',
        'source_reference_type', 'source_reference_id',
    ];

    protected function casts(): array
    {
        return [
            'body_encrypted' => 'encrypted',
            'payload_json' => 'array',
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'conversation_id');
    }
}
