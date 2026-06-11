<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceQuestionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_id',
        'template_id',
        'name',
        'match_type',
        'keywords_json',
        'response_text',
        'action_mode',
        'requires_approval',
        'priority',
        'is_active',
        'trigger_count',
        'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'keywords_json' => 'array',
            'requires_approval' => 'boolean',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'trigger_count' => 'integer',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MarketplaceQuestionTemplate::class, 'template_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
