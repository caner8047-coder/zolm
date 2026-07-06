<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InfluencerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'platform',
        'handle',
        'display_name',
        'profile_url',
        'avatar_url',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaignMembers(): HasMany
    {
        return $this->hasMany(InfluencerCampaignMember::class);
    }

    public function creatorSnapshots(): HasMany
    {
        return $this->hasMany(InfluencerCreatorSnapshot::class);
    }

    public function productSales(): HasMany
    {
        return $this->hasMany(InfluencerProductSale::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
