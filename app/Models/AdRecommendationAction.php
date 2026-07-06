<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdRecommendationAction extends Model
{
    protected $fillable = [
        'recommendation_id',
        'user_id',
        'action',
        'note',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(AdRecommendation::class, 'recommendation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
