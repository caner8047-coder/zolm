<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfluencerCampaignMember extends Model
{
    protected $fillable = [
        'campaign_id',
        'influencer_profile_id',
        'campaign_role',
        'link_url',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'campaign_id');
    }

    public function influencerProfile(): BelongsTo
    {
        return $this->belongsTo(InfluencerProfile::class);
    }
}
