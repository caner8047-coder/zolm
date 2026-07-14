<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportConnectorCertificationRun extends Model
{
    protected $fillable = [
        'store_id',
        'channel_key',
        'certified_by',
        'status',
        'certified_at',
    ];

    protected $casts = [
        'certified_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function certifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'certified_by');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(SupportConnectorCertificationCheck::class, 'run_id');
    }
}
