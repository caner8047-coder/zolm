<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportUsageEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'metric',
        'details_json',
    ];

    protected function casts(): array
    {
        return [
            'details_json' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }
}
