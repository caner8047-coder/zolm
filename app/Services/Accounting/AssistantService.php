<?php

namespace App\Services\Accounting;

use App\Models\AssistantQuery;
use App\Services\Accounting\ReportService;
use Illuminate\Support\Str;

/**
 * AI Finansal ve Operasyonel Asistan Servisi (Phase 11).
 *
 * Sorumluluklar:
 * 1. Doğal dil (natural language) sorgularını güvenli analiz fonksiyonlarına yönlendirme.
 * 2. Tenant izolasyonunu koruyarak salt-okunur finans raporlarını sentezleme.
 * 3. Sorgu geçmişi (assistant_queries) kaydı tutma.
 */
class AssistantService
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * AI Asistana Doğal Dil Sorusunu Gönder.
     * Güvenli yönlendirme (query mapping) ile izole raporları çalıştırır.
     */
    public function askAssistant(int $userId, string $queryText): AssistantQuery
    {
        $lowerQuery = Str::lower($queryText);
        $responseText = '';
        $meta = [];

        // 1. Nakit Akışı / Likidite sorgusu
        if (Str::contains($lowerQuery, ['nakit', 'likidite', 'kasa', 'banka', 'para'])) {
            $data = $this->reportService->getCashFlowForecast($userId);
            $responseText = sprintf(
                "Mevcut toplam likiditeniz: ₺%s (Kasa: ₺%s, Banka: ₺%s). Önümüzdeki 30 gün içinde beklenen tahsilat: ₺%s, beklenen ödeme: ₺%s. Öngörülen net nakit akışı: ₺%s.",
                number_format($data['total_liquidity'], 2, ',', '.'),
                number_format($data['cash_balance'], 2, ',', '.'),
                number_format($data['bank_balance'], 2, ',', '.'),
                number_format($data['expected_inflow'], 2, ',', '.'),
                number_format($data['expected_outflow'], 2, ',', '.'),
                number_format($data['net_forecast'], 2, ',', '.')
            );
            $meta = ['report' => 'cash_flow', 'data' => $data];
        }
        // 2. Alacak Yaşlandırma sorgusu
        elseif (Str::contains($lowerQuery, ['alacak', 'yaşlandırma', 'vade', 'borçlu'])) {
            $data = $this->reportService->getAgedReceivables($userId);
            $responseText = sprintf(
                "Toplam açık alacağınız: ₺%s. Vadesi gelmemiş: ₺%s. Vadesi geçmiş alacaklarınız: 0-30 gün: ₺%s, 31-60 gün: ₺%s, 61-90 gün: ₺%s, 90+ gün: ₺%s.",
                number_format($data['total'], 2, ',', '.'),
                number_format($data['not_due'], 2, ',', '.'),
                number_format($data['aged_0_30'], 2, ',', '.'),
                number_format($data['aged_31_60'], 2, ',', '.'),
                number_format($data['aged_61_90'], 2, ',', '.'),
                number_format($data['aged_90_plus'], 2, ',', '.')
            );
            $meta = ['report' => 'aged_receivables', 'data' => $data];
        }
        // 3. Kar / Zarar / Gelir sorgusu
        elseif (Str::contains($lowerQuery, ['karlılık', 'kar', 'zarar', 'gelir', 'gider'])) {
            $dateFrom = now()->startOfMonth()->toDateString();
            $dateTo = now()->endOfMonth()->toDateString();
            $data = $this->reportService->getProfitLossSummary($userId, $dateFrom, $dateTo);
            $responseText = sprintf(
                "Bu ayki brüt geliriniz: ₺%s. Toplam gideriniz: ₺%s. Net kâr/zarar durumunuz: ₺%s.",
                number_format($data['gross_revenue'], 2, ',', '.'),
                number_format($data['total_expense'], 2, ',', '.'),
                number_format($data['net_profit'], 2, ',', '.')
            );
            $meta = ['report' => 'profit_loss', 'data' => $data, 'period' => [$dateFrom, $dateTo]];
        }
        // 4. Stok Değeri sorgusu
        elseif (Str::contains($lowerQuery, ['stok değeri', 'stok varlığı', 'envanter'])) {
            $data = $this->reportService->getWarehouseStockValue($userId);
            $responseText = sprintf(
                "Depolarınızdaki toplam envanter adedi: %s. Güncel son alış maliyetlerine göre stok değeri: ₺%s.",
                number_format($data['total_items'], 0, ',', '.'),
                number_format($data['total_value'], 2, ',', '.')
            );
            $meta = ['report' => 'stock_valuation', 'data' => $data];
        }
        // 5. Fallback Default
        else {
            $responseText = "Üzgünüm, sorunuzu tam olarak anlayamadım. Finansal analiz için 'nakit akışı', 'alacaklar', 'karlılık' veya 'stok değeri' hakkında sorular sorabilirsiniz.";
            $meta = ['fallback' => true];
        }

        return AssistantQuery::create([
            'user_id'       => $userId,
            'query_text'    => $queryText,
            'response_text' => $responseText,
            'status'        => 'completed',
            'meta_json'     => $meta,
        ]);
    }
}
