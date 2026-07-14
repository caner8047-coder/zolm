<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportCommercialPlan extends Model
{
    protected $fillable = [
        'name', 'slug', 'entitlements',
    ];

    protected $casts = [
        'entitlements' => 'array',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(SupportCommercialSubscription::class, 'plan_id');
    }
}
