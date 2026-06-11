<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceQuestionAnswerLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketplace_question_id',
        'user_id',
        'template_id',
        'rule_id',
        'source',
        'answer_text',
        'status',
        'external_answer_id',
        'error_message',
        'response_json',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'response_json' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(MarketplaceQuestion::class, 'marketplace_question_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MarketplaceQuestionTemplate::class, 'template_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(MarketplaceQuestionRule::class, 'rule_id');
    }
}
