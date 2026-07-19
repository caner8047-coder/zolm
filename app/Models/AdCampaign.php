<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AdCampaign extends Model
{
    protected $fillable = [
        'user_id',
        'ad_account_id',
        'channel_code',
        'external_campaign_id',
        'campaign_identity_hash',
        'campaign_key',
        'name',
        'status',
        'targeting_type',
        'start_at',
        'end_at',
        'daily_budget',
        'total_budget',
        'remaining_budget',
        'bid_strategy',
        'selected_gbm',
        'recommended_gbm',
        'actual_gbm',
        'actual_cpc',
        'redirect_url',
        'metadata',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'daily_budget' => 'decimal:2',
        'total_budget' => 'decimal:2',
        'remaining_budget' => 'decimal:2',
        'selected_gbm' => 'decimal:4',
        'recommended_gbm' => 'decimal:4',
        'actual_gbm' => 'decimal:4',
        'actual_cpc' => 'decimal:4',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class);
    }

    public function adCampaignProducts(): HasMany
    {
        return $this->hasMany(AdCampaignProduct::class, 'campaign_id');
    }

    public function adCampaignSnapshots(): HasMany
    {
        return $this->hasMany(AdCampaignSnapshot::class, 'campaign_id');
    }

    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(AdCampaignSnapshot::class, 'campaign_id')->latestOfMany('captured_at');
    }

    public function adProductSnapshots(): HasMany
    {
        return $this->hasMany(AdProductSnapshot::class, 'campaign_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
