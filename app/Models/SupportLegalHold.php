<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportLegalHold extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'customer_id', 'customer_hash', 'reason', 'active',
    ];

    protected $casts = [
        'customer_id' => 'encrypted',
        'active' => 'boolean',
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
