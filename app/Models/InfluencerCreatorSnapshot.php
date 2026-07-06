<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfluencerCreatorSnapshot extends Model
{
    protected $fillable = [
        'campaign_id',
        'influencer_profile_id',
        'import_batch_id',
        'period_start',
        'period_end',
        'captured_at',
        'link_visits',
        'sales_total',
        'revenue_total',
        'new_customers',
        'estimated_payment',
        'actual_payment',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'captured_at' => 'datetime',
        'revenue_total' => 'decimal:2',
        'estimated_payment' => 'decimal:2',
        'actual_payment' => 'decimal:2',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class);
    }

    public function influencerProfile(): BelongsTo
    {
        return $this->belongsTo(InfluencerProfile::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(AdImportBatch::class, 'import_batch_id');
    }
}
