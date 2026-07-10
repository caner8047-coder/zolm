<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'debit_amount'       => 'decimal:2',
            'credit_amount'      => 'decimal:2',
            'exchange_rate'      => 'decimal:6',
            'debit_base_amount'  => 'decimal:2',
            'credit_base_amount' => 'decimal:2',
            'meta_json'          => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * İmzalı tutar (normal_balance dikkate alınmadan ham fark).
     * Borç - Alacak.
     */
    public function signedAmount(): float
    {
        return (float) $this->debit_base_amount - (float) $this->credit_base_amount;
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
