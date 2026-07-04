<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendyolBoosterReviewFilter extends Model
{
    use HasFactory;

    protected $table = 'trendyol_booster_review_filters';

    protected $fillable = [
        'user_id',
        'name',
        'min_rating',
        'max_rating',
        'min_comment_length',
        'require_photo',
        'exclude_keywords',
        'include_keywords',
        'auto_exclude_spam',
        'is_active',
        'auto_apply_on_push',
    ];

    protected function casts(): array
    {
        return [
            'min_rating' => 'integer',
            'max_rating' => 'integer',
            'min_comment_length' => 'integer',
            'require_photo' => 'boolean',
            'exclude_keywords' => 'array',
            'include_keywords' => 'array',
            'auto_exclude_spam' => 'boolean',
            'is_active' => 'boolean',
            'auto_apply_on_push' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
