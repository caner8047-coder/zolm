<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'provider',
        'auth_type',
        'credentials_encrypted',
        'webhook_secret',
        'webhook_url',
        'api_base_url',
        'status',
        'last_verified_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'credentials_encrypted' => 'encrypted:array',
            'webhook_secret' => 'encrypted',
            'last_verified_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }
}
