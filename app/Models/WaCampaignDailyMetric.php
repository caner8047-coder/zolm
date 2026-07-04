<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaCampaignDailyMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id', 'metric_date',
        'recipients_queued', 'recipients_sent', 'recipients_delivered',
        'recipients_read', 'recipients_clicked', 'recipients_converted',
        'recipients_skipped', 'recipients_failed',
        'revenue_attributed', 'coupons_created', 'coupons_used',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'revenue_attributed' => 'decimal:2',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WaCampaign::class, 'campaign_id');
    }
}
