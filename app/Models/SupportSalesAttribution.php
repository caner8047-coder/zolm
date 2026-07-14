<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportSalesAttribution extends Model
{
    protected $fillable = [
        'store_id', 'conversation_id', 'external_order_hash', 'attribution_method',
        'order_amount', 'currency', 'evidence_json', 'verified_at',
    ];
    protected function casts(): array
    {
        return ['order_amount' => 'decimal:2', 'evidence_json' => 'array', 'verified_at' => 'datetime'];
    }
}
