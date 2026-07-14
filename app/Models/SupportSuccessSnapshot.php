<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportSuccessSnapshot extends Model
{
    protected $fillable = [
        'store_id', 'health_score', 'health_label',
        'component_scores', 'unknown_components',
        'computed_by', 'computed_at', 'is_stale',
    ];

    protected $casts = [
        'component_scores' => 'array',
        'unknown_components' => 'array',
        'computed_at' => 'datetime',
        'is_stale' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(SupportSuccessTask::class, 'snapshot_id');
    }
}
