<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceFinanceBridgeRun extends Model
{
    protected $table = 'marketplace_finance_bridge_runs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload_json'  => 'array',
            'result_json'   => 'array',
            'attempted_at'  => 'datetime',
            'completed_at'  => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'marketplace_store_id');
    }

    public function channelOrder(): BelongsTo
    {
        return $this->belongsTo(ChannelOrder::class, 'channel_order_id');
    }

    public function financialEvent(): BelongsTo
    {
        return $this->belongsTo(OrderFinancialEvent::class, 'order_financial_event_id');
    }

    // Helpers
    public function isSucceeded(): bool
    {
        return $this->status === 'succeeded';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isSkipped(): bool
    {
        return $this->status === 'skipped';
    }

    public function isRetryable(): bool
    {
        return in_array($this->status, ['failed', 'skipped'], true);
    }
}
