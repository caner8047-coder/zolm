<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaCampaignAudience extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id', 'contact_id', 'store_id', 'snapshot_data_json',
        'eligibility_status', 'exclusion_reason',
        'outbox_id', 'coupon_id',
        'queued_at', 'sent_at', 'clicked_at', 'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_data_json' => 'array',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'clicked_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WaCampaign::class, 'campaign_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WaContact::class, 'contact_id');
    }

    public function outbox(): BelongsTo
    {
        return $this->belongsTo(WaOutbox::class, 'outbox_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(WaCoupon::class, 'coupon_id');
    }
}
