<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportProductionFreezeSnapshot extends Model
{
    protected $fillable = [
        'store_id',
        'run_id',
        'snapshot_data_encrypted',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'snapshot_data_encrypted' => 'encrypted',
        'approved_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(SupportProductionReadinessRun::class, 'run_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
