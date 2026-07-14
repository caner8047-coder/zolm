<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportApiAccessLog extends Model
{
    protected $fillable = [
        'api_client_id', 'api_token_id', 'store_id', 'method', 'uri', 'response_status', 'ip_address', 'request_payload_redacted',
    ];

    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(SupportApiClient::class, 'api_client_id');
    }

    public function apiToken(): BelongsTo
    {
        return $this->belongsTo(SupportApiToken::class, 'api_token_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }
}
