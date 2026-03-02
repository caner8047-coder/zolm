<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MpTransaction extends Model
{
    protected $fillable = [
        'period_id', 'transaction_date', 'document_number', 'order_number',
        'transaction_type', 'description', 'debt', 'credit', 'balance', 'is_matched',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'debt'             => 'decimal:2',
        'credit'           => 'decimal:2',
        'balance'          => 'decimal:2',
        'is_matched'       => 'boolean',
    ];

    // ─── Relationships ──────────────────────────────────────────

    public function period(): BelongsTo
    {
        return $this->belongsTo(MpPeriod::class, 'period_id');
    }

    // ─── Scopes ─────────────────────────────────────────────────

    public function scopeByOrderNumber($query, string $orderNumber)
    {
        return $query->where('order_number', $orderNumber);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('transaction_type', 'like', "%{$type}%");
    }

    public function scopeDebits($query)
    {
        return $query->where('debt', '>', 0);
    }

    public function scopeCredits($query)
    {
        return $query->where('credit', '>', 0);
    }

    public function scopeUnmatched($query)
    {
        return $query->where('is_matched', false);
    }

    // ─── Accessors ──────────────────────────────────────────────

    /**
     * Net tutar (Alacak - Borç)
     */
    public function getNetAmountAttribute(): float
    {
        return (float) $this->credit - (float) $this->debt;
    }

    /**
     * İşlem tipi kısa adı (UI badge)
     */
    public function getTypeShortAttribute(): string
    {
        return match (true) {
            str_contains($this->transaction_type, 'Komisyon') => 'Komisyon',
            str_contains($this->transaction_type, 'Kargo')    => 'Kargo',
            str_contains($this->transaction_type, 'İade')     => 'İade',
            str_contains($this->transaction_type, 'Barem')    => 'Barem',
            str_contains($this->transaction_type, 'Ağır')     => 'Ağır Kargo',
            default => $this->transaction_type,
        };
    }
}
