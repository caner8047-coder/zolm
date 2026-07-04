<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlaDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'name', 'channel', 'priority',
        'first_response_minutes', 'resolution_minutes',
        'business_hours_only', 'business_hours_config', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'business_hours_only' => 'boolean',
            'business_hours_config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(SlaTrack::class, 'sla_definition_id');
    }
}
