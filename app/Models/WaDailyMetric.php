<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaDailyMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'metric_date', 'channel',
        'messages_queued', 'messages_sent', 'messages_delivered', 'messages_read',
        'messages_failed', 'messages_opted_out',
        'clicks', 'coupon_created', 'coupon_used', 'orders_attributed', 'revenue_attributed',
        'shipping_notifications', 'order_confirmations', 'return_notifications',
        'cart_recovery_sent', 'cart_recovery_recovered',
        'stock_alerts_sent', 'stock_alerts_converted',
        'ai_runs', 'ai_handoffs', 'avg_response_time_ms',
        'support_conversations_opened', 'support_conversations_resolved', 'avg_first_response_minutes',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'revenue_attributed' => 'decimal:2',
            'avg_response_time_ms' => 'decimal:2',
            'avg_first_response_minutes' => 'decimal:2',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }
}
