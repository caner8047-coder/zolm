<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportEntitlementEvent extends Model
{
    protected $fillable = [
        'store_id', 'feature', 'status', 'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }
}
