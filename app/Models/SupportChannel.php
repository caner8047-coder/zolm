<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'key', 'name', 'status', 'is_enabled',
        'config_json', 'last_sync_at', 'last_health_check_at',
    ];

    protected function casts(): array
    {
        return [
            'config_json' => 'array',
            'is_enabled' => 'boolean',
            'last_sync_at' => 'datetime',
            'last_health_check_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function capabilities(): HasMany
    {
        return $this->hasMany(SupportChannelCapability::class, 'support_channel_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(SupportConversation::class, 'support_channel_id');
    }

    public function syncCursors(): HasMany
    {
        return $this->hasMany(SupportSyncCursor::class, 'support_channel_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_enabled', true)->where('status', 'active');
    }

    public function hasCapability(string $capability): bool
    {
        return $this->capabilities()
            ->where('capability', $capability)
            ->where('status', 'available')
            ->exists();
    }
}
