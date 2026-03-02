<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\RecipeSetting;
use Illuminate\Support\Collection;

/**
 * Reçete Hesap Motoru
 * Excel formüllerinin birebir karşılığı — ölçülerden sarfiyat hesaplar.
 */
class RecipeCalculationService
{
    /**
     * Tek bir satır için hesaplama yap
     * @return array ['qty' => float, 'unit' => string, 'debug' => array]
     */
    public function calculateLine(RecipeLine $line): array
    {
        $material = $line->material;
        $wasteRate = $line->effective_waste_rate;
        $fabricWidth = $line->effective_fabric_width;

        $result = match ($line->calc_type) {
            'fabric_meter' => $this->calcFabricMeter(
                $line->width_cm, $line->length_cm, $line->pieces, $wasteRate, $fabricWidth
            ),
            'area_m2' => $this->calcAreaM2(
                $line->width_cm, $line->length_cm, $line->pieces, $wasteRate
            ),
            'volume_m3' => $this->calcVolumeM3(
                $line->width_cm, $line->length_cm, $line->height_cm, $line->pieces, $wasteRate
            ),
            'piece' => $this->calcPiece($line->pieces, $wasteRate),
            'fixed_qty' => $this->calcFixed($line->constant_qty),
            default => ['qty' => 0, 'unit' => 'pcs', 'debug' => ['error' => 'Bilinmeyen hesap tipi']],
        };

        // Yuvarlama uygula
        $roundingMode = $material->rounding_mode ?? 'none';
        $roundingStep = $material->rounding_step;
        $result['qty'] = $this->applyRounding($result['qty'], $roundingMode, $roundingStep);

        return $result;
    }

    /**
     * Tüm reçete satırlarını hesapla ve kaydet
     */
    public function calculateRecipe(Recipe $recipe): Collection
    {
        $recipe->load('lines.material');
        $results = collect();

        foreach ($recipe->lines as $line) {
            $result = $this->calculateLine($line);
            $line->update([
                'calculated_qty'  => $result['qty'],
                'calculated_unit' => $result['unit'],
            ]);
            $results->push([
                'line_id' => $line->id,
                ...$result,
            ]);
        }

        return $results;
    }

    /**
     * Konsolide BOM üret (aynı malzemeler birleşik)
     */
    public function generateBOM(Recipe $recipe): Collection
    {
        $recipe->load('lines.material');
        return $recipe->getConsolidatedBom();
    }

    /**
     * Operasyon bazlı gruplayarak getir
     */
    public function groupByOperation(Recipe $recipe): Collection
    {
        $recipe->load('lines.material');
        return $recipe->lines->groupBy('operation')->map(function ($group, $operation) {
            return [
                'operation'       => $operation,
                'operation_label' => RecipeLine::OPERATIONS[$operation] ?? $operation,
                'lines'           => $group->values(),
                'total_items'     => $group->count(),
            ];
        })->values();
    }

    // ─── Hesap Formülleri ──────────────────────────────────

    /**
     * KUMAŞ METRE: metre = (alan_m2 / eni_m) × (1 + fire)
     * Excel'deki formül: (en×boy×adet/10000) / (kumaş_eni/100) × fire_çarpanı
     */
    protected function calcFabricMeter(?float $width, ?float $length, float $pcs, float $wasteRate, ?float $fabricWidth): array
    {
        $width = $width ?? 0;
        $length = $length ?? 0;
        $fabricWidth = $fabricWidth ?? 140; // Fallback

        $area_m2 = ($width * $length * $pcs) / 10000;
        $fabric_width_m = $fabricWidth / 100;
        $metre_raw = $fabric_width_m > 0 ? ($area_m2 / $fabric_width_m) : 0;
        $metre_with_waste = $metre_raw * (1 + $wasteRate);

        return [
            'qty'   => $metre_with_waste,
            'unit'  => 'm',
            'debug' => [
                'area_m2'        => round($area_m2, 6),
                'fabric_width_m' => $fabric_width_m,
                'metre_raw'      => round($metre_raw, 6),
                'waste_rate'     => $wasteRate,
                'metre_final'    => round($metre_with_waste, 6),
            ],
        ];
    }

    /**
     * ALAN M²: m² = (en×boy×adet/10000) × (1+fire)
     */
    protected function calcAreaM2(?float $width, ?float $length, float $pcs, float $wasteRate): array
    {
        $width = $width ?? 0;
        $length = $length ?? 0;
        $area_raw = ($width * $length * $pcs) / 10000;
        $area_with_waste = $area_raw * (1 + $wasteRate);

        return [
            'qty'   => $area_with_waste,
            'unit'  => 'm2',
            'debug' => [
                'area_raw'    => round($area_raw, 6),
                'waste_rate'  => $wasteRate,
                'area_final'  => round($area_with_waste, 6),
            ],
        ];
    }

    /**
     * HACİM M³: m³ = (en×boy×yükseklik×adet/1000000) × (1+fire)
     */
    protected function calcVolumeM3(?float $width, ?float $length, ?float $height, float $pcs, float $wasteRate): array
    {
        $width = $width ?? 0;
        $length = $length ?? 0;
        $height = $height ?? 0;
        $volume_raw = ($width * $length * $height * $pcs) / 1000000;
        $volume_with_waste = $volume_raw * (1 + $wasteRate);

        return [
            'qty'   => $volume_with_waste,
            'unit'  => 'm3',
            'debug' => [
                'volume_raw'   => round($volume_raw, 6),
                'waste_rate'   => $wasteRate,
                'volume_final' => round($volume_with_waste, 6),
            ],
        ];
    }

    /**
     * ADET: pcs × (1+fire), yukarı yuvarla
     */
    protected function calcPiece(float $pcs, float $wasteRate): array
    {
        $raw = $pcs * (1 + $wasteRate);
        return [
            'qty'   => ceil($raw),
            'unit'  => 'pcs',
            'debug' => ['pieces' => $pcs, 'waste_rate' => $wasteRate, 'raw' => $raw],
        ];
    }

    /**
     * SABİT MİKTAR: kullanıcının girdiği değer aynen
     */
    protected function calcFixed(?float $qty): array
    {
        return [
            'qty'   => $qty ?? 0,
            'unit'  => 'pcs',
            'debug' => ['constant' => $qty],
        ];
    }

    // ─── Yuvarlama ─────────────────────────────────────────

    protected function applyRounding(float $value, string $mode, ?float $step): float
    {
        if ($mode === 'none' || $value == 0) return $value;

        return match ($mode) {
            'ceil_step' => $step > 0 ? ceil($value / $step) * $step : $value,
            'round'     => $step > 0 ? round($value / $step) * $step : round($value, 4),
            'floor'     => $step > 0 ? floor($value / $step) * $step : $value,
            default     => $value,
        };
    }
}
