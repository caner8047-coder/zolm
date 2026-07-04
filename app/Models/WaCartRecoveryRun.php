<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaCartRecoveryRun extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'cart_id', 'stage', 'status', 'scheduled_at',
        'sent_at', 'cancelled_at', 'cancel_reason',
        'outbox_id', 'coupon_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(WaAbandonedCart::class, 'cart_id');
    }

    public function outbox(): BelongsTo
    {
        return $this->belongsTo(WaOutbox::class, 'outbox_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(WaCoupon::class, 'coupon_id');
    }
}
