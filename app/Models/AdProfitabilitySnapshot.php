<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdProfitabilitySnapshot extends Model
{
    protected $fillable = [
        'user_id',
        'campaign_id',
        'product_id',
        'keyword_snapshot_id',
        'influencer_profile_id',
        'period_start',
        'period_end',
        'net_revenue',
        'product_cost',
        'marketplace_commission',
        'shipping_cost',
        'packaging_cost',
        'discount_cost',
        'return_cost',
        'ad_spend',
        'influencer_cost',
        'gross_profit',
        'contribution_profit_before_ads',
        'net_contribution_profit',
        'net_margin_percent',
        'break_even_roas',
        'calculation_status',
        'missing_inputs',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'net_revenue' => 'decimal:2',
        'product_cost' => 'decimal:2',
        'marketplace_commission' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'packaging_cost' => 'decimal:2',
        'discount_cost' => 'decimal:2',
        'return_cost' => 'decimal:2',
        'ad_spend' => 'decimal:2',
        'influencer_cost' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'contribution_profit_before_ads' => 'decimal:2',
        'net_contribution_profit' => 'decimal:2',
        'net_margin_percent' => 'decimal:4',
        'break_even_roas' => 'decimal:4',
        'missing_inputs' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function keywordSnapshot(): BelongsTo
    {
        return $this->belongsTo(AdKeywordSnapshot::class, 'keyword_snapshot_id');
    }

    public function influencerProfile(): BelongsTo
    {
        return $this->belongsTo(InfluencerProfile::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function isComplete(): bool
    {
        return $this->calculation_status === 'complete';
    }

    public function isPartial(): bool
    {
        return $this->calculation_status === 'partial';
    }

    public function isInsufficientData(): bool
    {
        return $this->calculation_status === 'insufficient_data';
    }
}
