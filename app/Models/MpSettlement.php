<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MpSettlement extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'transaction_date' => 'date',
        'settlement_date'  => 'date',
        'due_date'         => 'date',
        'commission_rate'  => 'decimal:2',
        'ty_hakedis'       => 'decimal:2',
        'seller_hakedis'   => 'decimal:2',
        'total_amount'     => 'decimal:2',
        'is_reconciled'    => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(MpPeriod::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MpOrder::class);
    }
}
