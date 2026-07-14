<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportLaunchEvent extends Model
{
    protected $fillable = [
        'store_id', 'launch_plan_id', 'event_type', 'details_json',
    ];

    protected $casts = [
        'details_json' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SupportLaunchPlan::class, 'launch_plan_id');
    }
}
