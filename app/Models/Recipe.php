<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Recipe extends Model
{
    protected $fillable = [
        'user_id', 'mp_product_id', 'name', 'version', 'status', 'notes',
    ];

    public const STATUSES = [
        'draft'    => 'Taslak',
        'active'   => 'Aktif',
        'archived' => 'Arşiv',
    ];

    // ─── İlişkiler ─────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(MpProduct::class, 'mp_product_id');
    }

    public function lines()
    {
        return $this->hasMany(RecipeLine::class)->orderBy('sort_order');
    }

    // ─── Accessor'lar ──────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active'   => 'bg-emerald-100 text-emerald-800',
            'draft'    => 'bg-amber-100 text-amber-800',
            'archived' => 'bg-gray-100 text-gray-600',
            default    => 'bg-gray-100 text-gray-600',
        };
    }

    public function getLineCountAttribute(): int
    {
        return $this->lines()->count();
    }

    public function getTotalCostAttribute(): float
    {
        return $this->lines->sum(function ($line) {
            $price = $line->material->unit_price ?? 0;
            return $line->calculated_qty * $price;
        });
    }

    // ─── Scope'lar ─────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeForUser(Builder $query, ?int $userId = null): Builder
    {
        return $query->where('user_id', $userId ?? auth()->id());
    }

    // ─── Yardımcı Metodlar ─────────────────────────────────

    /**
     * Konsolide BOM: aynı malzemeler birleşik, miktarlar toplanmış
     */
    public function getConsolidatedBom(): \Illuminate\Support\Collection
    {
        return $this->lines->groupBy('material_id')->map(function ($group) {
            $first = $group->first();
            return [
                'material_id'   => $first->material_id,
                'material_code' => $first->material->code ?? '',
                'material_name' => $first->material->name ?? '',
                'total_qty'     => $group->sum('calculated_qty'),
                'unit'          => $first->calculated_unit,
                'operations'    => $group->pluck('operation')->unique()->values()->toArray(),
                'unit_price'    => $first->material->unit_price,
                'total_cost'    => $group->sum('calculated_qty') * ($first->material->unit_price ?? 0),
            ];
        })->sortBy('material_code')->values();
    }

    /**
     * Reçeteyi kopyala (yeni versiyon)
     */
    public function duplicate(string $newVersion = null): self
    {
        $newVersion = $newVersion ?? 'v' . ((int) str_replace('v', '', $this->version) + 1);

        $copy = $this->replicate();
        $copy->version = $newVersion;
        $copy->status = 'draft';
        $copy->save();

        foreach ($this->lines as $line) {
            $newLine = $line->replicate();
            $newLine->recipe_id = $copy->id;
            $newLine->save();
        }

        return $copy;
    }
}
