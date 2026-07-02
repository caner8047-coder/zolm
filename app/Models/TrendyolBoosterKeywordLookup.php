<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendyolBoosterKeywordLookup extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'keyword',
        'keyword_hash',
        'source_url',
        'result_count',
        'top_products',
        'raw_payload',
        'searched_at',
    ];

    protected function casts(): array
    {
        return [
            'result_count' => 'integer',
            'top_products' => 'array',
            'raw_payload' => 'array',
            'searched_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
