<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EDocument extends Model
{
    protected $table = 'e_documents';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'issue_date'             => 'date',
            'due_date'               => 'date',
            'sent_at'                => 'datetime',
            'accepted_at'            => 'datetime',
            'rejected_at'            => 'datetime',
            'cancelled_at'           => 'datetime',
            'provider_request_json'  => 'array',
            'provider_response_json' => 'array',
            'meta_json'              => 'array',
            'subtotal_amount'        => 'decimal:2',
            'discount_amount'        => 'decimal:2',
            'vat_amount'             => 'decimal:2',
            'total_amount'           => 'decimal:2',
            'exchange_rate'          => 'decimal:6',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(EDocumentLine::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(EDocumentEvent::class);
    }

    // Helper metotlar
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function canSend(): bool
    {
        return $this->isDraft();
    }

    public function canCancel(): bool
    {
        return $this->isAccepted();
    }
}
