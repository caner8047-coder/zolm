<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaMessageDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'outbox_id',
        'meta_message_id',
        'provider_event_key',
        'status',
        'error_code',
        'error_classification',
        'error_message',
        'sent_at',
        'delivered_at',
        'read_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function outbox(): BelongsTo
    {
        return $this->belongsTo(WaOutbox::class, 'outbox_id');
    }
}
