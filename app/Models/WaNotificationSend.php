<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaNotificationSend extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_id', 'template_id', 'recipient', 'status',
        'variables_used', 'error_message',
        'sent_at', 'delivered_at', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'variables_used' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(WaNotificationChannel::class, 'channel_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WaNotificationTemplate::class, 'template_id');
    }
}
