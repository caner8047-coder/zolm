<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportProductionReadinessRun extends Model
{
    protected $fillable = [
        'store_id',
        'run_by',
        'readiness_score',
        'status',
        'check_results_json',
        'failed_checks_json',
    ];

    protected $casts = [
        'check_results_json' => 'array',
        'failed_checks_json' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function runBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'run_by');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(SupportProductionFreezeSnapshot::class, 'run_id');
    }
}
