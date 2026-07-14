<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportConsentRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'customer_id', 'customer_hash', 'channel_key', 'consent_type', 'status', 'recorded_at',
    ];

    protected $casts = [
        'customer_id' => 'encrypted',
        'recorded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(fn (self $model) => $model->customer_hash = hash('sha256', (string) $model->customer_id));
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }
}
