<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MpPriceCanaryCertification extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'readiness_passed'             => 'boolean',
            'readiness_criteria_passed'    => 'array',
            'readiness_criteria_failed'    => 'array',
            'readiness_criteria_warnings'  => 'array',
            'readiness_minimum_samples'    => 'array',
            'approval_valid'               => 'boolean',
            'fingerprint_match'            => 'boolean',
            'emergency_stop_active'        => 'boolean',
            'manual_lock_active'           => 'boolean',
            'listing_price_changed'        => 'boolean',
            'audit_created'                => 'boolean',
            'notification_created'         => 'boolean',
            'certification_report_json'    => 'array',
            'certified_at'                 => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(MpPriceCanaryApproval::class, 'approval_id');
    }

    public function isCertified(): bool
    {
        return $this->certification_result === 'certified_zero_write';
    }

    /**
     * Mask a barcode for safe display: show first 4 and last 3 chars.
     */
    public static function maskBarcode(string $barcode): string
    {
        $len = strlen($barcode);
        if ($len <= 7) {
            return str_repeat('*', $len);
        }
        return substr($barcode, 0, 4) . str_repeat('*', $len - 7) . substr($barcode, -3);
    }
}
