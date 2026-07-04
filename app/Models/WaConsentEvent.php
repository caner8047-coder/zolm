<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaConsentEvent extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $fillable = [
        'contact_id',
        'store_id',
        'purpose',
        'action',
        'consent_text_version',
        'source',
        'consent_timestamp',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'consent_timestamp' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WaContact::class, 'contact_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }
}
