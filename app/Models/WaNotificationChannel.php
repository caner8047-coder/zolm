<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaNotificationChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'key', 'name', 'type', 'status',
        'config_json', 'is_enabled', 'last_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'config_json' => 'array',
            'is_enabled' => 'boolean',
            'last_sent_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(WaNotificationTemplate::class, 'channel_id');
    }

    public function sends(): HasMany
    {
        return $this->hasMany(WaNotificationSend::class, 'channel_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_enabled', true)->where('status', 'configured');
    }
}
