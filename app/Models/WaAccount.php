<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WaAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'brand_id',
        'waba_id',
        'phone_number_id',
        'display_phone_number',
        'access_token_encrypted',
        'status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'access_token_encrypted' => 'encrypted',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(WaTemplate::class, 'wa_account_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('status', 'active');
    }

    public function getAccessTokenAttribute(): string
    {
        return (string) $this->access_token_encrypted;
    }
}
