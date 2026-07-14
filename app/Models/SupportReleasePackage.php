<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportReleasePackage extends Model
{
    protected $fillable = [
        'store_id', 'title', 'status', 'created_by', 'approved_by', 'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupportReleasePackageItem::class, 'package_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SupportReleaseEvent::class, 'package_id');
    }
}
