<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosShift extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'opened_at'                => 'datetime',
            'closed_at'                => 'datetime',
            'opening_balance'          => 'decimal:2',
            'closing_balance'          => 'decimal:2',
            'expected_closing_balance' => 'decimal:2',
            'difference_amount'        => 'decimal:2',
            'meta_json'                => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class, 'pos_terminal_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(PosSale::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    // Helper metotlar
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
