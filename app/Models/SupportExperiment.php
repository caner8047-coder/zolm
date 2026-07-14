<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportExperiment extends Model
{
    protected $fillable = [
        'store_id', 'name', 'type', 'status', 'description', 'created_by',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(SupportExperimentVariant::class, 'experiment_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(SupportExperimentRun::class, 'experiment_id');
    }
}
