<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceRiskSignalState extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_SNOOZED = 'snoozed';
    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'user_id',
        'fingerprint',
        'signal_key',
        'category',
        'severity',
        'status',
        'is_current',
        'title',
        'signal_json',
        'note',
        'snoozed_until',
        'resolved_at',
        'last_seen_at',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'signal_json' => 'array',
            'is_current' => 'boolean',
            'snoozed_until' => 'datetime',
            'resolved_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
