<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_bank_account' => 'boolean',
            'is_cash_account' => 'boolean',
            'is_ar_account'   => 'boolean',
            'is_ap_account'   => 'boolean',
            'is_system'       => 'boolean',
            'is_active'       => 'boolean',
            'exchange_rate'   => 'decimal:6',
            'meta_json'       => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accountGroup(): BelongsTo
    {
        return $this->belongsTo(AccountGroup::class, 'account_group_id');
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * Bu hesabın normal bakiyesi debit mi?
     */
    public function isDebitNormal(): bool
    {
        return $this->normal_balance === 'debit';
    }

    /**
     * Hesap tipi adı (Türkçe)
     */
    public function typeLabel(): string
    {
        return match ($this->type) {
            'asset'     => 'Varlık',
            'liability' => 'Kaynak',
            'equity'    => 'Öz Kaynak',
            'revenue'   => 'Gelir',
            'expense'   => 'Gider',
            default     => $this->type,
        };
    }

    /**
     * Bu hesabın TRY bakiyesi (posted journal_lines üzerinden).
     * Pozitif bakiye normal_balance yönünde.
     */
    public function balance(?int $legalEntityId = null): float
    {
        $query = $this->journalLines()
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'));

        if ($legalEntityId !== null) {
            $query->whereHas('journalEntry', fn ($q) => $q->where('legal_entity_id', $legalEntityId));
        }

        $debit  = (float) $query->sum('debit_base_amount');
        $credit = (float) $query->sum('credit_base_amount');

        // normal_balance=debit ise borç - alacak; credit ise alacak - borç
        return $this->isDebitNormal()
            ? $debit - $credit
            : $credit - $debit;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
