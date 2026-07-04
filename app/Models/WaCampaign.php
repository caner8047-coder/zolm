<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaCampaign extends Model
{
    use HasFactory;

    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_APPROVED = 'approved';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_RUNNING = 'running';
    const STATUS_PAUSED = 'paused';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'store_id', 'wa_account_id', 'segment_id', 'name', 'description',
        'status', 'template_id', 'template_params_json',
        'schedule_at', 'started_at', 'paused_at', 'completed_at', 'cancelled_at',
        'attribution_window_days', 'quiet_hours_enabled',
        'batch_size', 'batch_delay_seconds', 'frequency_cap_override',
        'created_by', 'approved_by', 'approved_at', 'cancellation_reason',
        'coupon_enabled', 'coupon_type', 'coupon_value', 'coupon_minimum_spend',
        'coupon_expiry_hours', 'coupon_usage_limit',
        'total_recipients', 'total_sent', 'total_delivered', 'total_read',
        'total_clicked', 'total_converted', 'total_revenue',
    ];

    protected function casts(): array
    {
        return [
            'template_params_json' => 'array',
            'schedule_at' => 'datetime',
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'approved_at' => 'datetime',
            'quiet_hours_enabled' => 'boolean',
            'coupon_enabled' => 'boolean',
            'coupon_value' => 'decimal:2',
            'coupon_minimum_spend' => 'decimal:2',
            'total_revenue' => 'decimal:2',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(WaAccount::class, 'wa_account_id');
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(WaSegment::class, 'segment_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WaTemplate::class, 'template_id');
    }

    public function audiences(): HasMany
    {
        return $this->hasMany(WaCampaignAudience::class, 'campaign_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(WaCampaignEvent::class, 'campaign_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PENDING_APPROVAL], true);
    }

    public function isRunnable(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_SCHEDULED, self::STATUS_RUNNING], true);
    }
}
