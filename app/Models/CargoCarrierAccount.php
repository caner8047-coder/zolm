<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CargoCarrierAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'legal_entity_id',
        'carrier_code',
        'carrier_name',
        'account_name',
        'customer_code',
        'sender_username',
        'sender_password_encrypted',
        'query_password_encrypted',
        'cod_username',
        'cod_password_encrypted',
        'credentials_encrypted',
        'api_base_url',
        'query_base_url',
        'branch_code',
        'origin_city',
        'origin_district',
        'origin_address',
        'contact_name',
        'contact_phone',
        'is_default',
        'is_active',
        'status',
        'last_verified_at',
        'last_error',
        'settings_json',
    ];

    protected function casts(): array
    {
        return [
            'sender_password_encrypted' => 'encrypted',
            'query_password_encrypted' => 'encrypted',
            'cod_password_encrypted' => 'encrypted',
            'credentials_encrypted' => 'encrypted:array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'last_verified_at' => 'datetime',
            'settings_json' => 'array',
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

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function invoiceLines(): HasMany
    {
        return $this->hasMany(CargoInvoiceLine::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSurat($query)
    {
        return $query->where('carrier_code', 'surat');
    }

    public function hasApiEndpoint(string $key): bool
    {
        $endpoint = data_get($this->settings_json, "endpoints.{$key}")
            ?: data_get(config("cargo.integrations.{$this->carrier_code}.endpoints", []), $key);

        return is_string($endpoint) && trim($endpoint) !== '';
    }
}
