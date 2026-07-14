<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportSuccessTask extends Model
{
    protected $fillable = [
        'store_id', 'snapshot_id', 'task_type', 'status',
        'description', 'resolved_by', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(SupportSuccessSnapshot::class, 'snapshot_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
