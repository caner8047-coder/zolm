<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaTrackingLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'outbox_id', 'destination_url', 'token_hash',
        'expires_at', 'clicked_at', 'click_count',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'clicked_at' => 'datetime',
        ];
    }

    public function outbox(): BelongsTo
    {
        return $this->belongsTo(WaOutbox::class, 'outbox_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
