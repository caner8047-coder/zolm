<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TrendyolBoosterCollection extends Model
{
    protected $fillable = ['user_id', 'name', 'color', 'sort_order'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            TrendyolBoosterProduct::class,
            'trendyol_booster_collection_items',
        )->withPivot('note')->withTimestamps();
    }
}
