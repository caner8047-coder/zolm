<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendyolBoosterOperationMetric extends Model
{
    protected $fillable = [
        'user_id',
        'route_name',
        'http_method',
        'status_code',
        'duration_ms',
        'release_ring',
        'outcome',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
