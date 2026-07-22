<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendyolBoosterActionState extends Model
{
    protected $fillable = [
        'user_id',
        'fingerprint',
        'status',
        'assigned_user_id',
        'assigned_by_user_id',
        'acknowledged_at',
        'snoozed_until',
        'resolved_at',
        'context_json',
    ];

    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'datetime',
            'snoozed_until' => 'datetime',
            'resolved_at' => 'datetime',
            'context_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
