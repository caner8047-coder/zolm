<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaControlGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'name', 'description', 'sample_percentage',
        'criteria_json', 'is_active', 'current_enrolled',
    ];

    protected function casts(): array
    {
        return [
            'sample_percentage' => 'decimal:2',
            'criteria_json' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(WaControlGroupMember::class, 'group_id');
    }
}
