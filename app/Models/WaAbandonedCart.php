<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaAbandonedCart extends Model
{
    use HasFactory;

    const STATUS_ACTIVE = 'active';
    const STATUS_WAITING = 'waiting';
    const STATUS_STAGE_1 = 'stage_1_sent';
    const STATUS_STAGE_2 = 'stage_2_sent';
    const STATUS_STAGE_3 = 'stage_3_sent';
    const STATUS_RECOVERED = 'recovered';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_UNAVAILABLE = 'unavailable';

    protected $fillable = [
        'store_id', 'contact_id', 'wc_customer_id', 'cart_key_hash',
        'cart_snapshot_json', 'cart_total_snapshot', 'currency',
        'status', 'last_activity_at', 'first_detected_at', 'next_action_at',
        'recovery_token_hash', 'recovery_expires_at',
        'recovered_order_id', 'recovered_at',
    ];

    protected function casts(): array
    {
        return [
            'cart_snapshot_json' => 'array',
            'cart_total_snapshot' => 'decimal:2',
            'last_activity_at' => 'datetime',
            'first_detected_at' => 'datetime',
            'next_action_at' => 'datetime',
            'recovery_expires_at' => 'datetime',
            'recovered_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WaContact::class, 'contact_id');
    }

    public function recoveryRuns(): HasMany
    {
        return $this->hasMany(WaCartRecoveryRun::class, 'cart_id');
    }

    public function recoveredOrder(): BelongsTo
    {
        return $this->belongsTo(ChannelOrder::class, 'recovered_order_id');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_WAITING, self::STATUS_STAGE_1, self::STATUS_STAGE_2]);
    }

    public function scopeForRecovery($query)
    {
        return $query->active()
            ->whereNotNull('next_action_at')
            ->where('next_action_at', '<=', now());
    }

    public static function hashCartKey(string $cartKey): string
    {
        return hash('sha256', $cartKey);
    }
}
