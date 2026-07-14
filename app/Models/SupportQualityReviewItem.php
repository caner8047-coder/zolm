<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportQualityReviewItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'support_quality_review_id', 'category', 'score', 'comment',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(SupportQualityReview::class, 'support_quality_review_id');
    }
}
