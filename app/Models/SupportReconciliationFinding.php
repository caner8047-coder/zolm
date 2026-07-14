<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportReconciliationFinding extends Model
{
    protected $fillable = [
        'run_id', 'store_id', 'finding_type', 'details_json',
        'status', 'repaired_at', 'repaired_by',
    ];

    protected $casts = [
        'details_json' => 'array',
        'repaired_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(SupportReconciliationRun::class, 'run_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function repairedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'repaired_by');
    }
}
