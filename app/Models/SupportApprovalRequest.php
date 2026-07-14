<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportApprovalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'requester_id', 'action_type', 'details_json',
        'status', 'reason', 'approved_by', 'approved_at',
        'consumed_at', 'consumed_by',
    ];

    protected $casts = [
        'details_json' => 'array',
        'approved_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
