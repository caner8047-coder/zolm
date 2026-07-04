<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaAttributionEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id', 'store_id', 'message_delivery_id', 'campaign_id',
        'audience_id', 'event_type', 'order_id', 'revenue',
        'attribution_window', 'attributed_at', 'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'revenue' => 'decimal:2',
            'attributed_at' => 'datetime',
            'metadata_json' => 'array',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WaContact::class, 'contact_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WaCampaign::class, 'campaign_id');
    }

    public function audience(): BelongsTo
    {
        return $this->belongsTo(WaCampaignAudience::class, 'audience_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ChannelOrder::class, 'order_id');
    }
}
