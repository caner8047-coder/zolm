<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MpInvoice extends Model
{
    protected $fillable = [
        'period_id', 'invoice_number', 'invoice_date', 'invoice_type',
        'net_amount', 'vat_amount', 'vat_rate', 'total_amount', 'description',
    ];

    protected $casts = [
        'invoice_date'  => 'date',
        'net_amount'    => 'decimal:2',
        'vat_amount'    => 'decimal:2',
        'vat_rate'      => 'decimal:2',
        'total_amount'  => 'decimal:2',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(MpPeriod::class, 'period_id');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('invoice_type', 'like', "%{$type}%");
    }
}
