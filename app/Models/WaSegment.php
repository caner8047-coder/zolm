<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaSegment extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'name', 'description', 'status',
        'rules_json', 'estimated_count', 'last_calculated_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rules_json' => 'array',
            'last_calculated_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(WaCampaign::class, 'segment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
