<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaInboundMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'contact_id',
        'meta_message_id',
        'message_type',
        'body',
        'payload_json',
        'received_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'received_at' => 'datetime',
            'read_at' => 'datetime',
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
}
