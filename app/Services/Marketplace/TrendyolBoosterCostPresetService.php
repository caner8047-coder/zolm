<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterCostPreset;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TrendyolBoosterCostPresetService
{
    /**
     * @return Collection<int, TrendyolBoosterCostPreset>
     */
    public function presets(int $userId): Collection
    {
        return TrendyolBoosterCostPreset::query()
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function store(int $userId, array $input): TrendyolBoosterCostPreset
    {
        $name = $this->cleanName((string) ($input['name'] ?? ''));

        return TrendyolBoosterCostPreset::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'name' => $name,
            ],
            [
                'category_name' => $this->cleanText($input['category_name'] ?? null),
                'commission_rate' => $this->percent($input['commission_rate'] ?? 0),
                'cargo_cost' => $this->money($input['cargo_cost'] ?? 0),
                'return_cargo_cost' => $this->money($input['return_cargo_cost'] ?? 0),
                'packaging_cost' => $this->money($input['packaging_cost'] ?? 0),
                'service_fee_rate' => $this->percent($input['service_fee_rate'] ?? 0),
                'advertising_rate' => $this->percent($input['advertising_rate'] ?? 0),
                'return_rate' => $this->percent($input['return_rate'] ?? 0),
                'vat_rate' => $this->percent($input['vat_rate'] ?? 20),
                'cost_vat_rate' => $this->percent($input['cost_vat_rate'] ?? 20),
                'expense_vat_rate' => $this->percent($input['expense_vat_rate'] ?? 20),
                'is_default' => (bool) ($input['is_default'] ?? false),
            ]
        );
    }

    /**
     * @return array<string, float>
     */
    public function values(TrendyolBoosterCostPreset $preset): array
    {
        return [
            'commissionRate' => (float) $preset->commission_rate,
            'cargoCost' => (float) $preset->cargo_cost,
            'returnCargoCost' => (float) $preset->return_cargo_cost,
            'packagingCost' => (float) $preset->packaging_cost,
            'serviceFeeRate' => (float) $preset->service_fee_rate,
            'advertisingRate' => (float) $preset->advertising_rate,
            'returnRate' => (float) $preset->return_rate,
            'vatRate' => (float) $preset->vat_rate,
            'costVatRate' => (float) $preset->cost_vat_rate,
            'expenseVatRate' => (float) $preset->expense_vat_rate,
        ];
    }

    protected function cleanName(string $value): string
    {
        $value = $this->cleanText($value);

        return $value !== '' ? $value : 'Trendyol Genel';
    }

    protected function cleanText(mixed $value): string
    {
        $text = html_entity_decode(strip_tags((string) ($value ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?: '';

        return trim(Str::limit($text, 180, ''));
    }

    protected function money(mixed $value): float
    {
        return round(max(0, (float) str_replace(',', '.', (string) ($value ?? 0))), 2);
    }

    protected function percent(mixed $value): float
    {
        return round(max(0, min(100, (float) str_replace(',', '.', (string) ($value ?? 0)))), 2);
    }
}
