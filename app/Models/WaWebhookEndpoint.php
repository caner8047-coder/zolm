<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaWebhookEndpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'name', 'provider', 'url', 'secret_encrypted',
        'status', 'config_json', 'is_active',
        'last_received_at', 'last_health_check_at',
    ];

    protected function casts(): array
    {
        return [
            'config_json' => 'array',
            'is_active' => 'boolean',
            'secret_encrypted' => 'encrypted',
            'last_received_at' => 'datetime',
            'last_health_check_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WaWebhookLog::class, 'endpoint_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('status', 'active');
    }
}
