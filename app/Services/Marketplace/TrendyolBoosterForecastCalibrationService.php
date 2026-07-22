<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterProduct;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class TrendyolBoosterForecastCalibrationService
{
    /** @return array<string, mixed> */
    public function dashboard(int $userId): array
    {
        if (! Schema::hasTable('trendyol_booster_products') || ! Schema::hasTable('trendyol_booster_snapshots')) {
            return $this->summarize(collect());
        }

        $products = TrendyolBoosterProduct::query()
            ->where('user_id', $userId)
            ->with(['analysisSnapshots' => fn ($query) => $query
                ->whereNotNull('stock_quantity')
                ->orderBy('checked_at')
                ->orderBy('id')])
            ->get(['id', 'title', 'category_name']);
        $pairs = collect();

        foreach ($products as $product) {
            $snapshots = $product->analysisSnapshots->values();
            for ($index = 1; $index < $snapshots->count(); $index++) {
                $previous = $snapshots[$index - 1];
                $current = $snapshots[$index];
                $predicted = $previous->estimated_daily_sales;
                $previousStock = $previous->stock_quantity;
                $currentStock = $current->stock_quantity;
                if ($predicted === null || (float) $predicted < 0 || $previousStock === null || $currentStock === null || $currentStock > $previousStock) {
                    continue;
                }

                $from = Carbon::parse($previous->checked_at);
                $to = Carbon::parse($current->checked_at);
                $elapsedDays = max(0, $from->diffInSeconds($to) / 86400);
                if ($elapsedDays < (2 / 24) || $elapsedDays > 14) {
                    continue;
                }

                $actual = max(0, ((float) $previousStock - (float) $currentStock) / $elapsedDays);
                $pairs->push([
                    'product_id' => $product->id,
                    'title' => $product->title ?: 'Ürün',
                    'category' => $product->category_name ?: 'Kategorisiz',
                    'predicted' => round((float) $predicted, 3),
                    'actual' => round($actual, 3),
                    'checked_at' => $current->checked_at?->toIso8601String(),
                ]);
            }
        }

        return $this->summarize($pairs);
    }

    /**
     * @param Collection<int, array<string, mixed>> $pairs
     * @return array<string, mixed>
     */
    public function summarize(Collection $pairs): array
    {
        $evaluated = $pairs->map(function (array $pair): array {
            $predicted = max(0, (float) ($pair['predicted'] ?? 0));
            $actual = max(0, (float) ($pair['actual'] ?? 0));
            $absoluteError = abs($predicted - $actual);
            $percentageError = $actual > 0 ? ($absoluteError / $actual) * 100 : null;

            return $pair + [
                'absolute_error' => round($absoluteError, 3),
                'percentage_error' => $percentageError !== null ? round($percentageError, 1) : null,
                'bias' => round($predicted - $actual, 3),
                'within_25_percent' => $percentageError !== null && $percentageError <= 25,
            ];
        })->values();
        $percentageSamples = $evaluated->whereNotNull('percentage_error');
        $mape = $percentageSamples->isNotEmpty() ? round((float) $percentageSamples->avg('percentage_error'), 1) : null;
        $mae = $evaluated->isNotEmpty() ? round((float) $evaluated->avg('absolute_error'), 2) : null;
        $bias = $evaluated->isNotEmpty() ? round((float) $evaluated->avg('bias'), 2) : null;
        $within25 = $percentageSamples->isNotEmpty()
            ? round(($percentageSamples->where('within_25_percent', true)->count() / $percentageSamples->count()) * 100, 1)
            : null;
        $sampleCount = $evaluated->count();

        $status = match (true) {
            $sampleCount < 3 => 'warming_up',
            $mape !== null && $mape <= 25 => 'calibrated',
            $mape !== null && $mape <= 50 => 'watch',
            default => 'needs_tuning',
        };
        $categories = $evaluated->groupBy('category')->map(function (Collection $rows, string $category): array {
            $percentageRows = $rows->whereNotNull('percentage_error');

            return [
                'category' => $category,
                'sample_count' => $rows->count(),
                'mape' => $percentageRows->isNotEmpty() ? round((float) $percentageRows->avg('percentage_error'), 1) : null,
                'mae' => round((float) $rows->avg('absolute_error'), 2),
                'bias' => round((float) $rows->avg('bias'), 2),
            ];
        })->sortByDesc('sample_count')->values()->all();

        return [
            'status' => $status,
            'label' => match ($status) {
                'calibrated' => 'Kalibre',
                'watch' => 'İzlenmeli',
                'needs_tuning' => 'Ayar gerekli',
                default => 'Veri birikiyor',
            },
            'sample_count' => $sampleCount,
            'percentage_sample_count' => $percentageSamples->count(),
            'mape' => $mape,
            'mae' => $mae,
            'bias' => $bias,
            'bias_label' => $bias === null ? 'Ölçüm yok' : ($bias > .1 ? 'Yüksek tahmin eğilimi' : ($bias < -.1 ? 'Düşük tahmin eğilimi' : 'Dengeli tahmin')),
            'within_25_percent' => $within25,
            'categories' => $categories,
            'recent' => $evaluated->sortByDesc('checked_at')->take(10)->values()->all(),
            'evidence_note' => 'Kalibrasyon, ardışık stok gözlemlerindeki gerçek erimeyi önceki satış tahminiyle karşılaştırır. Stok ikmali görülen aralıklar dışlanır.',
        ];
    }
}
