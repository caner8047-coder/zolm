<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MpProductChangeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'mp_product_id',
        'channel_listing_id',
        'store_id',
        'batch_id',
        'change_scope',
        'field_key',
        'field_label',
        'value_type',
        'old_value',
        'new_value',
        'old_value_number',
        'new_value_number',
        'delta_number',
        'delta_percent',
        'source',
        'source_label',
        'note',
        'old_snapshot',
        'new_snapshot',
        'metadata',
        'changed_by',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'old_value_number' => 'decimal:4',
            'new_value_number' => 'decimal:4',
            'delta_number' => 'decimal:4',
            'delta_percent' => 'decimal:4',
            'old_snapshot' => 'array',
            'new_snapshot' => 'array',
            'metadata' => 'array',
            'changed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MpProduct::class, 'mp_product_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(ChannelListing::class, 'channel_listing_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
