<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportSecurityAuditRun extends Model
{
    protected $fillable = [
        'store_id', 'status', 'overall_severity', 'findings_count',
        'triggered_by', 'is_dry_run', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'is_dry_run' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(SupportSecurityFinding::class, 'run_id');
    }

    public function evidenceItems(): HasMany
    {
        return $this->hasMany(SupportSecurityEvidenceItem::class, 'run_id');
    }

    public function hasCriticalFindings(): bool
    {
        return $this->findings()->where('severity', 'critical')->exists();
    }
}
