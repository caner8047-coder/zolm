<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelClaimItem extends Model
{
    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'raw_payload' => 'array',
        'price' => 'decimal:2',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(ChannelClaim::class, 'claim_id');
    }
}
