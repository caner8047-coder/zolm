<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdRecommendation extends Model
{
    protected $fillable = [
        'user_id',
        'channel_code',
        'entity_type',
        'entity_id',
        'priority',
        'category',
        'title',
        'description',
        'recommended_action',
        'evidence',
        'expected_impact',
        'confidence_score',
        'status',
        'generated_by',
        'metadata',
    ];

    protected $casts = [
        'evidence' => 'array',
        'expected_impact' => 'array',
        'metadata' => 'array',
        'confidence_score' => 'decimal:4',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AdRecommendationAction::class, 'recommendation_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['new', 'viewed']);
    }
}
