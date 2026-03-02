<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Material extends Model
{
    protected $fillable = [
        'user_id', 'code', 'name', 'category', 'base_unit',
        'default_waste_rate', 'fabric_width_cm', 'fabric_calc_method',
        'density_kg_m3', 'thickness_cm',
        'rounding_mode', 'rounding_step',
        'unit_price', 'currency', 'supplier',
        'notes', 'is_active',
    ];

    protected $casts = [
        'default_waste_rate' => 'float',
        'fabric_width_cm'    => 'float',
        'density_kg_m3'      => 'float',
        'thickness_cm'       => 'float',
        'rounding_step'      => 'float',
        'unit_price'         => 'float',
        'is_active'          => 'boolean',
    ];

    // ─── Kategori & Birim Sabitleri ────────────────────────

    public const CATEGORIES = [
        'fabric'    => 'Kumaş',
        'foam'      => 'Sünger',
        'wood'      => 'Ahşap / Panel',
        'hardware'  => 'Hırdavat',
        'packaging' => 'Ambalaj',
        'textile'   => 'Tela / Astar',
        'lining'    => 'Elyaf / Vatka',
        'other'     => 'Diğer',
    ];

    public const UNITS = [
        'm'   => 'Metre',
        'm2'  => 'Metrekare',
        'm3'  => 'Metreküp',
        'pcs' => 'Adet',
        'kg'  => 'Kilogram',
        'set' => 'Set',
    ];

    public const FABRIC_METHODS = [
        'area_div_width'       => 'Alan / Kumaş Eni',
        'fixed_meter_per_piece' => 'Parça Başı Sabit Metre',
    ];

    public const ROUNDING_MODES = [
        'none'      => 'Yuvarlama Yok',
        'ceil_step' => 'Yukarı Yuvarla (Adım)',
        'round'     => 'Normal Yuvarla',
        'floor'     => 'Aşağı Yuvarla',
    ];

    // ─── İlişkiler ─────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function recipeLines()
    {
        return $this->hasMany(RecipeLine::class);
    }

    // ─── Accessor'lar ──────────────────────────────────────

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function getUnitLabelAttribute(): string
    {
        return self::UNITS[$this->base_unit] ?? $this->base_unit;
    }

    // ─── Scope'lar ─────────────────────────────────────────

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) return $query;
        return $query->where(fn($q) =>
            $q->where('code', 'like', "%{$term}%")
              ->orWhere('name', 'like', "%{$term}%")
        );
    }

    public function scopeByCategory(Builder $query, ?string $category): Builder
    {
        if (empty($category) || $category === 'all') return $query;
        return $query->where('category', $category);
    }

    public function scopeByUnit(Builder $query, ?string $unit): Builder
    {
        if (empty($unit) || $unit === 'all') return $query;
        return $query->where('base_unit', $unit);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser(Builder $query, ?int $userId = null): Builder
    {
        return $query->where('user_id', $userId ?? auth()->id());
    }
}
