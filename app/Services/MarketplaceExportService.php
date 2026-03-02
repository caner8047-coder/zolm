<?php

namespace App\Services;

use App\Models\MpPeriod;
use App\Models\MpOrder;
use App\Models\MpAuditLog;
use App\Services\UnitEconomicsService;
use App\Services\ReportService;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Pazaryeri Muhasebe — Excel Export Servisi
 *
 * 4 farklı export tipi:
 * 1. Tüm Siparişler Detaylı (5N1K)
 * 2. Hatalı/Flag'li Siparişler (Audit Raporu)
 * 3. Aylık Pivot Özeti (KPI)
 * 4. Birim İktisadı Raporu
 *
 * /excel-export workflow kurallarına uyar:
 * - setCellValueExplicit ile tip güvenli yazma
 * - UTF-8 encoding + XML kontrol karakter temizleme
 * - Sheet ismi sanitizasyonu (max 31 karakter)
 */
class MarketplaceExportService
{
    // ═══════════════════════════════════════════════════════════════
    // 1) TÜM SİPARİŞLER DETAYLI LİSTESİ (5N1K)
    // ═══════════════════════════════════════════════════════════════

    public function exportAllOrders(MpPeriod $period): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sanitizeSheetName('Siparisler ' . $period->period_name));

        // Header
        $headers = [
            'Sipariş No', 'Tarih', 'Durum', 'Ürün Adı', 'Barkod', 'Stok Kodu',
            'Adet', 'Brüt Tutar (TL)', 'Komisyon (TL)', 'Kargo (TL)',
            'Stopaj (TL)', 'Hizmet Bedeli (TL)', 'Net Hakediş (TL)',
            'KDV Oranı (%)', 'Kargo Firması', 'Desi', 'COGS (TL)',
            'Ambalaj (TL)', 'Gerçek Net Kâr (TL)', 'Margin (%)',
            'Flagli', 'Komisyon Oranı (%)',
        ];

        $this->writeHeaderRow($sheet, $headers, 1);

        // Data
        $unitService = new UnitEconomicsService();
        $row = 2; // Pass by reference in use()

        MpOrder::where('period_id', $period->id)
            ->orderByDesc('order_date')
            ->chunk(1000, function ($orders) use ($sheet, &$row, $unitService) {
                foreach ($orders as $order) {
                    $econ = $order->status === 'Teslim Edildi'
                        ? $unitService->calculateForOrder($order)
                        : null;

            $col = 1;
            $this->writeCell($sheet, $row, $col++, $order->order_number, DataType::TYPE_STRING);
            $this->writeCell($sheet, $row, $col++, $order->order_date?->format('d.m.Y'), DataType::TYPE_STRING);
            $this->writeCell($sheet, $row, $col++, $order->status, DataType::TYPE_STRING);
            $this->writeCell($sheet, $row, $col++, $order->product_name, DataType::TYPE_STRING);
            $this->writeCell($sheet, $row, $col++, $order->barcode, DataType::TYPE_STRING);
            $this->writeCell($sheet, $row, $col++, $order->stock_code, DataType::TYPE_STRING);
            $this->writeCell($sheet, $row, $col++, $order->quantity, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, $row, $col++, $order->gross_amount, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, $row, $col++, $order->commission_amount, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, $row, $col++, $order->cargo_amount, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, $row, $col++, $order->withholding_tax, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, $row, $col++, $order->service_fee, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, $row, $col++, $order->net_hakedis, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, $row, $col++, $order->product_vat_rate, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, $row, $col++, $order->cargo_company, DataType::TYPE_STRING);
            $this->writeCell($sheet, $row, $col++, $order->cargo_desi, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, $row, $col++, $order->cogs_at_time, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, $row, $col++, $order->packaging_cost_at_time, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, $row, $col++, $econ ? $econ['real_net_profit'] : null, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, $row, $col++, $econ ? $econ['margin_percent'] : null, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, $row, $col++, $order->is_flagged ? 'EVET' : '', DataType::TYPE_STRING);
            $this->writeCell($sheet, $row, $col++, $order->commission_rate, DataType::TYPE_NUMERIC);

            // Bleeding satır kırmızı
            if ($econ && $econ['is_bleeding']) {
                $sheet->getStyle("A{$row}:V{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FEE2E2');
            }

            $row++;
        }
    });

        $this->autoSizeColumns($sheet, count($headers));

        return $this->save($spreadsheet, $period, 'siparisler-detay');
    }

    // ═══════════════════════════════════════════════════════════════
    // 2) HATALI / FLAG'Lİ SİPARİŞLER (AUDİT RAPORU)
    // ═══════════════════════════════════════════════════════════════

    public function exportAuditReport(MpPeriod $period): string
    {
        $spreadsheet = new Spreadsheet();

        // Sheet 1: Audit Logları
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle($this->sanitizeSheetName('Audit Sonuclari'));

        $headers1 = [
            'Kural Kodu', 'Önem', 'Başlık', 'Açıklama',
            'Beklenen (TL)', 'Gerçekleşen (TL)', 'Fark (TL)',
            'Sipariş No', 'Durum', 'Tarih',
        ];
        $this->writeHeaderRow($sheet1, $headers1, 1);

        $logs = MpAuditLog::where('period_id', $period->id)
            ->with('order')
            ->orderByRaw("FIELD(severity, 'critical', 'warning', 'info')")
            ->get();

        $row = 2;
        foreach ($logs as $log) {
            $col = 1;
            $this->writeCell($sheet1, $row, $col++, $log->rule_code, DataType::TYPE_STRING);
            $this->writeCell($sheet1, $row, $col++, $log->severity, DataType::TYPE_STRING);
            $this->writeCell($sheet1, $row, $col++, $log->title, DataType::TYPE_STRING);
            $this->writeCell($sheet1, $row, $col++, $log->description, DataType::TYPE_STRING);
            $this->writeCell($sheet1, $row, $col++, $log->expected_value, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet1, $row, $col++, $log->actual_value, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet1, $row, $col++, $log->difference, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet1, $row, $col++, $log->order?->order_number, DataType::TYPE_STRING);
            $this->writeCell($sheet1, $row, $col++, $log->status, DataType::TYPE_STRING);
            $this->writeCell($sheet1, $row, $col++, $log->created_at?->format('d.m.Y H:i'), DataType::TYPE_STRING);

            // Critical → kırmızı, Warning → sarı
            if ($log->severity === 'critical') {
                $sheet1->getStyle("A{$row}:J{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEE2E2');
            } elseif ($log->severity === 'warning') {
                $sheet1->getStyle("A{$row}:J{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
            }

            $row++;
        }

        $this->autoSizeColumns($sheet1, count($headers1));

        // Sheet 2: Flag'li Siparişler
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle($this->sanitizeSheetName('Flagli Siparisler'));

        $headers2 = [
            'Sipariş No', 'Tarih', 'Durum', 'Ürün Adı', 'Barkod',
            'Brüt Tutar (TL)', 'Komisyon (TL)', 'Kargo (TL)', 'Net Hakediş (TL)',
        ];
        $this->writeHeaderRow($sheet2, $headers2, 1);

        $flaggedOrders = MpOrder::where('period_id', $period->id)
            ->where('is_flagged', true)
            ->orderByDesc('order_date')
            ->get();

        $row = 2;
        foreach ($flaggedOrders as $order) {
            $col = 1;
            $this->writeCell($sheet2, $row, $col++, $order->order_number, DataType::TYPE_STRING);
            $this->writeCell($sheet2, $row, $col++, $order->order_date?->format('d.m.Y'), DataType::TYPE_STRING);
            $this->writeCell($sheet2, $row, $col++, $order->status, DataType::TYPE_STRING);
            $this->writeCell($sheet2, $row, $col++, $order->product_name, DataType::TYPE_STRING);
            $this->writeCell($sheet2, $row, $col++, $order->barcode, DataType::TYPE_STRING);
            $this->writeCell($sheet2, $row, $col++, $order->gross_amount, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet2, $row, $col++, $order->commission_amount, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet2, $row, $col++, $order->cargo_amount, DataType::TYPE_NUMERIC);
            $this->writeCell($sheet2, $row, $col++, $order->net_hakedis, DataType::TYPE_NUMERIC);
            $row++;
        }

        $this->autoSizeColumns($sheet2, count($headers2));

        return $this->save($spreadsheet, $period, 'audit-rapor');
    }

    // ═══════════════════════════════════════════════════════════════
    // 3) AYLIK PİVOT ÖZETİ (KPI)
    // ═══════════════════════════════════════════════════════════════

    public function exportMonthlyPivot(MpPeriod $period): string
    {
        $reportService = new ReportService();
        $pivot = $reportService->monthlyPivot($period->id);
        $kpis = $pivot['kpis'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sanitizeSheetName('Aylik Ozet'));

        // KPI Tablosu
        $sheet->setCellValueExplicit('A1', 'AYLIK RAPOR — ' . $this->cleanString($pivot['period_name']), DataType::TYPE_STRING);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->mergeCells('A1:C1');

        $kpiData = [
            ['KPI', 'Değer (TL)', 'Açıklama'],
            ['Toplam Brüt Ciro', $kpis['total_brut'], 'Başarılı siparişlerin müşteri ödeme toplamı'],
            ['Toplam Stopaj', $kpis['total_stopaj'], 'E-Ticaret stopajı (%1) kümülatif toplamı'],
            ['Lojistik Zararı (Yanık Maliyet)', $kpis['logistic_loss']['total'], 'İade gidiş + dönüş kargo zararı'],
            ['  → Gidiş Kargo (Sunk Cost)', $kpis['logistic_loss']['sunk_cargo'], ''],
            ['  → Dönüş Kargo Cezası', $kpis['logistic_loss']['return_cargo'], ''],
            ['Devlete Ödenecek Net KDV', $kpis['net_vat']['net_vat'], 'Satış KDV - Gider KDV mahsuplaşma'],
            ['  → Satış KDV', $kpis['net_vat']['sales_vat'], ''],
            ['  → Gider KDV (Komisyon+Kargo)', $kpis['net_vat']['expense_vat'], ''],
            ['Toplam Gerçek Net Kâr', $kpis['real_profit']['total_profit'], 'Hakediş - COGS - Ambalaj ± KDV'],
            ['Toplam Hakediş', $kpis['total_hakedis'], 'Trendyol net hakedişi toplamı'],
            ['Toplam Komisyon', $pivot['total_commission'], 'Trendyol komisyon kesintisi toplamı'],
            ['Toplam Kargo', $pivot['total_cargo'], 'Kargo kesintisi toplamı'],
        ];

        $row = 3;
        foreach ($kpiData as $i => $kpiRow) {
            $col = 1;
            foreach ($kpiRow as $j => $value) {
                $type = $j === 1 && $i > 0 ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING;
                $this->writeCell($sheet, $row, $col, $value, $type);
                $col++;
            }
            if ($i === 0) {
                $sheet->getStyle("A{$row}:C{$row}")->getFont()->setBold(true);
                $sheet->getStyle("A{$row}:C{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
            }
            $row++;
        }

        // Sipariş İstatistikleri
        $row += 2;
        $sheet->setCellValueExplicit("A{$row}", 'SİPARİŞ İSTATİSTİKLERİ', DataType::TYPE_STRING);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $row++;

        $statsData = [
            ['Toplam Sipariş', $kpis['total_orders']],
            ['İade Sipariş', $kpis['total_returns']],
            ['İptal Sipariş', $kpis['total_cancels']],
            ['İade Oranı (%)', $kpis['return_rate']],
            ['Kârlı Ürün Sayısı', $kpis['real_profit']['profitable_count']],
            ['Zararlı (Bleeding) Ürün', $kpis['real_profit']['bleeding_count']],
            ['Denetim Uyarıları', $kpis['audit_count']],
        ];

        foreach ($statsData as $stat) {
            $this->writeCell($sheet, $row, 1, $stat[0], DataType::TYPE_STRING);
            $this->writeCell($sheet, $row, 2, $stat[1], DataType::TYPE_NUMERIC);
            $row++;
        }

        // Durum dağılımı
        $row += 2;
        $sheet->setCellValueExplicit("A{$row}", 'DURUM DAĞILIMI', DataType::TYPE_STRING);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $row++;

        $this->writeCell($sheet, $row, 1, 'Durum', DataType::TYPE_STRING);
        $this->writeCell($sheet, $row, 2, 'Adet', DataType::TYPE_STRING);
        $this->writeCell($sheet, $row, 3, 'Tutar (TL)', DataType::TYPE_STRING);
        $sheet->getStyle("A{$row}:C{$row}")->getFont()->setBold(true);
        $row++;

        foreach ($pivot['status_breakdown'] as $status) {
            $this->writeCell($sheet, $row, 1, $status['status'], DataType::TYPE_STRING);
            $this->writeCell($sheet, $row, 2, $status['count'], DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, $row, 3, $status['total_amount'], DataType::TYPE_NUMERIC);
            $row++;
        }

        $this->autoSizeColumns($sheet, 3);

        return $this->save($spreadsheet, $period, 'aylik-ozet');
    }

    // ═══════════════════════════════════════════════════════════════
    // 4) MALİ MÜŞAVİR STOPAJ (193 KOD) İHRACI
    // ═══════════════════════════════════════════════════════════════

    public function exportStopajReport(MpPeriod $period): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sanitizeSheetName('193 Stopaj Kesintileri'));

        $headers = [
            'Sıra', 'Sipariş No', 'İşlem Tarihi', 'Brüt Satış (TL)',
            'Uygulanan İndirimler (TL)', 'KDV Hariç Stopaj Matrahı (TL)', 
            'Kesilen %1 Stopaj Tutarı (TL)', 'Durum'
        ];

        $this->writeHeaderRow($sheet, $headers, 1);

        $row = 2; // Pass by reference for chunk
        $totalBase = 0;
        $totalStopaj = 0;

        MpOrder::where('period_id', $period->id)
            ->whereNotIn('status', ['İptal Edildi'])
            ->where('withholding_tax', '>', 0)
            ->orderByDesc('order_date')
            ->chunk(1000, function ($orders) use ($sheet, &$row, &$totalBase, &$totalStopaj) {
                foreach ($orders as $index => $order) {
                    $discounts = (float) $order->discount_amount + (float) $order->campaign_discount;
                    $netGross = max(0, (float) $order->gross_amount - $discounts);
                    
                    // Vergi matrahı (Tahmini KDV arındırması - Stopaj matrahı genelde KDV hariç tutardır)
                    $vatRate = (float) $order->product_vat_rate / 100;
                    $vatExcludedBase = $netGross / (1 + $vatRate);

                    $col = 1;
                    $this->writeCell($sheet, $row, $col++, $index + 1, DataType::TYPE_NUMERIC);
                    $this->writeCell($sheet, $row, $col++, $order->order_number, DataType::TYPE_STRING);
                    $this->writeCell($sheet, $row, $col++, $order->order_date?->format('d.m.Y'), DataType::TYPE_STRING);
                    $this->writeCell($sheet, $row, $col++, $order->gross_amount, DataType::TYPE_NUMERIC);
                    $this->writeCell($sheet, $row, $col++, $discounts, DataType::TYPE_NUMERIC);
                    $this->writeCell($sheet, $row, $col++, $vatExcludedBase, DataType::TYPE_NUMERIC);
                    $this->writeCell($sheet, $row, $col++, $order->withholding_tax, DataType::TYPE_NUMERIC);
                    $this->writeCell($sheet, $row, $col++, $order->status, DataType::TYPE_STRING);

                    $totalBase += $vatExcludedBase;
                    $totalStopaj += (float) $order->withholding_tax;
                    $row++;
                }
            });

        // Toplam Satırı
        $this->writeCell($sheet, $row, 5, 'TOPLAM:', DataType::TYPE_STRING);
        $this->writeCell($sheet, $row, 6, $totalBase, DataType::TYPE_NUMERIC);
        $this->writeCell($sheet, $row, 7, $totalStopaj, DataType::TYPE_NUMERIC);
        
        $sheet->getStyle("E{$row}:G{$row}")->getFont()->setBold(true);
        $sheet->getStyle("E{$row}:G{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF08A');

        $this->autoSizeColumns($sheet, count($headers));

        return $this->save($spreadsheet, $period, 'stopaj-raporu');
    }

    // ═══════════════════════════════════════════════════════════════
    // 5) BİRİM İKTİSADI RAPORU
    // ═══════════════════════════════════════════════════════════════

    public function exportUnitEconomics(MpPeriod $period): string
    {
        $unitService = new UnitEconomicsService();

        $spreadsheet = new Spreadsheet();

        // Sheet 1: Sipariş bazlı
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle($this->sanitizeSheetName('Siparis Bazli'));

        $headers1 = [
            'Sipariş No', 'Barkod', 'Stok Kodu', 'Ürün Adı', 'Adet',
            'Brüt Tutar (TL)', 'Hakediş (TL)', 'Komisyon (TL)', 'Kargo (TL)',
            'COGS (TL)', 'Ambalaj (TL)', 'Satış KDV (TL)', 'Gider KDV (TL)',
            'Net KDV (TL)', 'Gerçek Net Kâr (TL)', 'Margin (%)', 'Kanayan',
        ];
        $this->writeHeaderRow($sheet1, $headers1, 1);

        $orderResults = $unitService->calculateForPeriod($period);
        $row = 2;

        foreach ($orderResults as $item) {
            $col = 1;
            $this->writeCell($sheet1, $row, $col++, $item['order_number'], DataType::TYPE_STRING);
            $this->writeCell($sheet1, $row, $col++, $item['barcode'], DataType::TYPE_STRING);
            $this->writeCell($sheet1, $row, $col++, $item['stock_code'], DataType::TYPE_STRING);
            $this->writeCell($sheet1, $row, $col++, $item['product_name'], DataType::TYPE_STRING);
            $this->writeCell($sheet1, $row, $col++, $item['quantity'], DataType::TYPE_NUMERIC);
            $this->writeCell($sheet1, $row, $col++, $item['gross_amount'], DataType::TYPE_NUMERIC);
            $this->writeCell($sheet1, $row, $col++, $item['hakedis'], DataType::TYPE_NUMERIC);
            $this->writeCell($sheet1, $row, $col++, $item['commission'], DataType::TYPE_NUMERIC);
            $this->writeCell($sheet1, $row, $col++, $item['cargo'], DataType::TYPE_NUMERIC);
            $this->writeCell($sheet1, $row, $col++, $item['cogs'], DataType::TYPE_NUMERIC);
            $this->writeCell($sheet1, $row, $col++, $item['packaging'], DataType::TYPE_NUMERIC);
            $this->writeCell($sheet1, $row, $col++, $item['sales_vat'], DataType::TYPE_NUMERIC);
            $this->writeCell($sheet1, $row, $col++, $item['expense_vat'], DataType::TYPE_NUMERIC);
            $this->writeCell($sheet1, $row, $col++, $item['net_vat'], DataType::TYPE_NUMERIC);
            $this->writeCell($sheet1, $row, $col++, $item['real_net_profit'], DataType::TYPE_NUMERIC);
            $this->writeCell($sheet1, $row, $col++, $item['margin_percent'], DataType::TYPE_NUMERIC);
            $this->writeCell($sheet1, $row, $col++, $item['is_bleeding'] ? 'EVET' : '', DataType::TYPE_STRING);

            if ($item['is_bleeding']) {
                $sheet1->getStyle("A{$row}:Q{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEE2E2');
            }

            $row++;
        }

        $this->autoSizeColumns($sheet1, count($headers1));

        // Sheet 2: SKU (Barkod) bazlı toplam
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle($this->sanitizeSheetName('SKU Bazli Kar'));

        $headers2 = [
            'Barkod', 'Stok Kodu', 'Ürün Adı', 'Sipariş Sayısı', 'Toplam Adet',
            'Toplam Ciro (TL)', 'Toplam Hakediş (TL)', 'Toplam COGS (TL)',
            'Toplam Ambalaj (TL)', 'Toplam Net Kâr (TL)', 'Ortalama Margin (%)',
            'Zararlı Sipariş', 'Kanayan',
        ];
        $this->writeHeaderRow($sheet2, $headers2, 1);

        $skuResults = $unitService->profitBySku($period);
        $row = 2;

        foreach ($skuResults as $item) {
            $col = 1;
            $this->writeCell($sheet2, $row, $col++, $item['barcode'], DataType::TYPE_STRING);
            $this->writeCell($sheet2, $row, $col++, $item['stock_code'], DataType::TYPE_STRING);
            $this->writeCell($sheet2, $row, $col++, $item['product_name'], DataType::TYPE_STRING);
            $this->writeCell($sheet2, $row, $col++, $item['order_count'], DataType::TYPE_NUMERIC);
            $this->writeCell($sheet2, $row, $col++, $item['total_quantity'], DataType::TYPE_NUMERIC);
            $this->writeCell($sheet2, $row, $col++, $item['total_gross'], DataType::TYPE_NUMERIC);
            $this->writeCell($sheet2, $row, $col++, $item['total_hakedis'], DataType::TYPE_NUMERIC);
            $this->writeCell($sheet2, $row, $col++, $item['total_cogs'], DataType::TYPE_NUMERIC);
            $this->writeCell($sheet2, $row, $col++, $item['total_packaging'], DataType::TYPE_NUMERIC);
            $this->writeCell($sheet2, $row, $col++, $item['total_net_profit'], DataType::TYPE_NUMERIC);
            $this->writeCell($sheet2, $row, $col++, $item['avg_margin'], DataType::TYPE_NUMERIC);
            $this->writeCell($sheet2, $row, $col++, $item['bleeding_count'], DataType::TYPE_NUMERIC);
            $this->writeCell($sheet2, $row, $col++, $item['is_bleeding'] ? 'EVET' : '', DataType::TYPE_STRING);

            if ($item['is_bleeding']) {
                $sheet2->getStyle("A{$row}:M{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEE2E2');
            }

            $row++;
        }

        $this->autoSizeColumns($sheet2, count($headers2));

        return $this->save($spreadsheet, $period, 'birim-iktisadi');
    }

    // ═══════════════════════════════════════════════════════════════
    // HELPER METHODS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Header satırını yaz (kalın + arkaplan)
     */
    protected function writeHeaderRow($sheet, array $headers, int $row): void
    {
        foreach ($headers as $i => $header) {
            $col = $i + 1;
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;
            $sheet->setCellValueExplicit($cell, $this->cleanString($header), DataType::TYPE_STRING);
        }

        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $range = "A{$row}:{$lastCol}{$row}";

        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F2937');
        $sheet->getStyle($range)->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    /**
     * Tek hücreye güvenli yazma (tip belirtilerek)
     */
    protected function writeCell($sheet, int $row, int $col, $value, string $type): void
    {
        $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;

        if ($value === null || $value === '') {
            $sheet->setCellValueExplicit($cell, '', DataType::TYPE_STRING);
            return;
        }

        if ($type === DataType::TYPE_STRING) {
            $sheet->setCellValueExplicit($cell, $this->cleanString((string) $value), DataType::TYPE_STRING);
        } else {
            $sheet->setCellValueExplicit($cell, (float) $value, DataType::TYPE_NUMERIC);
            $sheet->getStyle($cell)->getNumberFormat()
                ->setFormatCode('#,##0.00');
        }
    }

    /**
     * Sütun genişliklerini otomatik ayarla
     */
    protected function autoSizeColumns($sheet, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Sheet ismi sanitizasyonu (max 31 karakter, yasak karakterler)
     */
    protected function sanitizeSheetName(string $name): string
    {
        $name = str_replace([':', '\\', '/', '?', '*', '[', ']'], '', $name);
        return mb_substr($name, 0, 31);
    }

    /**
     * UTF-8 ve XML uyumlu string temizleme
     */
    protected function cleanString($value): string
    {
        if ($value === null) return '';
        if (!is_string($value)) return (string) $value;
        if (trim($value) === '') return '';

        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1254');
        }

        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        $value = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $value);

        return $value;
    }

    /**
     * Dosyayı kaydet ve yolunu döndür
     */
    protected function save(Spreadsheet $spreadsheet, MpPeriod $period, string $prefix): string
    {
        $dir = storage_path('app/exports');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = "mp-{$prefix}-{$period->year}-{$period->month}-" . now()->format('Ymd-His') . '.xlsx';
        $path = $dir . DIRECTORY_SEPARATOR . $filename;

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $path;
    }
}
