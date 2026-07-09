<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyLedgerEntry extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'due_date' => 'date',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
            'debit_amount' => 'decimal:2',
            'credit_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'debit_base_amount' => 'decimal:2',
            'credit_base_amount' => 'decimal:2',
            'meta_json' => 'array',
        ];
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

    public function crmContact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class);
    }

    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    public function scopeForParty($query, int $partyId)
    {
        return $query->where('party_id', $partyId);
    }

    /**
     * İmzalı tutar: debit - credit.
     * Pozitif: biz alacaklıyız. Negatif: biz borçluyuz.
     */
    public function signedAmount(): float
    {
        return (float) $this->debit_amount - (float) $this->credit_amount;
    }

    public function isVoid(): bool
    {
        return $this->status === 'voided' || $this->voided_at !== null;
    }
}
