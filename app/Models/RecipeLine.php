<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecipeLine extends Model
{
    protected $fillable = [
        'recipe_id', 'material_id', 'operation', 'usage_area',
        'calc_type', 'width_cm', 'length_cm', 'height_cm', 'pieces',
        'waste_rate_override', 'fabric_width_override',
        'constant_qty', 'calculated_qty', 'calculated_unit',
        'notes', 'sort_order',
    ];

    protected $casts = [
        'width_cm'              => 'float',
        'length_cm'             => 'float',
        'height_cm'             => 'float',
        'pieces'                => 'float',
        'waste_rate_override'   => 'float',
        'fabric_width_override' => 'float',
        'constant_qty'          => 'float',
        'calculated_qty'        => 'float',
        'sort_order'            => 'integer',
    ];

    public const OPERATIONS = [
        'terzihane'  => 'Terzihane',
        'doseme'     => 'Döşeme',
        'marangoz'   => 'Marangozhane',
        'sunger'     => 'Süngerhane',
        'paketleme'  => 'Paketleme',
        'demirhane'  => 'Demirhane',
        'diger'      => 'Diğer',
    ];

    public const CALC_TYPES = [
        'fabric_meter' => 'Kumaş (Metre)',
        'area_m2'      => 'Alan (m²)',
        'volume_m3'    => 'Hacim (m³)',
        'piece'        => 'Adet',
        'fixed_qty'    => 'Sabit Miktar',
    ];

    // ─── İlişkiler ─────────────────────────────────────────

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    // ─── Accessor'lar ──────────────────────────────────────

    public function getOperationLabelAttribute(): string
    {
        return self::OPERATIONS[$this->operation] ?? $this->operation;
    }

    public function getCalcTypeLabelAttribute(): string
    {
        return self::CALC_TYPES[$this->calc_type] ?? $this->calc_type;
    }

    /**
     * 3 seviyeli öncelik: Satır override > Malzeme default > Sistem default
     */
    public function getEffectiveWasteRateAttribute(): float
    {
        if ($this->waste_rate_override !== null) {
            return $this->waste_rate_override;
        }
        if ($this->material && $this->material->default_waste_rate !== null) {
            return $this->material->default_waste_rate;
        }
        return (float) RecipeSetting::get('default_waste_rate', 0.10);
    }

    /**
     * 2 seviyeli öncelik: Satır override > Malzeme default
     */
    public function getEffectiveFabricWidthAttribute(): ?float
    {
        if ($this->fabric_width_override !== null) {
            return $this->fabric_width_override;
        }
        return $this->material->fabric_width_cm ?? null;
    }

    /**
     * Hesaplama sonucunun satır maliyeti
     */
    public function getLineCostAttribute(): float
    {
        return $this->calculated_qty * ($this->material->unit_price ?? 0);
    }
}
