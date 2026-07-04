<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaCampaignEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id', 'audience_id', 'event_type',
        'payload_json', 'actor_user_id',
    ];

    protected function casts(): array
    {
        return ['payload_json' => 'array'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WaCampaign::class, 'campaign_id');
    }

    public function audience(): BelongsTo
    {
        return $this->belongsTo(WaCampaignAudience::class, 'audience_id');
    }
}
