<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportDataSubjectRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'customer_id', 'customer_hash', 'request_type', 'details_json',
        'approval_request_id', 'status', 'requested_at', 'completed_at',
    ];

    protected $casts = [
        'customer_id' => 'encrypted',
        'details_json' => 'array',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(fn (self $model) => $model->customer_hash = hash('sha256', (string) $model->customer_id));
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(SupportApprovalRequest::class, 'approval_request_id');
    }
}
