<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'order_date'      => 'date',
            'total_amount'    => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'exchange_rate'   => 'decimal:6',
            'approved_at'     => 'datetime',
            'cancelled_at'    => 'datetime',
            'due_date'        => 'date',
            'meta_json'       => 'array',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
