<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceQuestionMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketplace_question_id',
        'direction',
        'external_message_id',
        'body',
        'attachments_json',
        'sent_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'attachments_json' => 'array',
            'sent_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(MarketplaceQuestion::class, 'marketplace_question_id');
    }
}
