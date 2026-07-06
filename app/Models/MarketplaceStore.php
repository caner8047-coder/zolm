<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MarketplaceStore extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'legal_entity_id',
        'marketplace',
        'store_name',
        'store_code',
        'seller_id',
        'status',
        'timezone',
        'currency',
        'is_active',
        'uses_own_cargo',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'uses_own_cargo' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function connection(): HasOne
    {
        return $this->hasOne(IntegrationConnection::class, 'store_id');
    }

    public function waAccount(): HasOne
    {
        return $this->hasOne(WaAccount::class, 'store_id');
    }

    public function syncProfile(): HasOne
    {
        return $this->hasOne(IntegrationSyncProfile::class, 'store_id');
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(IntegrationSyncRun::class, 'store_id');
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(IntegrationWebhookEvent::class, 'store_id');
    }

    public function pushRuns(): HasMany
    {
        return $this->hasMany(IntegrationPushRun::class, 'store_id');
    }

    public function orderActionRuns(): HasMany
    {
        return $this->hasMany(IntegrationOrderActionRun::class, 'store_id');
    }

    public function channelProducts(): HasMany
    {
        return $this->hasMany(ChannelProduct::class, 'store_id');
    }

    public function channelListings(): HasMany
    {
        return $this->hasMany(ChannelListing::class, 'store_id');
    }

    public function channelOrders(): HasMany
    {
        return $this->hasMany(ChannelOrder::class, 'store_id');
    }

    public function marketplaceQuestions(): HasMany
    {
        return $this->hasMany(MarketplaceQuestion::class, 'store_id');
    }
}
