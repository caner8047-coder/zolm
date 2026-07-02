<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceReportSubscription extends Model
{
    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_WEEKLY = 'weekly';

    protected $fillable = [
        'user_id',
        'store_id',
        'name',
        'frequency',
        'channels_json',
        'webhook_url',
        'telegram_bot_token',
        'telegram_chat_id',
        'recipients_json',
        'filters_json',
        'sections_json',
        'enabled',
        'send_time',
        'timezone',
        'last_sent_at',
        'next_run_at',
        'last_status',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'channels_json' => 'array',
            'recipients_json' => 'array',
            'filters_json' => 'array',
            'sections_json' => 'array',
            'enabled' => 'boolean',
            'last_sent_at' => 'datetime',
            'next_run_at' => 'datetime',
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

    public function digestRuns(): HasMany
    {
        return $this->hasMany(MarketplaceReportDigestRun::class, 'marketplace_report_subscription_id');
    }
}
