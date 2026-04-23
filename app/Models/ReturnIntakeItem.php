<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReturnIntakeItem extends Model
{
    public const INTAKE_LABELS = [
        'undamaged' => 'Hasarsız iade',
        'damaged' => 'Hasarlı iade',
    ];

    public const STATUS_LABELS = [
        'queued' => 'Analiz kuyruğunda',
        'analyzing' => 'Analiz ediliyor',
        'matched' => 'Eşleşti',
        'ready_for_decision' => 'Karar için hazır',
        'needs_review' => 'Manuel inceleme',
        'decisioned' => 'Karar verildi',
        'failed' => 'Hata oluştu',
    ];

    public const CONDITION_LABELS = [
        'undamaged' => 'Hasarsız',
        'damaged' => 'Hasarlı',
        'unknown' => 'Belirsiz',
    ];

    public const PRODUCT_VERIFICATION_LABELS = [
        'matched' => 'Ürün doğrulandı',
        'mismatch' => 'Ürün uyumsuz',
        'unverified' => 'Ürün doğrulanmadı',
    ];

    public const DECISION_LABELS = [
        'pending' => 'Karar bekliyor',
        'approved' => 'Onaylandı',
        'rejected' => 'Reddedildi',
        'restocked' => 'Stoka alındı',
        'scrapped' => 'Hurdaya ayrıldı',
        'needs_review' => 'Manuel inceleme',
    ];

    public const SUGGESTED_DECISION_LABELS = [
        'approve_marketplace' => 'Pazaryerinde onayla',
        'reject_marketplace' => 'Pazaryerinde reddet',
        'restock' => 'Stoka al',
        'scrap' => 'Hurdaya ayır',
        'manual_review' => 'Manuel incele',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'matching_confidence' => 'float',
            'suggested_confidence' => 'float',
            'arrived_at' => 'datetime',
            'analysis_started_at' => 'datetime',
            'analysis_completed_at' => 'datetime',
            'raw_summary_json' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ReturnIntakeBatch::class, 'batch_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(ChannelClaim::class, 'channel_claim_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ChannelOrder::class, 'channel_order_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ChannelOrderPackage::class, 'channel_order_package_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ReturnIntakeMedia::class, 'return_intake_item_id');
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(ReturnIntakeAnalysis::class, 'return_intake_item_id');
    }

    public function latestAnalysis(): HasOne
    {
        return $this->hasOne(ReturnIntakeAnalysis::class, 'return_intake_item_id')->latestOfMany();
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ReturnIntakeDecision::class, 'return_intake_item_id');
    }

    public function latestDecision(): HasOne
    {
        return $this->hasOne(ReturnIntakeDecision::class, 'return_intake_item_id')->latestOfMany();
    }

    public function intakeLabel(): string
    {
        return self::INTAKE_LABELS[$this->intake_type] ?? $this->intake_type;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->intake_status] ?? $this->intake_status;
    }

    public function conditionLabel(): string
    {
        return self::CONDITION_LABELS[$this->condition_status] ?? $this->condition_status;
    }

    public function productVerificationLabel(): string
    {
        return self::PRODUCT_VERIFICATION_LABELS[$this->product_verification_status] ?? $this->product_verification_status;
    }

    public function decisionLabel(): string
    {
        return self::DECISION_LABELS[$this->decision_status] ?? $this->decision_status;
    }

    public function suggestedDecisionLabel(): ?string
    {
        if (!$this->suggested_decision) {
            return null;
        }

        return self::SUGGESTED_DECISION_LABELS[$this->suggested_decision] ?? $this->suggested_decision;
    }
}
