<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssistantQuery extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'meta_json'        => 'array',
            'filters_json'     => 'array',
            'sources_json'     => 'array',
            'suggestions_json' => 'array',
            'answered_at'      => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionSuggestions(): HasMany
    {
        return $this->hasMany(AssistantActionSuggestion::class);
    }
}
