<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EDocumentLine extends Model
{
    protected $table = 'e_document_lines';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity'        => 'decimal:4',
            'unit_price'      => 'decimal:2',
            'discount_rate'   => 'decimal:4',
            'discount_amount' => 'decimal:2',
            'vat_rate'        => 'decimal:4',
            'vat_amount'      => 'decimal:2',
            'line_subtotal'   => 'decimal:2',
            'line_total'      => 'decimal:2',
            'meta_json'       => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(EDocument::class, 'e_document_id');
    }

    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class);
    }
}
