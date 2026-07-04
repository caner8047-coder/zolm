<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaWebhookEvent extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSED = 'processed';
    const STATUS_FAILED = 'failed';
    const STATUS_DUPLICATE = 'duplicate';

    protected $fillable = [
        'event_type',
        'request_id',
        'request_hash',
        'provider_event_key',
        'source',
        'payload',
        'signature',
        'verified_at',
        'processed_at',
        'status',
        'duplicate_count',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'verified_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeForRetry($query)
    {
        return $query->where('status', self::STATUS_FAILED)
            ->orWhere(function ($q) {
                $q->where('status', self::STATUS_PENDING)
                    ->where('processed_at', '<', now()->subMinutes(5));
            });
    }
}
