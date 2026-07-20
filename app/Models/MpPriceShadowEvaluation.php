<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MpPriceShadowEvaluation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'evaluated_at' => 'datetime',
            'actual_buybox_price_after' => 'decimal:2',
            'price_deviation' => 'decimal:2',
            'would_win_buybox' => 'boolean',
            'would_preserve_margin' => 'boolean',
            'was_unnecessary_drop' => 'boolean',
            'was_raise_opportunity_correct' => 'boolean',
            'evaluation_notes' => 'array',
        ];
    }

    public function shadowRecord(): BelongsTo
    {
        return $this->belongsTo(MpPriceShadowRecord::class, 'shadow_record_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }
}
