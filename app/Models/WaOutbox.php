<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaOutbox extends Model
{
    use HasFactory;

    protected $table = 'wa_outbox';

    const STATUS_QUEUED = 'queued';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SENT = 'sent';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_READ = 'read';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    const TERMINAL_STATUSES = [
        self::STATUS_SENT,
        self::STATUS_DELIVERED,
        self::STATUS_READ,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
    ];

    const STATUS_ORDER = [
        self::STATUS_QUEUED => 0,
        self::STATUS_PROCESSING => 1,
        self::STATUS_SENT => 2,
        self::STATUS_DELIVERED => 3,
        self::STATUS_READ => 4,
        self::STATUS_FAILED => -1,
        self::STATUS_CANCELLED => -1,
    ];

    protected $fillable = [
        'contact_id',
        'store_id',
        'idempotency_key',
        'message_type',
        'template_name',
        'template_language',
        'template_params_json',
        'body_text',
        'priority',
        'status',
        'scheduled_at',
        'next_retry_at',
        'retry_count',
        'max_retries',
        'error_message',
        'error_code',
        'meta_message_id',
        'automation_key',
        'related_order_id',
        'related_cart_id',
    ];

    protected function casts(): array
    {
        return [
            'template_params_json' => 'array',
            'scheduled_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WaContact::class, 'contact_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function deliveries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WaMessageDelivery::class, 'outbox_id');
    }

    public function scopeQueued($query)
    {
        return $query->where('status', self::STATUS_QUEUED)
            ->where(function ($q) {
                $q->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function canProgressTo(string $newStatus): bool
    {
        $currentOrder = self::STATUS_ORDER[$this->status] ?? -1;
        $newOrder = self::STATUS_ORDER[$newStatus] ?? -1;

        // sent → delivered → read sırası korunur; geriye düşmez
        return $newOrder > $currentOrder;
    }
}
