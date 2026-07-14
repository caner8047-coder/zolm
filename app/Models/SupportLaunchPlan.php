<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportLaunchPlan extends Model
{
    protected $fillable = [
        'store_id', 'status', 'target_channels', 'initial_mode',
        'canary_percentage', 'conversation_limit', 'allowed_categories',
        'rollback_rules', 'approver_id', 'approved_at', 'readiness_snapshot',
    ];

    protected $casts = [
        'target_channels' => 'array',
        'allowed_categories' => 'array',
        'rollback_rules' => 'array',
        'readiness_snapshot' => 'array',
        'approved_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(SupportLaunchPlanStep::class, 'launch_plan_id');
    }
}
