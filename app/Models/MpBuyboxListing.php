<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MpBuyboxListing extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'buybox_price' => 'decimal:2',
            'seller_price' => 'decimal:2',
            'second_price' => 'decimal:2',
            'third_price' => 'decimal:2',
            'seller_rank' => 'integer',
            'has_multiple_sellers' => 'boolean',
            'raw_payload' => 'array',
            'retrieved_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }
}
