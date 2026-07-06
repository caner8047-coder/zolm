<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfluencerCampaignPayment extends Model
{
    protected $fillable = [
        'campaign_id',
        'payment_type',
        'commission_rate',
        'amount_ex_vat',
        'vat_amount',
        'amount_inc_vat',
        'payment_status',
        'source',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'amount_ex_vat' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'amount_inc_vat' => 'decimal:2',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'campaign_id');
    }
}
