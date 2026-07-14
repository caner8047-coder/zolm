<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportOnboardingState extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'current_step',
        'steps_completed',
        'status',
        'recommended_mode',
        'connection_started_at',
        'first_verified_draft_at',
        'verification_duration_seconds',
        'last_verified_at',
        'catalog_verified_at',
        'diagnostics_json',
        'catalog_dry_run_json',
        'support_bundle_json',
        'support_requested_at',
        'sample_question',
        'sample_result_json',
    ];

    protected function casts(): array
    {
        return [
            'steps_completed' => 'array',
            'current_step' => 'integer',
            'connection_started_at' => 'datetime',
            'first_verified_draft_at' => 'datetime',
            'verification_duration_seconds' => 'integer',
            'last_verified_at' => 'datetime',
            'catalog_verified_at' => 'datetime',
            'diagnostics_json' => 'array',
            'catalog_dry_run_json' => 'array',
            'support_bundle_json' => 'array',
            'support_requested_at' => 'datetime',
            'sample_result_json' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }
}
