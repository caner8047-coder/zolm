<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPilotFeedback extends Model
{
    protected $table = 'accounting_pilot_feedbacks';

    protected $fillable = [
        'user_id',
        'actor_user_id',
        'module',
        'route_name',
        'type',
        'severity',
        'status',
        'title',
        'description',
        'browser',
        'viewport_width',
        'viewport_height',
        'screenshot_path',
        'resolved_at',
        'meta_json',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'meta_json' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }
}
