<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CargoReportLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'pieces' => 'integer',
            'desi' => 'decimal:2',
            'measurement_desi' => 'decimal:2',
            'measurement_kg' => 'decimal:2',
            'amount' => 'decimal:2',
            'measurement_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'amount_without_vat' => 'decimal:2',
            'document_date' => 'datetime',
            'carrier_created_at' => 'datetime',
            'last_event_at' => 'datetime',
            'delivered_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(CargoReportRun::class, 'cargo_report_run_id');
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
}
