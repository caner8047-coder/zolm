<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaExternalIntegration extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'provider', 'name', 'status',
        'config_json', 'credentials_encrypted', 'is_enabled',
        'last_sync_at', 'last_health_check_at',
    ];

    protected function casts(): array
    {
        return [
            'config_json' => 'array',
            'credentials_encrypted' => 'encrypted',
            'is_enabled' => 'boolean',
            'last_sync_at' => 'datetime',
            'last_health_check_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function syncJobs(): HasMany
    {
        return $this->hasMany(WaIntegrationSyncJob::class, 'integration_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_enabled', true)->where('status', 'active');
    }
}
