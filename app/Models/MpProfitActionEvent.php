<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MpProfitActionEvent extends Model
{
    public const TYPE_CREATED = 'created';
    public const TYPE_REFRESHED = 'refreshed';
    public const TYPE_REOPENED_BY_SIGNAL = 'reopened_by_signal';
    public const TYPE_STATUS_CHANGED = 'status_changed';
    public const TYPE_PLAN_UPDATED = 'plan_updated';
    public const TYPE_NOTE_UPDATED = 'note_updated';

    protected $fillable = [
        'mp_profit_action_item_id',
        'user_id',
        'event_type',
        'from_status',
        'to_status',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'meta_json' => 'array',
        ];
    }

    public function actionItem(): BelongsTo
    {
        return $this->belongsTo(MpProfitActionItem::class, 'mp_profit_action_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
