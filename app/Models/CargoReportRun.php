<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CargoReportRun extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'total_desi' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'measurement_amount' => 'decimal:2',
            'grand_total_amount' => 'decimal:2',
            'raw_payload' => 'array',
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

    public function carrierAccount(): BelongsTo
    {
        return $this->belongsTo(CargoCarrierAccount::class, 'cargo_carrier_account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CargoReportLine::class);
    }
}
