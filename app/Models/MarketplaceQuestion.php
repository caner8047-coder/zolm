<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'channel_product_id',
        'channel_listing_id',
        'channel_order_id',
        'external_question_id',
        'question_type',
        'status',
        'customer_name',
        'customer_external_id',
        'product_name',
        'product_sku',
        'product_barcode',
        'product_url',
        'question_text',
        'answer_text',
        'ai_suggested_answer',
        'ai_confidence',
        'ai_status',
        'matched_rule_id',
        'answered_by_user_id',
        'asked_at',
        'answered_at',
        'expires_at',
        'last_synced_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'ai_confidence' => 'integer',
            'asked_at' => 'datetime',
            'answered_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function channelProduct(): BelongsTo
    {
        return $this->belongsTo(ChannelProduct::class, 'channel_product_id');
    }

    public function channelListing(): BelongsTo
    {
        return $this->belongsTo(ChannelListing::class, 'channel_listing_id');
    }

    public function channelOrder(): BelongsTo
    {
        return $this->belongsTo(ChannelOrder::class, 'channel_order_id');
    }

    public function matchedRule(): BelongsTo
    {
        return $this->belongsTo(MarketplaceQuestionRule::class, 'matched_rule_id');
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MarketplaceQuestionMessage::class)->orderBy('sent_at')->orderBy('id');
    }

    public function answerLogs(): HasMany
    {
        return $this->hasMany(MarketplaceQuestionAnswerLog::class)->latest();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'pending', 'draft']);
    }
}
