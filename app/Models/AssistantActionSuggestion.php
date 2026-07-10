<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asistan aksiyon önerisi (salt-okunur; hiçbir finansal işlem başlatmaz).
 */
class AssistantActionSuggestion extends Model
{
    protected $table = 'assistant_action_suggestions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload_json'  => 'array',
            'reviewed_at'   => 'datetime',
            'dismissed_at'  => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assistantQuery(): BelongsTo
    {
        return $this->belongsTo(AssistantQuery::class);
    }
}
