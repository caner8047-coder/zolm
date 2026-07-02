<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceReportDigestRun extends Model
{
    protected $fillable = [
        'marketplace_report_subscription_id',
        'report_id',
        'user_id',
        'store_id',
        'frequency',
        'period_start',
        'period_end',
        'recipient_email',
        'subject',
        'status',
        'summary_json',
        'payload_json',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'summary_json' => 'array',
            'payload_json' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(MarketplaceReportSubscription::class, 'marketplace_report_subscription_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }
}
