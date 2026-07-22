<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MpPriceAction extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'old_price' => 'decimal:2',
            'requested_price' => 'decimal:2',
            'confirmed_price' => 'decimal:2',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(MpPriceRecommendation::class, 'recommendation_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function pushRun(): BelongsTo
    {
        return $this->belongsTo(IntegrationPushRun::class, 'integration_push_run_id');
    }

    public function canRollback(): bool
    {
        return $this->status === 'success'
            && $this->rolled_back_at === null
            && $this->old_price > 0;
    }
}
