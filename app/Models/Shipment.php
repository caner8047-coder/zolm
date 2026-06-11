<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    use HasFactory;

    public const ACTIVE_STATUSES = [
        'draft',
        'ready',
        'label_created',
        'shipped',
        'in_transit',
        'out_for_delivery',
        'exception',
    ];

    public const TERMINAL_STATUSES = [
        'delivered',
        'returned',
        'cancelled',
        'failed',
    ];

    protected $fillable = [
        'user_id',
        'legal_entity_id',
        'store_id',
        'channel_order_id',
        'channel_order_package_id',
        'channel_claim_id',
        'supply_order_id',
        'cargo_carrier_account_id',
        'shipment_no',
        'source_type',
        'direction',
        'flow_type',
        'carrier_code',
        'carrier_name',
        'external_shipment_id',
        'reference_number',
        'order_number',
        'package_number',
        'tracking_number',
        'barcode',
        'status',
        'status_label',
        'customer_name',
        'customer_phone',
        'destination_city',
        'destination_district',
        'destination_address',
        'sender_name',
        'sender_phone',
        'origin_city',
        'origin_district',
        'origin_address',
        'parcel_count',
        'total_desi',
        'total_weight',
        'expected_cost',
        'actual_cost',
        'invoice_cost',
        'cost_delta',
        'currency',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'last_tracked_at',
        'last_event_at',
        'last_error',
        'raw_payload',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'parcel_count' => 'integer',
            'total_desi' => 'decimal:2',
            'total_weight' => 'decimal:2',
            'expected_cost' => 'decimal:2',
            'actual_cost' => 'decimal:2',
            'invoice_cost' => 'decimal:2',
            'cost_delta' => 'decimal:2',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_tracked_at' => 'datetime',
            'last_event_at' => 'datetime',
            'raw_payload' => 'array',
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

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ChannelOrder::class, 'channel_order_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ChannelOrderPackage::class, 'channel_order_package_id');
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(ChannelClaim::class, 'channel_claim_id');
    }

    public function supplyOrder(): BelongsTo
    {
        return $this->belongsTo(SupplyOrder::class);
    }

    public function carrierAccount(): BelongsTo
    {
        return $this->belongsTo(CargoCarrierAccount::class, 'cargo_carrier_account_id');
    }

    public function parcels(): HasMany
    {
        return $this->hasMany(ShipmentParcel::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(ShipmentCost::class);
    }

    public function invoiceLines(): HasMany
    {
        return $this->hasMany(CargoInvoiceLine::class);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function getIsTerminalAttribute(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function getStatusToneAttribute(): string
    {
        return match ($this->status) {
            'delivered' => 'success',
            'exception', 'failed' => 'danger',
            'cancelled', 'returned' => 'warning',
            'shipped', 'in_transit', 'out_for_delivery' => 'info',
            default => 'default',
        };
    }
}
