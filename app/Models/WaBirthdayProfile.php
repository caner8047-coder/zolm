<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaBirthdayProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id', 'store_id', 'birth_date',
        'consent_granted', 'consent_at', 'last_birthday_year',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'consent_granted' => 'boolean',
            'consent_at' => 'datetime',
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
