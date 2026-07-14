<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportProjectionCursor extends Model
{
    protected $fillable = [
        'store_id', 'channel_id', 'channel_type', 'cursor_key',
        'last_seen_external_id', 'last_synced_at', 'checksum_snapshot', 'status',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }
}
