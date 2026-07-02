<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplacePricingScenario extends Model
{
    protected $fillable = [
        'user_id',
        'mp_product_id',
        'channel_listing_id',
        'name',
        'marketplace',
        'currency',
        'input_json',
        'result_json',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'input_json' => 'array',
            'result_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MpProduct::class, 'mp_product_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(ChannelListing::class, 'channel_listing_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
