<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaAbTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'segment_id', 'name', 'status',
        'variants_json', 'traffic_split', 'primary_metric',
        'started_at', 'completed_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'variants_json' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(WaSegment::class, 'segment_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(WaAbTestResult::class, 'ab_test_id');
    }
}
