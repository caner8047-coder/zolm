<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterProduct;
use Illuminate\Support\Facades\Schema;

class TrendyolBoosterPriorityActionService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(int $userId): array
    {
        if (! Schema::hasTable('trendyol_booster_products')) {
            return $this->emptyDashboard();
        }

        $query = TrendyolBoosterProduct::query()
            ->where('user_id', $userId)
            ->when(
                Schema::hasColumn('trendyol_booster_products', 'tracking_status'),
                fn ($query) => $query->where('tracking_status', 'active'),
            )
            ->latest('updated_at')
            ->limit($this->scanLimit());

        if (Schema::hasTable('trendyol_booster_snapshots')) {
            $query->with('latestSnapshot');
        }

        $products = $query->get();
        $actions = $products
            ->map(fn (TrendyolBoosterProduct $product): ?array => $this->actionFor($product))
            ->filter()
            ->sortByDesc('priority')
            ->values();
        $visibleActions = $actions->take($this->displayLimit())->values();
        $criticalCount = $actions->where('severity', 'critical')->count();
        $warningCount = $actions->where('severity', 'warning')->count();
        $severity = $criticalCount > 0
            ? 'critical'
            : ($warningCount > 0 ? 'warning' : ($actions->isNotEmpty() ? 'info' : 'healthy'));

        return [
            'severity' => $severity,
            'tone' => $this->tone($severity),
            'label' => $actions->isEmpty()
                ? 'Bugün için kritik ürün işi yok'
                : $actions->count().' ürün öncelik bekliyor',
            'summary' => $actions->isEmpty()
                ? 'Aktif takiplerde zarar, yüksek risk, kritik stok veya eksik analiz sinyali görünmüyor.'
                : 'En yüksek ticari etki ve veri güveni dikkate alınarak sıralandı.',
            'active_product_count' => $products->count(),
            'action_count' => $actions->count(),
            'critical_count' => $criticalCount,
            'warning_count' => $warningCount,
            'actions' => $visibleActions->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function actionFor(TrendyolBoosterProduct $product): ?array
    {
        $title = trim((string) $product->title) ?: 'Trendyol ürünü';
        $netProfit = (float) $product->net_profit;

        if ($product->decision_status === 'loss' || $netProfit < 0) {
            return $this->action(
                product: $product,
                key: 'loss',
                severity: 'critical',
                priority: 500 + min(99, (int) abs($netProfit)),
                label: 'Zarar riskini incele',
                reason: $title.' mevcut fiyat ve maliyet hesabında zarar sinyali veriyor.',
                metric: number_format($netProfit, 2, ',', '.').' TL',
            );
        }

        $riskScore = (int) $product->risk_score;
        $highRiskThreshold = max(1, min(100, (int) config('marketplace.trendyol_booster.decision_actions.high_risk_score', 60)));
        if ($riskScore >= $highRiskThreshold) {
            $severity = $riskScore >= 80 ? 'critical' : 'warning';

            return $this->action(
                product: $product,
                key: 'high_risk',
                severity: $severity,
                priority: 400 + $riskScore,
                label: 'Yüksek riski doğrula',
                reason: $title.' için fiyat, rekabet ve veri kaynaklarını yeniden kontrol et.',
                metric: $riskScore.'/100 risk',
            );
        }

        $snapshot = $product->relationLoaded('latestSnapshot') ? $product->latestSnapshot : null;
        $stockDays = $snapshot?->estimated_days_of_stock;
        $confidence = (int) ($snapshot?->confidence_score ?? 0);
        $stockDaysThreshold = max(1, (float) config('marketplace.trendyol_booster.decision_actions.critical_stock_days', 3));
        $stockConfidenceThreshold = max(0, min(100, (int) config('marketplace.trendyol_booster.decision_actions.stock_min_confidence', 50)));

        if ($stockDays !== null
            && (float) $stockDays > 0
            && (float) $stockDays <= $stockDaysThreshold
            && $confidence >= $stockConfidenceThreshold) {
            return $this->action(
                product: $product,
                key: 'critical_stock',
                severity: 'warning',
                priority: 350 + (int) round(($stockDaysThreshold - (float) $stockDays) * 10),
                label: 'Stok sinyalini doğrula',
                reason: $title.' için güvenilir ölçümde stok kısa sürede tükenebilir.',
                metric: '~'.number_format((float) $stockDays, 1, ',', '.').' gün',
            );
        }

        $qualityScore = (int) $product->data_quality_score;
        $lowQualityThreshold = max(0, min(100, (int) config('marketplace.trendyol_booster.decision_actions.low_quality_score', 40)));
        if ($snapshot === null || $qualityScore < $lowQualityThreshold) {
            return $this->action(
                product: $product,
                key: $snapshot === null ? 'first_analysis' : 'low_quality',
                severity: 'info',
                priority: $snapshot === null ? 250 : 200 + ($lowQualityThreshold - $qualityScore),
                label: $snapshot === null ? 'İlk analizi tamamla' : 'Veri kalitesini yükselt',
                reason: $snapshot === null
                    ? $title.' için karşılaştırılabilir ürün ölçümü henüz oluşmadı.'
                    : $title.' için karar vermeden önce daha güçlü veri topla.',
                metric: $snapshot === null ? 'Veri bekliyor' : '%'.$qualityScore.' kalite',
            );
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function action(
        TrendyolBoosterProduct $product,
        string $key,
        string $severity,
        int $priority,
        string $label,
        string $reason,
        string $metric,
    ): array {
        return [
            'key' => $key,
            'product_id' => $product->id,
            'title' => trim((string) $product->title) ?: 'Trendyol ürünü',
            'image_url' => $product->image_url,
            'severity' => $severity,
            'tone' => $this->tone($severity),
            'priority' => $priority,
            'label' => $label,
            'reason' => $reason,
            'metric' => $metric,
            'action_label' => 'İncele',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyDashboard(): array
    {
        return [
            'severity' => 'healthy',
            'tone' => 'emerald',
            'label' => 'Bugün için kritik ürün işi yok',
            'summary' => 'Aktif takip başlayınca öncelikli ticari işler burada sıralanacak.',
            'active_product_count' => 0,
            'action_count' => 0,
            'critical_count' => 0,
            'warning_count' => 0,
            'actions' => [],
        ];
    }

    protected function scanLimit(): int
    {
        return max(25, min(500, (int) config('marketplace.trendyol_booster.decision_actions.scan_limit', 200)));
    }

    protected function displayLimit(): int
    {
        return max(1, min(5, (int) config('marketplace.trendyol_booster.decision_actions.display_limit', 3)));
    }

    protected function tone(string $severity): string
    {
        return [
            'critical' => 'rose',
            'warning' => 'amber',
            'info' => 'sky',
            'healthy' => 'emerald',
        ][$severity] ?? 'slate';
    }
}
