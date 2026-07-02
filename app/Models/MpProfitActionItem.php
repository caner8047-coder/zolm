<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MpProfitActionItem extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SNOOZED = 'snoozed';
    public const STATUS_RESOLVED = 'resolved';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    protected $fillable = [
        'user_id',
        'scope_hash',
        'fingerprint',
        'action_key',
        'title',
        'description',
        'action_label',
        'route_name',
        'query_json',
        'filters_json',
        'recommendation_json',
        'value',
        'impact',
        'score',
        'status',
        'priority',
        'due_date',
        'owner_label',
        'note',
        'snoozed_until',
        'resolved_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'query_json' => 'array',
            'filters_json' => 'array',
            'recommendation_json' => 'array',
            'value' => 'integer',
            'impact' => 'decimal:2',
            'score' => 'decimal:2',
            'due_date' => 'date',
            'snoozed_until' => 'datetime',
            'resolved_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MpProfitActionEvent::class, 'mp_profit_action_item_id');
    }
}
