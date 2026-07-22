<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterProduct;
use App\Services\ExcelService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class TrendyolBoosterReportService
{
    public function excel(int $userId): BinaryFileResponse
    {
        $payload = $this->payload($userId);
        $path = storage_path('app/temp/trendyol-booster-karar-raporu-'.$userId.'-'.now()->format('Ymd-His').'.xlsx');
        app(ExcelService::class)->exportToXlsx([
            ['name' => 'Karar Özeti', 'data' => [$payload['summary_row']]],
            ['name' => 'Ürün Kararları', 'data' => $payload['rows']->all()],
        ], $path);

        return response()->download($path, 'zolm-trendyol-booster-'.now()->format('Y-m-d').'.xlsx')->deleteFileAfterSend(true);
    }

    public function pdf(int $userId): Response
    {
        $payload = $this->payload($userId);

        return Pdf::loadView('pdf.trendyol-booster-report', $payload)
            ->setPaper('a4', 'landscape')
            ->download('zolm-trendyol-booster-'.now()->format('Y-m-d').'.pdf');
    }

    /** @return array<string, mixed> */
    public function payload(int $userId): array
    {
        $products = TrendyolBoosterProduct::query()
            ->where('user_id', $userId)
            ->with('latestSnapshot')
            ->latest('updated_at')
            ->limit(1000)
            ->get();
        $rows = $this->buildRows($products);
        $summary = [
            'Toplam Ürün' => $products->count(),
            'Takipte' => $products->where('tracking_status', 'active')->count(),
            'Karara Hazır' => $products->filter(fn ($product): bool => (float) $product->cogs > 0 && $product->latestSnapshot !== null)->count(),
            'Riskli' => $products->where('risk_score', '>=', 60)->count(),
            'Rapor Tarihi' => now()->timezone('Europe/Istanbul')->format('d.m.Y H:i'),
        ];

        return [
            'title' => 'ZOLM Trendyol Booster Karar Raporu',
            'generated_at' => now()->timezone('Europe/Istanbul'),
            'summary' => $summary,
            'summary_row' => $summary,
            'rows' => $rows->isNotEmpty() ? $rows : collect([['Durum' => 'Henüz ürün verisi yok']]),
            'method_note' => 'Tahmini satış ve stok metrikleri yalnızca gözlenen Trendyol sinyallerine dayanır; kesin sipariş adedi değildir.',
        ];
    }

    /** @param Collection<int, TrendyolBoosterProduct> $products */
    public function buildRows(Collection $products): Collection
    {
        return $products->map(function (TrendyolBoosterProduct $product): array {
            $snapshot = $product->latestSnapshot;

            return [
                'Ürün' => (string) ($product->title ?: 'Trendyol ürünü'),
                'Marka' => (string) ($product->brand ?: ''),
                'Kategori' => (string) ($product->category_name ?: ''),
                'Trendyol Ürün ID' => (string) ($product->trendyol_product_id ?: ''),
                'Satış Fiyatı TL' => round((float) $product->sale_price, 2),
                'Maliyet TL' => round((float) $product->cogs, 2),
                'Net Kâr TL' => round((float) $product->net_profit, 2),
                'Net Marj %' => round((float) $product->net_margin, 2),
                'Karar' => (string) ($product->decision_status ?: 'veri_bekliyor'),
                'Risk Skoru' => (int) $product->risk_score,
                'Veri Kalitesi' => (int) $product->data_quality_score,
                'Tahmini Günlük Satış' => $product->estimated_daily_sales !== null ? round((float) $product->estimated_daily_sales, 2) : '',
                'Görünen Stok' => $snapshot?->stock_quantity ?? '',
                'Güven Skoru' => $snapshot?->confidence_score ?? '',
                'Takip Durumu' => (string) ($product->tracking_status ?: 'candidate'),
                'Son Kontrol' => $product->last_checked_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') ?? '',
                'Kaynak URL' => (string) ($product->source_url ?: ''),
            ];
        })->values();
    }
}
