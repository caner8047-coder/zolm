<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportReconciliationRun extends Model
{
    protected $fillable = [
        'store_id', 'started_at', 'completed_at', 'status', 'summary_json',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'summary_json' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(SupportReconciliationFinding::class, 'run_id');
    }
}
