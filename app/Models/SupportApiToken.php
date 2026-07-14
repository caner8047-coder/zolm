<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportApiToken extends Model
{
    protected $fillable = [
        'api_client_id', 'token_prefix', 'token_hash', 'scopes', 'store_ids', 'expires_at', 'last_used_at', 'revoked_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'store_ids' => 'array',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(SupportApiClient::class, 'api_client_id');
    }

    public function isValid(): bool
    {
        if ($this->revoked_at) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
