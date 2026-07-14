<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportSecurityFinding extends Model
{
    protected $fillable = [
        'run_id', 'store_id', 'category', 'severity',
        'title', 'description', 'status',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(SupportSecurityAuditRun::class, 'run_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }
}
