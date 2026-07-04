<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaContactPreference extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $fillable = [
        'contact_id',
        'store_id',
        'purpose',
        'status',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WaContact::class, 'contact_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function scopeGranted($query, string $purpose)
    {
        return $query->where('purpose', $purpose)->where('status', 'granted');
    }
}
