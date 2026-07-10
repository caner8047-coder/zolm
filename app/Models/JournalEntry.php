<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'entry_date'    => 'date',
            'due_date'      => 'date',
            'posted_at'     => 'datetime',
            'voided_at'     => 'datetime',
            'exchange_rate' => 'decimal:6',
            'meta_json'     => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('sort_order');
    }

    /**
     * Fiş iptal edilmiş mi?
     */
    public function isVoid(): bool
    {
        return $this->status === 'voided' || $this->voided_at !== null;
    }

    /**
     * Fişin toplam borç tutarı (TRY).
     */
    public function totalDebit(): float
    {
        return (float) $this->lines->sum('debit_base_amount');
    }

    /**
     * Fişin toplam alacak tutarı (TRY).
     */
    public function totalCredit(): float
    {
        return (float) $this->lines->sum('credit_base_amount');
    }

    /**
     * Fiş dengeli mi? (borç = alacak, 0.005 tolerans)
     */
    public function isBalanced(): bool
    {
        return abs($this->totalDebit() - $this->totalCredit()) < 0.005;
    }

    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('entry_type', $type);
    }
}
