<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaOnboardingFlow extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id', 'store_id', 'flow_type', 'status',
        'current_step', 'steps_config', 'started_at',
        'completed_at', 'exit_reason',
    ];

    protected function casts(): array
    {
        return [
            'steps_config' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WaContact::class, 'contact_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WaOnboardingStep::class, 'flow_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
