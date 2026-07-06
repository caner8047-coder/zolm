<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdAccount extends Model
{
    protected $fillable = [
        'user_id',
        'marketplace',
        'account_name',
        'external_account_id',
        'currency_code',
        'timezone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adCampaigns(): HasMany
    {
        return $this->hasMany(AdCampaign::class);
    }

    public function adImportBatches(): HasMany
    {
        return $this->hasMany(AdImportBatch::class);
    }

    public function marketplaceProductMappings(): HasMany
    {
        return $this->hasMany(MarketplaceProductMapping::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
