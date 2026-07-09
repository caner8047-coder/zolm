<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Party extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_blacklisted' => 'boolean',
            'meta_json' => 'array',
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

    public function roles(): HasMany
    {
        return $this->hasMany(PartyRole::class, 'party_id');
    }

    public function identities(): HasMany
    {
        return $this->hasMany(PartyIdentity::class, 'party_id');
    }

    public function crmContacts(): HasMany
    {
        return $this->hasMany(CrmContact::class, 'party_id');
    }

    public function customers(): HasMany
    {
        return $this->roles()->where('role', 'customer');
    }

    public function suppliers(): HasMany
    {
        return $this->roles()->where('role', 'supplier');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(PartyLedgerEntry::class, 'party_id');
    }
}
