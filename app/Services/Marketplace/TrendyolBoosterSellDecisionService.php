<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterProduct;
use App\Models\TrendyolBoosterSnapshot;
use Illuminate\Support\Collection;

class TrendyolBoosterSellDecisionService
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function calculateFinancial(array $input): array
    {
        return $this->financial($input);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $marketData
     * @return array<string, mixed>
     */
    public function decide(TrendyolBoosterProduct $product, array $input, array $marketData = [], string $marketMessage = ''): array
    {
        $snapshots = $product->analysisSnapshots()
            ->latest('checked_at')
            ->latest('id')
            ->limit(12)
            ->get();

        if ($snapshots->isEmpty() && $product->relationLoaded('analysisSnapshots')) {
            $snapshots = $product->analysisSnapshots;
        }

        $current = $snapshots->first();
        $previous = $snapshots->get(1);
        $financial = $this->financial($input);
        $velocity = $this->velocity($product, $snapshots, $current, $previous);
        $market = $this->market($marketData, (string) $product->trendyol_product_id, $marketMessage);
        $score = $this->score($financial, $velocity, $market, $current);
        $decision = $this->decision($financial, $velocity, $score);
        $reasons = $this->reasons($financial, $velocity, $market, $decision);
        $actions = $this->actions($financial, $velocity, $market, $decision);

        return [
            'version' => 1,
            'generated_at' => now()->toIso8601String(),
            'product' => [
                'id' => $product->id,
                'trendyol_product_id' => $product->trendyol_product_id,
                'source_url' => $product->source_url,
                'title' => $product->title,
                'brand' => $product->brand,
                'category_name' => $product->category_name,
                'image_url' => $product->image_url,
                'sale_price' => (float) $product->sale_price,
            ],
            'decision' => $decision,
            'score' => $score,
            'confidence' => min(100, (int) round(($velocity['confidence'] * 0.7) + (($financial['cost_ready'] ? 100 : 35) * 0.3))),
            'financial' => $financial,
            'velocity' => $velocity,
            'market' => $market,
            'reasons' => $reasons,
            'actions' => $actions,
            'expert_summary' => $this->expertSummary($financial, $velocity, $market, $decision),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function financial(array $input): array
    {
        $saleGross = $this->money($input['sale_price'] ?? 0);
        $cogsGross = $this->money($input['cogs'] ?? 0);
        $packagingGross = $this->money($input['packaging_cost'] ?? 0);
        $cargoGross = $this->money($input['cargo_cost'] ?? 0);
        $returnCargoGross = $this->money($input['return_cargo_cost'] ?? 0);
        $commissionRate = $this->percent($input['commission_rate'] ?? 0);
        $serviceFeeRate = $this->percent($input['service_fee_rate'] ?? 0);
        $advertisingRate = $this->percent($input['advertising_rate'] ?? 0);
        $returnRate = $this->percent($input['return_rate'] ?? 0);
        $vatRate = $this->percent($input['vat_rate'] ?? 20);
        $costVatRate = $this->percent($input['cost_vat_rate'] ?? $vatRate);
        $expenseVatRate = $this->percent($input['expense_vat_rate'] ?? 20);
        $withholdingRate = $this->percent($input['withholding_rate'] ?? 1);
        $incomeTaxRate = $this->percent($input['income_tax_rate'] ?? 25);
        $vatEnabled = (bool) ($input['vat_enabled'] ?? true);
        $withholdingEnabled = (bool) ($input['withholding_enabled'] ?? true);

        $sale = $this->splitIncludedVat($saleGross, $vatEnabled ? $vatRate : 0);
        $cogs = $this->splitIncludedVat($cogsGross, $vatEnabled ? $costVatRate : 0);
        $packaging = $this->splitIncludedVat($packagingGross, $vatEnabled ? $costVatRate : 0);

        $commissionGross = round($saleGross * $commissionRate, 2);
        $commission = $this->splitIncludedVat($commissionGross, $vatEnabled ? $expenseVatRate : 0);
        $cargo = $this->splitIncludedVat($cargoGross, $vatEnabled ? $expenseVatRate : 0);
        $serviceFeeGross = round($saleGross * $serviceFeeRate, 2);
        $serviceFee = $this->splitIncludedVat($serviceFeeGross, $vatEnabled ? $expenseVatRate : 0);
        $returnReserveGross = round(($saleGross * $returnRate) + ($returnCargoGross * $returnRate), 2);
        $advertisingGross = round($saleGross * $advertisingRate, 2);
        $withholdingBase = $vatEnabled ? $sale['net'] : $saleGross;
        $withholding = $withholdingEnabled ? round($withholdingBase * $withholdingRate, 2) : 0.0;

        $inputVat = round($cogs['vat'] + $packaging['vat'] + $commission['vat'] + $cargo['vat'] + $serviceFee['vat'], 2);
        $payableVat = max(0.0, round($sale['vat'] - $inputVat, 2));
        $taxableProfit = round(
            $sale['net']
            - $cogs['net']
            - $packaging['net']
            - $commission['net']
            - $cargo['net']
            - $serviceFee['net']
            - $returnReserveGross,
            2
        );
        $incomeTax = max(0.0, round($taxableProfit * $incomeTaxRate, 2));
        $productCostGross = round($cogsGross + $packagingGross, 2);
        $grossProfit = round($saleGross - $productCostGross, 2);
        $grossMargin = $saleGross > 0 ? round(($grossProfit / $saleGross) * 100, 2) : 0.0;
        $contributionProfit = round(
            $grossProfit
            - $commissionGross
            - $cargoGross
            - $serviceFeeGross
            - $returnReserveGross,
            2
        );
        $operatingProfit = round($contributionProfit - $payableVat, 2);
        $netProfit = round(
            $saleGross
            - $productCostGross
            - $commissionGross
            - $cargoGross
            - $serviceFeeGross
            - $returnReserveGross
            - $payableVat
            - $incomeTax,
            2
        );
        $netProfitAfterAds = round($netProfit - $advertisingGross, 2);
        $margin = $saleGross > 0 ? round(($netProfit / $saleGross) * 100, 2) : 0.0;
        $roi = $productCostGross > 0 ? round(($netProfit / $productCostGross) * 100, 2) : 0.0;

        return [
            'sale_gross' => $saleGross,
            'sale_net' => $sale['net'],
            'sales_vat' => $sale['vat'],
            'cogs_gross' => $cogsGross,
            'packaging_gross' => $packagingGross,
            'product_cost_gross' => $productCostGross,
            'product_cost_net' => round($cogs['net'] + $packaging['net'], 2),
            'purchase_vat_credit' => round($cogs['vat'] + $packaging['vat'], 2),
            'gross_profit' => $grossProfit,
            'gross_margin_percent' => $grossMargin,
            'commission_gross' => $commissionGross,
            'commission_net' => $commission['net'],
            'commission_vat_credit' => $commission['vat'],
            'cargo_gross' => $cargoGross,
            'cargo_net' => $cargo['net'],
            'cargo_vat_credit' => $cargo['vat'],
            'service_fee_gross' => $serviceFeeGross,
            'service_fee_net' => $serviceFee['net'],
            'return_reserve_gross' => $returnReserveGross,
            'contribution_profit' => $contributionProfit,
            'input_vat_credit' => $inputVat,
            'payable_vat' => $payableVat,
            'operating_profit' => $operatingProfit,
            'taxable_profit' => $taxableProfit,
            'income_tax' => $incomeTax,
            'income_tax_rate' => round($incomeTaxRate * 100, 2),
            'withholding' => $withholding,
            'withholding_base' => round($withholdingBase, 2),
            'withholding_rate' => round($withholdingRate * 100, 2),
            'withholding_note' => 'Stopaj peşin vergi kabul edilir; net kâr hesabında ikinci kez düşülmedi.',
            'advertising_excluded' => $advertisingGross,
            'net_profit' => $netProfit,
            'net_profit_after_ads' => $netProfitAfterAds,
            'profit_margin_percent' => $margin,
            'roi_percent' => $roi,
            'cost_ready' => $productCostGross > 0,
            'vat_enabled' => $vatEnabled,
            'withholding_enabled' => $withholdingEnabled,
            'cash_after_marketplace' => round($saleGross - $commissionGross - $withholding, 2),
        ];
    }

    /**
     * @param  Collection<int, TrendyolBoosterSnapshot>  $snapshots
     * @return array<string, mixed>
     */
    protected function velocity(
        TrendyolBoosterProduct $product,
        Collection $snapshots,
        ?TrendyolBoosterSnapshot $current,
        ?TrendyolBoosterSnapshot $previous,
    ): array {
        $days = $this->daysBetween($current, $previous);
        $stockDropPerDay = null;
        $reviewPerDay = null;
        $evaluationPerDay = null;
        $favoritePerDay = null;
        $questionPerDay = null;
        $sources = [];

        if ($days !== null && $days > 0 && $current && $previous) {
            $stockDelta = $this->delta($previous->stock_quantity, $current->stock_quantity);
            if ($stockDelta !== null && $stockDelta < 0) {
                $stockDropPerDay = round(abs($stockDelta) / $days, 2);
                $sources[] = [
                    'label' => 'Stok düşüşü',
                    'value' => $stockDropPerDay,
                    'unit' => 'adet/gün',
                    'quality' => 'high',
                    'note' => 'Ardışık snapshot stok azalışı satış için en güçlü sinyaldir.',
                ];
            }

            $reviewPerDay = $this->positiveDeltaPerDay($previous->review_count, $current->review_count, $days);
            $evaluationPerDay = $this->positiveDeltaPerDay($previous->evaluation_count, $current->evaluation_count, $days);
            $favoritePerDay = $this->positiveDeltaPerDay($previous->favorite_count, $current->favorite_count, $days);
            $questionPerDay = $this->positiveDeltaPerDay($previous->question_count, $current->question_count, $days);

            foreach ([
                ['Yorum artışı', $reviewPerDay, 'yorum/gün', 'Yorum bırakan alıcı oranı düşük olduğu için satış tahmini aralıkla verilir.'],
                ['Değerlendirme artışı', $evaluationPerDay, 'değerlendirme/gün', 'Puan veren alıcı oranı yorum oranından daha yüksek kabul edilir.'],
                ['Favori artışı', $favoritePerDay, 'favori/gün', 'Favori artışı niyet sinyalidir, tek başına satış sayılmaz.'],
                ['Soru artışı', $questionPerDay, 'soru/gün', 'Soru artışı talep canlılığını gösterir.'],
            ] as [$label, $value, $unit, $note]) {
                if ($value !== null && $value > 0) {
                    $sources[] = compact('label', 'value', 'unit', 'note') + ['quality' => 'medium'];
                }
            }
        }

        $central = null;
        $method = 'insufficient_history';
        $confidence = 10;
        $proxyEstimates = [];

        if ($current?->estimated_daily_sales !== null && (float) $current->estimated_daily_sales > 0) {
            $central = (float) $current->estimated_daily_sales;
            $method = (string) data_get($current->metrics_json, 'sales_method', 'booster_intelligence');
            $confidence = max(55, (int) $current->confidence_score);
        } elseif ($stockDropPerDay !== null && $stockDropPerDay > 0) {
            $central = $stockDropPerDay;
            $method = 'stock_depletion';
            $confidence = 90;
        } else {
            if ($evaluationPerDay !== null && $evaluationPerDay > 0) {
                $proxyEstimates[] = ['value' => $evaluationPerDay / 0.06, 'method' => 'evaluation_proxy'];
            }
            if ($reviewPerDay !== null && $reviewPerDay > 0) {
                $proxyEstimates[] = ['value' => $reviewPerDay / 0.025, 'method' => 'review_proxy'];
            }
            if ($favoritePerDay !== null && $favoritePerDay > 0) {
                $proxyEstimates[] = ['value' => $favoritePerDay * 0.04, 'method' => 'favorite_proxy'];
            }
            if ($questionPerDay !== null && $questionPerDay > 0) {
                $proxyEstimates[] = ['value' => $questionPerDay * 4, 'method' => 'question_proxy'];
            }
            if ($current?->basket_count !== null && $current->basket_count > 0) {
                $proxyEstimates[] = ['value' => (int) $current->basket_count * 0.18, 'method' => 'basket_proxy'];
            }
            if ($current?->view_count_24h !== null && $current->view_count_24h > 0) {
                $proxyEstimates[] = ['value' => (int) $current->view_count_24h * 0.012, 'method' => 'view_24h_proxy'];
            }

            if ($proxyEstimates !== []) {
                $central = collect($proxyEstimates)->avg('value');
                $method = collect($proxyEstimates)->pluck('method')->unique()->implode('+');
                $confidence = $days !== null ? 58 : 38;
            } elseif ($product->estimated_daily_sales !== null && (float) $product->estimated_daily_sales > 0) {
                $central = (float) $product->estimated_daily_sales;
                $method = 'stored_product_estimate';
                $confidence = max(35, (int) $product->data_quality_score);
            }
        }

        $central = $central !== null ? round(max(0, $central), 2) : null;
        $rangeMultiplier = $confidence >= 80 ? [0.75, 1.25] : ($confidence >= 55 ? [0.55, 1.6] : [0.35, 2.2]);
        $low = $central !== null ? round($central * $rangeMultiplier[0], 2) : null;
        $high = $central !== null ? round($central * $rangeMultiplier[1], 2) : null;
        $salePrice = (float) $product->sale_price;

        return [
            'method' => $method,
            'confidence' => $confidence,
            'sample_count' => $snapshots->count(),
            'observed_days' => $days !== null ? round($days, 2) : null,
            'estimated_daily_sales' => $central,
            'estimated_daily_sales_low' => $low,
            'estimated_daily_sales_high' => $high,
            'estimated_daily_revenue' => $central !== null ? round($central * $salePrice, 2) : null,
            'review_per_day' => $reviewPerDay,
            'evaluation_per_day' => $evaluationPerDay,
            'favorite_per_day' => $favoritePerDay,
            'question_per_day' => $questionPerDay,
            'stock_drop_per_day' => $stockDropPerDay,
            'days_of_stock' => $central !== null && $central > 0 && $current?->stock_quantity !== null
                ? round((int) $current->stock_quantity / $central, 2)
                : null,
            'current_stock' => $current?->stock_quantity,
            'published_24h_views' => $current?->view_count_24h,
            'published_basket_count' => $current?->basket_count,
            'total_reviews' => $current?->review_count,
            'total_evaluations' => $current?->evaluation_count,
            'total_favorites' => $current?->favorite_count,
            'sources' => $sources,
        ];
    }

    /**
     * @param  array<string, mixed>  $marketData
     * @return array<string, mixed>
     */
    protected function market(array $marketData, string $productId, string $message): array
    {
        $ids = array_values(array_filter((array) ($marketData['product_ids'] ?? [])));
        $rank = null;

        foreach ($ids as $index => $id) {
            if ((string) $id === $productId) {
                $rank = $index + 1;
                break;
            }
        }

        $visibleCompetitors = max(0, count($ids) - ($rank !== null ? 1 : 0));

        return [
            'keyword' => (string) ($marketData['keyword'] ?? ''),
            'source_url' => (string) ($marketData['source_url'] ?? ''),
            'message' => $message,
            'visible_result_count' => count($ids),
            'visible_competitors' => $visibleCompetitors,
            'product_rank' => $rank,
            'visibility_label' => match (true) {
                $rank !== null && $rank <= 3 => 'İlk 3 sonuçta',
                $rank !== null && $rank <= 12 => 'İlk sayfada',
                $rank !== null => 'Sonuçlarda görünüyor',
                count($ids) > 0 => 'İlk sonuçlarda yakalanmadı',
                default => 'Arama verisi yok',
            },
            'top_products' => array_slice((array) ($marketData['top_products'] ?? []), 0, 6),
        ];
    }

    /**
     * @param  array<string, mixed>  $financial
     * @param  array<string, mixed>  $velocity
     * @param  array<string, mixed>  $market
     */
    protected function score(array $financial, array $velocity, array $market, ?TrendyolBoosterSnapshot $current): int
    {
        $margin = (float) $financial['profit_margin_percent'];
        $dailySales = $velocity['estimated_daily_sales'];
        $confidence = (int) $velocity['confidence'];
        $score = match (true) {
            $margin >= 25 => 42,
            $margin >= 18 => 36,
            $margin >= 12 => 28,
            $margin >= 7 => 18,
            $margin > 0 => 8,
            default => 0,
        };

        $score += match (true) {
            $dailySales === null => 0,
            $dailySales >= 20 => 32,
            $dailySales >= 10 => 27,
            $dailySales >= 5 => 22,
            $dailySales >= 2 => 15,
            $dailySales >= 0.5 => 8,
            default => 2,
        };
        $score += (int) round($confidence * 0.12);
        $score += match (true) {
            (int) $market['visible_competitors'] <= 2 => 8,
            (int) $market['visible_competitors'] <= 6 => 5,
            default => 3,
        };

        if ($market['product_rank'] !== null && (int) $market['product_rank'] <= 3) {
            $score += 2;
        }

        if ($current?->average_rating !== null && (float) $current->average_rating >= 4.5) {
            $score += 3;
        }
        if ($current?->seller_score !== null && (float) $current->seller_score >= 8.5) {
            $score += 3;
        }

        if (! $financial['cost_ready']) {
            $score = min($score, 45);
        }
        if ((float) $financial['net_profit'] <= 0) {
            $score = min($score, 25);
        }
        if ($dailySales === null || $confidence < 35) {
            $score = min($score, 68);
        }
        if ($margin > 0 && $margin < 7) {
            $score = min($score, 58);
        }

        return max(0, min(100, $score));
    }

    /** @param array<string, mixed> $financial @param array<string, mixed> $velocity */
    protected function decision(array $financial, array $velocity, int $score): string
    {
        if (! $financial['cost_ready']) {
            return 'wait';
        }

        if ((float) $financial['net_profit'] <= 0) {
            return 'avoid';
        }

        $margin = (float) $financial['profit_margin_percent'];
        $dailySales = $velocity['estimated_daily_sales'];

        return match (true) {
            $score >= 75 && $margin >= 12 && $dailySales !== null && $dailySales >= 1 => 'sell',
            $score >= 60 && $margin >= 8 => 'test',
            $score >= 45 => 'wait',
            default => 'avoid',
        };
    }

    /**
     * @param  array<string, mixed>  $financial
     * @param  array<string, mixed>  $velocity
     * @param  array<string, mixed>  $market
     * @return array<int, string>
     */
    protected function reasons(array $financial, array $velocity, array $market, string $decision): array
    {
        $reasons = [];

        if (! $financial['cost_ready']) {
            $reasons[] = 'Ürün maliyeti girilmediği için sat/satma kararı güvenilir seviyede değil.';
        } elseif ((float) $financial['net_profit'] > 0) {
            $reasons[] = 'Vergi sonrası net kâr pozitif: '.number_format((float) $financial['profit_margin_percent'], 1, ',', '.').'% marj.';
        } else {
            $reasons[] = 'Vergi sonrası net kâr negatif; ürün bu fiyat/maliyet yapısıyla zarar üretir.';
        }

        if ($velocity['estimated_daily_sales'] !== null) {
            $reasons[] = 'Satış hızı tahmini: günde '.number_format((float) $velocity['estimated_daily_sales'], 2, ',', '.').' adet.';
        } else {
            $reasons[] = 'Satış hızı için yeterli tarihsel snapshot veya yayınlanan 24s sinyal yok.';
        }

        if ($velocity['review_per_day'] !== null) {
            $reasons[] = 'Yorum hızı: günde '.number_format((float) $velocity['review_per_day'], 2, ',', '.').' yorum.';
        }

        if ($market['product_rank'] !== null) {
            $reasons[] = 'Trendyol aramasında görünürlük: '.$market['visibility_label'].'.';
        } elseif ((int) $market['visible_result_count'] > 0) {
            $reasons[] = 'Benzer arama sonuçları bulundu ancak ürün ilk görünür sonuçlarda yakalanmadı.';
        }

        if ($decision === 'sell') {
            $reasons[] = 'Finans, talep ve veri güveni aynı yönde güçlü sinyal veriyor.';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param  array<string, mixed>  $financial
     * @param  array<string, mixed>  $velocity
     * @param  array<string, mixed>  $market
     * @return array<int, string>
     */
    protected function actions(array $financial, array $velocity, array $market, string $decision): array
    {
        return match ($decision) {
            'sell' => [
                'İlk alımı satış hızı tahminine göre 7-14 günlük stokla sınırla.',
                'Ürünü Booster Radar’da saatlik/günlük taramaya açık tut.',
                'Fiyatı hedef marjın altına düşüren kampanyaları ayrı simüle et.',
            ],
            'test' => [
                'Tam stok alımı yerine küçük partiyle test et.',
                'En az iki snapshot sonrası yorum/gün ve stok düşüşünü tekrar kontrol et.',
                'Komisyon, kargo ve iade varsayımlarını gerçek faturalarla doğrula.',
            ],
            'wait' => [
                'Maliyet veya talep verisi netleşmeden alım kararı verme.',
                'Ürünü 24-48 saat Booster Radar’da izleyip ikinci snapshot oluştur.',
                'Rakip fiyatlarını ve kategori komisyonunu panelden teyit et.',
            ],
            default => [
                'Bu fiyat/maliyet yapısıyla üründen uzak dur.',
                'Alış maliyeti, kargo veya satış fiyatı değişirse kararı yeniden çalıştır.',
                'Daha yüksek talep sinyali olan alternatif ürünleri karşılaştır.',
            ],
        };
    }

    /** @param array<string, mixed> $financial @param array<string, mixed> $velocity @param array<string, mixed> $market */
    protected function expertSummary(array $financial, array $velocity, array $market, string $decision): string
    {
        $decisionText = match ($decision) {
            'sell' => 'satılabilir',
            'test' => 'kontrollü test edilebilir',
            'wait' => 'veri beklemeli',
            default => 'satışa alınmamalı',
        };
        $speed = $velocity['estimated_daily_sales'] !== null
            ? 'Günlük satış tahmini '.number_format((float) $velocity['estimated_daily_sales'], 2, ',', '.').' adet bandında.'
            : 'Satış hızı henüz güvenilir ölçülemiyor.';

        return 'Uzman motoru bu ürünü "'.$decisionText.'" olarak sınıfladı. '
            .'Net kâr '.number_format((float) $financial['net_profit'], 2, ',', '.').' TL, marj %'
            .number_format((float) $financial['profit_margin_percent'], 1, ',', '.').'. '
            .$speed.' Stopaj peşin vergi olarak izlenir, net kârdan ikinci kez düşülmez.';
    }

    protected function splitIncludedVat(float $gross, float $rate): array
    {
        if ($gross <= 0 || $rate <= 0) {
            return ['gross' => round($gross, 2), 'net' => round($gross, 2), 'vat' => 0.0];
        }

        $net = round($gross / (1 + $rate), 2);

        return ['gross' => round($gross, 2), 'net' => $net, 'vat' => round($gross - $net, 2)];
    }

    protected function daysBetween(?TrendyolBoosterSnapshot $current, ?TrendyolBoosterSnapshot $previous): ?float
    {
        if (! $current?->checked_at || ! $previous?->checked_at) {
            return null;
        }

        return max(0.01, $previous->checked_at->floatDiffInDays($current->checked_at));
    }

    protected function delta(mixed $previous, mixed $current): ?float
    {
        if (! is_numeric($previous) || ! is_numeric($current)) {
            return null;
        }

        return (float) $current - (float) $previous;
    }

    protected function positiveDeltaPerDay(mixed $previous, mixed $current, float $days): ?float
    {
        $delta = $this->delta($previous, $current);

        if ($delta === null || $delta <= 0) {
            return null;
        }

        return round($delta / $days, 2);
    }

    protected function money(mixed $value): float
    {
        return round(max(0, (float) $value), 2);
    }

    protected function percent(mixed $value): float
    {
        $rate = max(0, (float) $value);

        return $rate >= 1 ? min(100, $rate) / 100 : min(1, $rate);
    }
}
