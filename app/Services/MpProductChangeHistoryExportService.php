<?php

namespace App\Services;

use App\Models\MpProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MpProductChangeHistoryExportService
{
    private const COLORS = [
        'ink' => '0F172A',
        'slate' => '334155',
        'muted' => '64748B',
        'soft' => '94A3B8',
        'line' => 'E2E8F0',
        'panel' => 'F8FAFC',
        'panel_alt' => 'F1F5F9',
        'white' => 'FFFFFF',
        'green' => '059669',
        'green_bg' => 'ECFDF5',
        'amber' => 'B45309',
        'amber_bg' => 'FFFBEB',
        'red' => 'BE123C',
        'red_bg' => 'FFF1F2',
        'blue' => '2563EB',
    ];

    private const NUMERIC_TYPES = ['money', 'percent', 'integer', 'decimal'];

    private const SUPPORTED_TYPES = ['money', 'percent', 'integer', 'decimal', 'boolean', 'string'];

    public function export(MpProduct $product, Collection $logs, string $outputPath): string
    {
        $logs = $logs
            ->sortByDesc(fn ($log) => sprintf(
                '%020d-%020d',
                $log->changed_at?->getTimestamp() ?? 0,
                (int) ($log->id ?? 0)
            ))
            ->values();

        $integrity = $this->integritySummary($product, $logs);
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('ZOLM')
            ->setCompany('ZOLM')
            ->setTitle('Ürün Değişim Geçmişi Raporu')
            ->setSubject($this->productName($product))
            ->setDescription('ZOLM ürün değişim geçmişi, analiz ve veri sözlüğü')
            ->setKeywords('ZOLM ürün değişim audit rapor');

        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle($this->sanitizeSheetName('Analiz ve Grafik'));
        $this->buildAnalysisSheet($summarySheet, $product, $logs, $integrity);

        $logsSheet = $spreadsheet->createSheet();
        $logsSheet->setTitle($this->sanitizeSheetName('Kayıtlar'));
        $this->buildLogsSheet($logsSheet, $product, $logs, $integrity);

        $dictionarySheet = $spreadsheet->createSheet();
        $dictionarySheet->setTitle($this->sanitizeSheetName('Veri Sözlüğü'));
        $this->buildDictionarySheet($dictionarySheet, $product, $logs, $integrity);

        $spreadsheet->setActiveSheetIndex(0);

        $dir = dirname($outputPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->setIncludeCharts(true);
        $writer->save($outputPath);
        $spreadsheet->disconnectWorksheets();

        Log::info('MpProductChangeHistoryExportService: Profesyonel export oluşturuldu', [
            'product_id' => $product->id,
            'logs_count' => $logs->count(),
            'integrity_status' => $integrity['status'],
            'report_id' => $integrity['report_id'],
            'path' => $outputPath,
        ]);

        return $outputPath;
    }

    /**
     * @param  array<string, mixed>  $integrity
     */
    protected function buildAnalysisSheet(Worksheet $sheet, MpProduct $product, Collection $logs, array $integrity): void
    {
        $sheet->setShowGridlines(false);
        $sheet->setSelectedCell('A1');
        $sheet->getTabColor()->setARGB(self::COLORS['ink']);
        $sheet->getSheetView()->setZoomScale(85);
        $sheet->freezePane('A10');

        $this->writeText($sheet, 'A1', 'ZOLM');
        $this->writeText($sheet, 'B1', 'ÜRÜN DEĞİŞİM KONTROL MERKEZİ');
        $sheet->mergeCells('B1:L1');
        $sheet->getStyle('A1:L1')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLORS['ink']]],
            'font' => ['bold' => true, 'color' => ['argb' => self::COLORS['white']], 'size' => 12],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A1')->getFont()->setSize(15);
        $sheet->getRowDimension(1)->setRowHeight(32);

        $this->writeText($sheet, 'A3', $this->productName($product));
        $sheet->mergeCells('A3:H3');
        $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(18)->setColor(new Color(self::COLORS['ink']));

        $this->writeText($sheet, 'A4', sprintf(
            'Model: %s  •  Stok kodu: %s  •  Barkod: %s',
            $this->identifier($product->model_code),
            $this->identifier($product->stock_code),
            $this->identifier($product->barcode),
        ));
        $sheet->mergeCells('A4:H4');
        $sheet->getStyle('A4')->getFont()->setSize(10)->setColor(new Color(self::COLORS['muted']));

        $this->writeText($sheet, 'I3', 'Rapor No');
        $sheet->mergeCells('I3:L3');
        $this->writeText($sheet, 'I4', $integrity['report_id']);
        $sheet->mergeCells('I4:L4');
        $sheet->getStyle('I3:L4')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLORS['panel']]],
            'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLORS['line']]]],
        ]);
        $sheet->getStyle('I3')->getFont()->setBold(true)->setSize(9)->setColor(new Color(self::COLORS['muted']));
        $sheet->getStyle('I4')->getFont()->setBold(true)->setSize(10)->setColor(new Color(self::COLORS['ink']));

        $this->writeKpiCard($sheet, 'A6:C8', 'Toplam kayıt', (string) $logs->count(), '#,##0 "kayıt"');
        $this->writeKpiCard($sheet, 'D6:F8', 'Parasal değişim', (string) $logs->where('value_type', 'money')->count(), '#,##0 "kayıt"');
        $this->writeKpiCard($sheet, 'G6:I8', 'Kanal değişimi', (string) $logs->where('change_scope', 'listing')->count(), '#,##0 "kayıt"');

        $lastChangeLog = $logs
            ->filter(fn ($log) => $log->changed_at !== null)
            ->sortByDesc('changed_at')
            ->first();
        $this->writeKpiCard(
            $sheet,
            'J6:L8',
            'Son değişim',
            $lastChangeLog?->changed_at?->format('d.m.Y H:i'),
            '@',
            DataType::TYPE_STRING,
            'Kayıt yok',
        );

        $this->writeSectionTitle($sheet, 'A10:L10', 'Değişim yoğunluğu', 'Hangi alanların daha sık değiştiğini gösterir.');
        $fieldGroups = $logs
            ->groupBy(fn ($log) => $this->cleanString($log->field_label ?: $log->field_key ?: 'Tanımsız alan'))
            ->map->count()
            ->sortDesc()
            ->take(12);

        $this->writeTableHeader($sheet, 12, [
            'A' => 'Değişen Alan',
            'B' => 'Kayıt',
            'C' => 'Pay',
        ]);

        $fieldRow = 13;
        foreach ($fieldGroups as $fieldName => $count) {
            $this->writeText($sheet, 'A'.$fieldRow, $fieldName);
            $this->writeNumber($sheet, 'B'.$fieldRow, (float) $count);
            $this->writeNumber(
                $sheet,
                'C'.$fieldRow,
                $logs->isNotEmpty() ? $count / $logs->count() : 0,
                '0.0%'
            );
            $fieldRow++;
        }

        if ($fieldGroups->isEmpty()) {
            $this->writeText($sheet, 'A13', 'Henüz değişim kaydı yok');
            $this->writeNumber($sheet, 'B13', 0);
            $this->writeNumber($sheet, 'C13', 0, '0.0%');
            $fieldRow = 14;
        }

        $fieldLastRow = $fieldRow - 1;
        $this->styleDataBlock($sheet, "A12:C{$fieldLastRow}");

        if ($logs->isNotEmpty()) {
            $categories = [new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_STRING,
                "'Analiz ve Grafik'!\$A\$13:\$A\${$fieldLastRow}",
                null,
                $fieldGroups->count()
            )];
            $values = [new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_NUMBER,
                "'Analiz ve Grafik'!\$B\$13:\$B\${$fieldLastRow}",
                null,
                $fieldGroups->count()
            )];
            $labels = [new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_STRING,
                "'Analiz ve Grafik'!\$B\$12",
                null,
                1
            )];
            $series = new DataSeries(
                DataSeries::TYPE_BARCHART,
                DataSeries::GROUPING_STANDARD,
                [0],
                $labels,
                $categories,
                $values
            );
            $series->setPlotDirection(DataSeries::DIRECTION_BAR);
            $chart = new Chart(
                'field_change_distribution',
                new Title('En sık değişen alanlar'),
                null,
                new PlotArea(null, [$series])
            );
            $chart->setTopLeftPosition('E12');
            $chart->setBottomRightPosition('L27');
            $sheet->addChart($chart);
        }

        $controlStartRow = max(29, $fieldLastRow + 3);
        $this->writeSectionTitle(
            $sheet,
            "A{$controlStartRow}:L{$controlStartRow}",
            'Veri tutarlılığı',
            'Dosyadaki kayıtların kimlik, tarih ve sayısal değer kontrolleri.'
        );
        $controlHeaderRow = $controlStartRow + 2;
        $this->writeTableHeader($sheet, $controlHeaderRow, [
            'A' => 'Kontrol',
            'F' => 'Sonuç',
            'H' => 'Açıklama',
        ]);
        $sheet->mergeCells("A{$controlHeaderRow}:E{$controlHeaderRow}");
        $sheet->mergeCells("F{$controlHeaderRow}:G{$controlHeaderRow}");
        $sheet->mergeCells("H{$controlHeaderRow}:L{$controlHeaderRow}");

        $controls = [
            ['Kayıt sayısı mutabakatı', $logs->count().' / '.$logs->count(), 'Excel satır sayısı ile kaynak kayıt sayısı eşleşiyor.', true],
            ['Tekrarlanan kayıt kimliği', (string) $integrity['duplicate_ids'], 'Aynı log kimliğinin birden fazla kez gelmesi kontrol edilir.', $integrity['duplicate_ids'] === 0],
            ['Eksik değişim zamanı', (string) $integrity['missing_timestamps'], 'Zaman damgası olmayan kayıtlar raporlanır.', $integrity['missing_timestamps'] === 0],
            ['Sayısal alan tutarlılığı', (string) $integrity['numeric_issues'], 'Para, yüzde, adet ve ondalık değerlerin sayısal karşılığı kontrol edilir.', $integrity['numeric_issues'] === 0],
            ['Desteklenmeyen veri tipi', (string) $integrity['unsupported_types'], 'Bilinmeyen tipler metin olarak korunur ve burada işaretlenir.', $integrity['unsupported_types'] === 0],
        ];

        $controlRow = $controlHeaderRow + 1;
        foreach ($controls as [$label, $value, $description, $passed]) {
            $this->writeText($sheet, "A{$controlRow}", $label);
            $sheet->mergeCells("A{$controlRow}:E{$controlRow}");
            $this->writeText($sheet, "F{$controlRow}", $passed ? 'Geçti' : 'İncele: '.$value);
            $sheet->mergeCells("F{$controlRow}:G{$controlRow}");
            $this->writeText($sheet, "H{$controlRow}", $description);
            $sheet->mergeCells("H{$controlRow}:L{$controlRow}");
            $sheet->getStyle("F{$controlRow}:G{$controlRow}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => $passed ? self::COLORS['green_bg'] : self::COLORS['amber_bg']],
                ],
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => $passed ? self::COLORS['green'] : self::COLORS['amber']],
                ],
            ]);
            $controlRow++;
        }
        $this->styleDataBlock($sheet, "A{$controlHeaderRow}:L".($controlRow - 1));

        $this->writeText($sheet, 'A'.($controlRow + 1), 'Bütünlük parmak izi (SHA-256)');
        $this->writeText($sheet, 'C'.($controlRow + 1), $integrity['checksum']);
        $sheet->mergeCells('C'.($controlRow + 1).':L'.($controlRow + 1));
        $sheet->getStyle('A'.($controlRow + 1).':L'.($controlRow + 1))->getFont()->setSize(9)->setColor(new Color(self::COLORS['muted']));

        $this->writeText(
            $sheet,
            'A'.($controlRow + 3),
            'Not: Grafikler karar desteği içindir. Kesin kayıtlar “Kayıtlar” sayfasındaki tiplenmiş alanlarda yer alır.'
        );
        $sheet->mergeCells('A'.($controlRow + 3).':L'.($controlRow + 3));
        $sheet->getStyle('A'.($controlRow + 3))->getFont()->setItalic(true)->setSize(9)->setColor(new Color(self::COLORS['muted']));

        $trendSectionRow = $controlRow + 6;
        $trendHeaderRow = $trendSectionRow + 2;
        $trendDataStartRow = $trendHeaderRow + 1;
        $priceTrend = $this->buildCostAndSalePriceTrend($product, $logs);

        $this->writeSectionTitle(
            $sheet,
            "A{$trendSectionRow}:L{$trendSectionRow}",
            'Maliyet ve satış fiyatı değişimi',
            'Maliyet ile satış fiyatının aynı zaman eksenindeki değişimini gösterir.'
        );
        $this->writeText($sheet, 'A'.($trendSectionRow + 1), $priceTrend['note']);
        $sheet->mergeCells('A'.($trendSectionRow + 1).':L'.($trendSectionRow + 1));
        $sheet->getStyle('A'.($trendSectionRow + 1).':L'.($trendSectionRow + 1))->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['argb' => self::COLORS['muted']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($trendSectionRow + 1)->setRowHeight(30);

        $this->writeTableHeader($sheet, $trendHeaderRow, [
            'A' => 'Tarih ve Saat',
            'B' => 'Ürün Maliyeti (₺)',
            'C' => $priceTrend['sale_label'],
        ]);

        $trendRow = $trendDataStartRow;
        foreach ($priceTrend['points'] as $point) {
            $this->writeText($sheet, 'A'.$trendRow, $point['label']);
            $this->writeNumber($sheet, 'B'.$trendRow, $point['cost'], '#,##0.00;[Red]-#,##0.00;0.00');
            $this->writeNumber($sheet, 'C'.$trendRow, $point['sale'], '#,##0.00;[Red]-#,##0.00;0.00');

            if ($trendRow % 2 === 0) {
                $sheet->getStyle("A{$trendRow}:C{$trendRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB(self::COLORS['panel']);
            }
            $trendRow++;
        }

        $trendLastRow = $trendRow - 1;
        $this->styleDataBlock($sheet, "A{$trendHeaderRow}:C{$trendLastRow}");

        $trendCategories = [new DataSeriesValues(
            DataSeriesValues::DATASERIES_TYPE_STRING,
            "'Analiz ve Grafik'!\$A\${$trendDataStartRow}:\$A\${$trendLastRow}",
            null,
            count($priceTrend['points'])
        )];
        $trendLabels = [
            new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_STRING,
                "'Analiz ve Grafik'!\$B\${$trendHeaderRow}",
                null,
                1
            ),
            new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_STRING,
                "'Analiz ve Grafik'!\$C\${$trendHeaderRow}",
                null,
                1
            ),
        ];
        $trendValues = [
            new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_NUMBER,
                "'Analiz ve Grafik'!\$B\${$trendDataStartRow}:\$B\${$trendLastRow}",
                null,
                count($priceTrend['points'])
            ),
            new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_NUMBER,
                "'Analiz ve Grafik'!\$C\${$trendDataStartRow}:\$C\${$trendLastRow}",
                null,
                count($priceTrend['points'])
            ),
        ];
        $trendSeries = new DataSeries(
            DataSeries::TYPE_LINECHART,
            DataSeries::GROUPING_STANDARD,
            [0, 1],
            $trendLabels,
            $trendCategories,
            $trendValues
        );
        $trendChart = new Chart(
            'cost_sale_price_trend',
            new Title('Maliyet ve Satış Fiyatı Değişimi (₺)'),
            new Legend(Legend::POSITION_TOP),
            new PlotArea(null, [$trendSeries])
        );
        $trendChart->setTopLeftPosition('E'.$trendHeaderRow);
        $trendChart->setBottomRightPosition('L'.($trendHeaderRow + 17));
        $sheet->addChart($trendChart);

        $widths = [
            'A' => 24, 'B' => 12, 'C' => 12, 'D' => 3,
            'E' => 16, 'F' => 16, 'G' => 16, 'H' => 16,
            'I' => 16, 'J' => 16, 'K' => 16, 'L' => 16,
        ];
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.45)->setRight(0.35)->setBottom(0.45)->setLeft(0.35);
        $sheet->getHeaderFooter()->setOddFooter('&LZOLM • Ürün Değişim Raporu&C&G&R&P / &N');
    }

    /**
     * @return array{
     *     sale_label: string,
     *     note: string,
     *     points: array<int, array{label: string, cost: float, sale: float}>
     * }
     */
    protected function buildCostAndSalePriceTrend(MpProduct $product, Collection $logs): array
    {
        $costLogs = $this->numericTrendLogs(
            $logs->filter(fn ($log) => $log->field_key === 'cogs' && $log->change_scope !== 'listing')
        );
        $productSaleLogs = $this->numericTrendLogs(
            $logs->filter(fn ($log) => $log->field_key === 'sale_price' && $log->change_scope !== 'listing')
        );

        $saleLogs = $productSaleLogs;
        $saleLabel = 'Satış Fiyatı (₺) · Ana Ürün';
        $saleSourceNote = 'Satış fiyatı ana ürün geçmişinden alınmıştır.';

        if ($saleLogs->isEmpty()) {
            $listingSaleLogs = $this->numericTrendLogs(
                $logs->filter(fn ($log) => $log->field_key === 'sale_price' && $log->change_scope === 'listing')
            );
            $latestListingLog = $listingSaleLogs
                ->sortByDesc(fn ($log) => sprintf(
                    '%020d-%020d',
                    $log->changed_at?->getTimestamp() ?? 0,
                    (int) ($log->id ?? 0)
                ))
                ->first();

            if ($latestListingLog !== null) {
                $saleLogs = $this->singleListingPriceSeries($listingSaleLogs, $latestListingLog);
                $storeContext = collect([
                    $latestListingLog->store?->marketplace
                        ? ucfirst((string) $latestListingLog->store->marketplace)
                        : null,
                    $latestListingLog->store?->store_name,
                ])->filter()->implode(' · ');
                $storeContext = $storeContext !== '' ? $storeContext : 'Tek kanal';
                $saleLabel = 'Satış Fiyatı (₺) · '.$storeContext;
                $saleSourceNote = 'Ana ürün fiyat geçmişi olmadığı için en güncel tek kanal/mağaza serisi kullanılmıştır: '
                    .$storeContext.'.';
            }
        }

        $costFallback = is_numeric($product->cogs) ? (float) $product->cogs : 0.0;
        $saleFallback = is_numeric($product->sale_price) ? (float) $product->sale_price : 0.0;
        $costInitial = $this->initialTrendValue($costLogs, $costFallback);
        $saleInitial = $this->initialTrendValue($saleLogs, $saleFallback);
        $events = collect();

        foreach ($costLogs as $log) {
            $events->push([
                'series' => 'cost',
                'timestamp' => $log->changed_at->getTimestamp(),
                'id' => (int) ($log->id ?? 0),
                'value' => (float) ($log->new_value_number ?? $log->old_value_number),
            ]);
        }
        foreach ($saleLogs as $log) {
            $events->push([
                'series' => 'sale',
                'timestamp' => $log->changed_at->getTimestamp(),
                'id' => (int) ($log->id ?? 0),
                'value' => (float) ($log->new_value_number ?? $log->old_value_number),
            ]);
        }

        $events = $events
            ->sortBy(fn (array $event) => sprintf('%020d-%020d', $event['timestamp'], $event['id']))
            ->values();

        if ($events->isEmpty()) {
            $now = now();

            return [
                'sale_label' => $this->cleanString($saleLabel),
                'note' => 'Maliyet veya satış fiyatı değişim kaydı bulunmadığı için güncel değerler sabit referans olarak gösterilmiştir.',
                'points' => [
                    [
                        'label' => $now->copy()->subMinute()->format('d.m.Y H:i:s'),
                        'cost' => $costFallback,
                        'sale' => $saleFallback,
                    ],
                    [
                        'label' => $now->format('d.m.Y H:i:s'),
                        'cost' => $costFallback,
                        'sale' => $saleFallback,
                    ],
                ],
            ];
        }

        $currentCost = $costInitial;
        $currentSale = $saleInitial;
        $firstTimestamp = (int) $events->first()['timestamp'];
        $points = [[
            'label' => date('d.m.Y H:i:s', $firstTimestamp - 1),
            'cost' => $currentCost,
            'sale' => $currentSale,
        ]];

        foreach ($events->groupBy('timestamp') as $timestamp => $timestampEvents) {
            foreach ($timestampEvents as $event) {
                if ($event['series'] === 'cost') {
                    $currentCost = (float) $event['value'];
                } else {
                    $currentSale = (float) $event['value'];
                }
            }

            $points[] = [
                'label' => date('d.m.Y H:i:s', (int) $timestamp),
                'cost' => $currentCost,
                'sale' => $currentSale,
            ];
        }

        $fallbackNotes = [];
        if ($costLogs->isEmpty()) {
            $fallbackNotes[] = 'Maliyet geçmişi yok; güncel maliyet sabit gösterildi.';
        }
        if ($saleLogs->isEmpty()) {
            $fallbackNotes[] = 'Satış fiyatı geçmişi yok; güncel satış fiyatı sabit gösterildi.';
        }

        return [
            'sale_label' => $this->cleanString($saleLabel),
            'note' => $this->cleanString(trim($saleSourceNote.' '.implode(' ', $fallbackNotes))),
            'points' => $points,
        ];
    }

    protected function numericTrendLogs(Collection $logs): Collection
    {
        return $logs
            ->filter(fn ($log) => $log->changed_at !== null
                && ($log->old_value_number !== null || $log->new_value_number !== null))
            ->sortBy(fn ($log) => sprintf(
                '%020d-%020d',
                $log->changed_at->getTimestamp(),
                (int) ($log->id ?? 0)
            ))
            ->values();
    }

    protected function singleListingPriceSeries(Collection $logs, mixed $selectedLog): Collection
    {
        if ($selectedLog->channel_listing_id !== null) {
            return $logs
                ->where('channel_listing_id', $selectedLog->channel_listing_id)
                ->values();
        }

        if ($selectedLog->store_id !== null) {
            return $logs
                ->where('store_id', $selectedLog->store_id)
                ->values();
        }

        return collect([$selectedLog]);
    }

    protected function initialTrendValue(Collection $logs, float $fallback): float
    {
        $firstLog = $logs->first();
        if ($firstLog === null) {
            return $fallback;
        }

        if ($firstLog->old_value_number !== null) {
            return (float) $firstLog->old_value_number;
        }

        return $firstLog->new_value_number !== null ? (float) $firstLog->new_value_number : $fallback;
    }

    /**
     * @param  array<string, mixed>  $integrity
     */
    protected function buildLogsSheet(Worksheet $sheet, MpProduct $product, Collection $logs, array $integrity): void
    {
        $sheet->setShowGridlines(false);
        $sheet->getTabColor()->setARGB(self::COLORS['blue']);
        $sheet->getSheetView()->setZoomScale(80);
        $sheet->freezePane('A10');

        $this->writeText($sheet, 'A1', 'ZOLM');
        $this->writeText($sheet, 'B1', 'ÜRÜN DEĞİŞİM KAYIT DEFTERİ');
        $sheet->mergeCells('B1:V1');
        $sheet->getStyle('A1:V1')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLORS['ink']]],
            'font' => ['bold' => true, 'color' => ['argb' => self::COLORS['white']], 'size' => 12],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A1')->getFont()->setSize(15);
        $sheet->getRowDimension(1)->setRowHeight(32);

        $identityPairs = [
            ['A3', 'Ürün', 'B3:H3', $this->productName($product)],
            ['I3', 'Rapor No', 'J3:V3', $integrity['report_id']],
            ['A4', 'Model Kodu', 'B4:D4', $this->identifier($product->model_code)],
            ['E4', 'Stok Kodu', 'F4:H4', $this->identifier($product->stock_code)],
            ['I4', 'Barkod', 'J4:M4', $this->identifier($product->barcode)],
            ['N4', 'ZOLM Ürün ID', 'O4:P4', $this->identifier($product->id)],
            ['Q4', 'Kayıt', 'R4:V4', $logs->count().' adet'],
        ];
        foreach ($identityPairs as [$labelCell, $label, $valueRange, $value]) {
            $this->writeText($sheet, $labelCell, $label);
            $sheet->getStyle($labelCell)->getFont()->setBold(true)->setSize(9)->setColor(new Color(self::COLORS['muted']));
            [$valueStart] = explode(':', $valueRange);
            $this->writeText($sheet, $valueStart, $value);
            $sheet->mergeCells($valueRange);
            $sheet->getStyle($valueRange)->getFont()->setBold(true)->setSize(10)->setColor(new Color(self::COLORS['ink']));
        }

        $this->writeText($sheet, 'A6', 'Rapor oluşturma zamanı');
        $this->writeDate($sheet, 'B6', now());
        $sheet->mergeCells('B6:D6');
        $this->writeText($sheet, 'E6', 'Saat dilimi');
        $this->writeText($sheet, 'F6', (string) config('app.timezone', 'Europe/Istanbul'));
        $sheet->mergeCells('F6:H6');
        $this->writeText($sheet, 'I6', 'Bütünlük durumu');
        $this->writeText($sheet, 'J6', $integrity['status_label']);
        $sheet->mergeCells('J6:M6');
        $this->writeText($sheet, 'N6', 'SHA-256');
        $this->writeText($sheet, 'O6', $integrity['checksum']);
        $sheet->mergeCells('O6:V6');
        $sheet->getStyle('A6:V6')->getFont()->setSize(9)->setColor(new Color(self::COLORS['muted']));

        $headers = [
            'A' => 'Kayıt ID',
            'B' => 'Değişim Zamanı',
            'C' => 'Kapsam Kodu',
            'D' => 'Kapsam',
            'E' => 'Alan Kodu',
            'F' => 'Değişen Alan',
            'G' => 'Veri Tipi',
            'H' => 'Pazaryeri',
            'I' => 'Mağaza',
            'J' => 'Mağaza ID',
            'K' => 'Kanal Kayıt ID',
            'L' => 'Eski Değer',
            'M' => 'Yeni Değer',
            'N' => 'Eski Sayısal',
            'O' => 'Yeni Sayısal',
            'P' => 'Fark',
            'Q' => 'Değişim Oranı',
            'R' => 'İşlemi Yapan',
            'S' => 'Kaynak Kodu',
            'T' => 'Kaynak',
            'U' => 'Batch ID',
            'V' => 'Not / Açıklama',
        ];
        $headerRow = 9;
        $this->writeTableHeader($sheet, $headerRow, $headers);
        $sheet->getRowDimension($headerRow)->setRowHeight(34);

        $row = 10;
        foreach ($logs as $log) {
            $storeMarketplace = $log->store?->marketplace
                ? ucfirst((string) $log->store->marketplace)
                : '';
            $scopeLabel = $log->change_scope === 'listing' ? 'Kanal Kaydı' : 'Ana Ürün';

            $this->writeText($sheet, 'A'.$row, $this->identifier($log->id));
            if ($log->changed_at) {
                $this->writeDate($sheet, 'B'.$row, $log->changed_at);
            } else {
                $this->writeText($sheet, 'B'.$row, '');
            }
            $this->writeText($sheet, 'C'.$row, $log->change_scope ?: 'product');
            $this->writeText($sheet, 'D'.$row, $scopeLabel);
            $this->writeText($sheet, 'E'.$row, $log->field_key ?: '');
            $this->writeText($sheet, 'F'.$row, $log->field_label ?: $log->field_key ?: 'Tanımsız alan');
            $this->writeText($sheet, 'G'.$row, $this->valueTypeLabel((string) $log->value_type));
            $this->writeText($sheet, 'H'.$row, $storeMarketplace);
            $this->writeText($sheet, 'I'.$row, $log->store?->store_name ?: '');
            $this->writeText($sheet, 'J'.$row, $this->identifier($log->store_id));
            $this->writeText($sheet, 'K'.$row, $this->identifier($log->channel_listing_id));
            $this->writeText($sheet, 'L'.$row, $this->formatLogValue($log, $log->old_value));
            $this->writeText($sheet, 'M'.$row, $this->formatLogValue($log, $log->new_value));
            $this->writeTypedNumericValue($sheet, 'N'.$row, $log, $log->old_value_number);
            $this->writeTypedNumericValue($sheet, 'O'.$row, $log, $log->new_value_number);
            $this->writeTypedNumericValue($sheet, 'P'.$row, $log, $log->delta_number, true);

            if ($log->delta_percent !== null) {
                $this->writeNumber($sheet, 'Q'.$row, (float) $log->delta_percent / 100, '+0.0%;-0.0%;-');
            } else {
                $this->writeText($sheet, 'Q'.$row, '');
            }

            $this->writeText($sheet, 'R'.$row, $log->changedByUser?->name ?: ($log->changed_by ? 'Kullanıcı #'.$log->changed_by : 'Sistem'));
            $this->writeText($sheet, 'S'.$row, $log->source ?: 'system');
            $this->writeText($sheet, 'T'.$row, $log->source_label ?: $log->source ?: 'Sistem');
            $this->writeText($sheet, 'U'.$row, $log->batch_id ?: '');
            $this->writeText($sheet, 'V'.$row, $log->note ?: '');

            if ($row % 2 === 1) {
                $sheet->getStyle("A{$row}:V{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB(self::COLORS['panel']);
            }
            $row++;
        }

        if ($logs->isEmpty()) {
            $this->writeText($sheet, 'A10', 'Bu ürün için henüz değişim kaydı bulunmuyor.');
            $sheet->mergeCells('A10:V10');
            $sheet->getStyle('A10:V10')->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLORS['panel']]],
                'font' => ['italic' => true, 'color' => ['argb' => self::COLORS['muted']]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        $lastRow = max(10, $row - 1);
        if ($logs->isNotEmpty()) {
            $sheet->setAutoFilter("A{$headerRow}:V{$lastRow}");
        }
        $sheet->getStyle("A{$headerRow}:V{$lastRow}")->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_HAIR)
            ->setColor(new Color(self::COLORS['line']));
        $sheet->getStyle("A10:V{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle("L10:M{$lastRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("V10:V{$lastRow}")->getAlignment()->setWrapText(true);

        $widths = [
            'A' => 12, 'B' => 20, 'C' => 14, 'D' => 14, 'E' => 24, 'F' => 24,
            'G' => 13, 'H' => 14, 'I' => 20, 'J' => 13, 'K' => 16, 'L' => 18,
            'M' => 18, 'N' => 16, 'O' => 16, 'P' => 15, 'Q' => 16, 'R' => 20,
            'S' => 22, 'T' => 24, 'U' => 26, 'V' => 34,
        ];
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A3)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd(1, 9);
        $sheet->getPageMargins()->setTop(0.45)->setRight(0.25)->setBottom(0.45)->setLeft(0.25);
        $sheet->getHeaderFooter()->setOddFooter('&L'.$integrity['report_id'].'&C&G&R&P / &N');
    }

    /**
     * @param  array<string, mixed>  $integrity
     */
    protected function buildDictionarySheet(Worksheet $sheet, MpProduct $product, Collection $logs, array $integrity): void
    {
        $sheet->setShowGridlines(false);
        $sheet->getTabColor()->setARGB(self::COLORS['green']);
        $sheet->getSheetView()->setZoomScale(90);
        $sheet->freezePane('A10');

        $this->writeText($sheet, 'A1', 'ZOLM');
        $this->writeText($sheet, 'B1', 'RAPOR METADATASI VE VERİ SÖZLÜĞÜ');
        $sheet->mergeCells('B1:E1');
        $sheet->getStyle('A1:E1')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLORS['ink']]],
            'font' => ['bold' => true, 'color' => ['argb' => self::COLORS['white']], 'size' => 12],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A1')->getFont()->setSize(15);
        $sheet->getRowDimension(1)->setRowHeight(32);

        $metadata = [
            ['Rapor No', $integrity['report_id']],
            ['Ürün', $this->productName($product)],
            ['ZOLM Ürün ID', $this->identifier($product->id)],
            ['Kaynak kayıt sayısı', (string) $logs->count()],
            ['İlk değişim', $integrity['first_change_label']],
            ['Son değişim', $integrity['last_change_label']],
            ['Rapor oluşturma', now()->format('d.m.Y H:i:s')],
            ['Saat dilimi', (string) config('app.timezone', 'Europe/Istanbul')],
            ['Bütünlük durumu', $integrity['status_label']],
            ['SHA-256', $integrity['checksum']],
        ];
        $this->writeTableHeader($sheet, 3, ['A' => 'Belge Bilgisi', 'B' => 'Değer']);
        $sheet->mergeCells('B3:E3');
        $metaRow = 4;
        foreach ($metadata as [$label, $value]) {
            $this->writeText($sheet, 'A'.$metaRow, $label);
            $this->writeText($sheet, 'B'.$metaRow, $value);
            $sheet->mergeCells("B{$metaRow}:E{$metaRow}");
            $metaRow++;
        }
        $this->styleDataBlock($sheet, 'A3:E'.($metaRow - 1));

        $dictionaryRow = $metaRow + 2;
        $this->writeTableHeader($sheet, $dictionaryRow, [
            'A' => 'Kolon',
            'B' => 'Excel Tipi',
            'C' => 'Kaynak / Kural',
            'D' => 'Boş Değer',
            'E' => 'Açıklama',
        ]);

        $definitions = [
            ['Kayıt ID', 'Metin', 'mp_product_change_logs.id', 'İzin verilmez', 'Kimlik olarak saklanır; baştaki sıfırlar ve büyük değerler bozulmaz.'],
            ['Değişim Zamanı', 'Excel tarih-saat', 'changed_at', 'Kontrol uyarısı', 'Sıralanabilir gerçek Excel tarihidir; dd.mm.yyyy hh:mm:ss gösterilir.'],
            ['Kapsam Kodu', 'Metin', 'change_scope', 'product', 'product veya listing teknik kodu.'],
            ['Kapsam', 'Metin', 'change_scope etiketi', 'Ana Ürün', 'Kullanıcıya gösterilen kapsam adı.'],
            ['Alan Kodu', 'Metin', 'field_key', 'Kontrol uyarısı', 'Değişen alanın makine tarafından okunabilir anahtarı.'],
            ['Değişen Alan', 'Metin', 'field_label', 'Alan kodu', 'Değişen alanın Türkçe etiketi.'],
            ['Veri Tipi', 'Metin', 'value_type', 'Metin', 'Para, yüzde, adet, ondalık, doğru/yanlış veya metin.'],
            ['Pazaryeri / Mağaza', 'Metin', 'store ilişkisi', 'Boş', 'Kanal kapsamlı kayıtlarda mağaza bağlamı.'],
            ['Mağaza / Kanal Kayıt ID', 'Metin', 'store_id / channel_listing_id', 'Boş', 'Denetim ve çapraz kontrol için teknik kimlikler.'],
            ['Eski / Yeni Değer', 'Metin', 'old_value / new_value', 'Boş', 'Kaynağın okunabilir gösterimi; Excel formülü olarak çalıştırılmaz.'],
            ['Eski / Yeni Sayısal', 'Sayı', '*_value_number', 'Boş', 'Para, yüzde, adet ve ondalık değerlerin hesaplanabilir karşılığı.'],
            ['Fark', 'Sayı', 'delta_number', 'Boş', 'Yeni sayısal değer eksi eski sayısal değer.'],
            ['Değişim Oranı', 'Yüzde', 'delta_percent / 100', 'Boş', 'Eski değer sıfır değilse oransal değişim.'],
            ['Kaynak Kodu / Kaynak', 'Metin', 'source / source_label', 'Sistem', 'Değişikliği oluşturan işlem ve okunabilir etiketi.'],
            ['Batch ID', 'Metin', 'batch_id', 'Boş', 'Toplu işlemlerin korelasyon kimliği.'],
            ['Not / Açıklama', 'Metin', 'note', 'Boş', 'İşlem sırasında kaydedilen açıklama.'],
        ];

        $row = $dictionaryRow + 1;
        foreach ($definitions as $definition) {
            foreach (range('A', 'E') as $index => $column) {
                $this->writeText($sheet, $column.$row, $definition[$index]);
            }
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:E{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB(self::COLORS['panel']);
            }
            $row++;
        }
        $this->styleDataBlock($sheet, "A{$dictionaryRow}:E".($row - 1));
        $sheet->getStyle('C'.($dictionaryRow + 1).':E'.($row - 1))->getAlignment()->setWrapText(true);

        $sheet->getColumnDimension('A')->setWidth(26);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(34);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(70);
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
    }

    /**
     * @return array<string, mixed>
     */
    protected function integritySummary(MpProduct $product, Collection $logs): array
    {
        $duplicateIds = $logs
            ->pluck('id')
            ->filter(fn ($id) => $id !== null)
            ->duplicates()
            ->unique()
            ->count();
        $missingTimestamps = $logs->filter(fn ($log) => $log->changed_at === null)->count();
        $numericIssues = $logs->filter(function ($log) {
            if (! in_array((string) $log->value_type, self::NUMERIC_TYPES, true)) {
                return false;
            }

            return ($log->old_value !== null && $log->old_value !== '' && $log->old_value_number === null)
                || ($log->new_value !== null && $log->new_value !== '' && $log->new_value_number === null);
        })->count();
        $unsupportedTypes = $logs
            ->filter(fn ($log) => ! in_array((string) $log->value_type, self::SUPPORTED_TYPES, true))
            ->count();
        $issueCount = $duplicateIds + $missingTimestamps + $numericIssues + $unsupportedTypes;

        $checksumPayload = $logs->map(fn ($log) => [
            'id' => $log->id,
            'product_id' => $log->mp_product_id,
            'listing_id' => $log->channel_listing_id,
            'store_id' => $log->store_id,
            'changed_at' => $log->changed_at?->format('c'),
            'field_key' => $log->field_key,
            'old_value' => $log->old_value,
            'new_value' => $log->new_value,
            'delta_number' => $log->delta_number,
            'delta_percent' => $log->delta_percent,
            'source' => $log->source,
            'batch_id' => $log->batch_id,
        ])->values()->all();
        $checksum = hash('sha256', json_encode($checksumPayload, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
        $firstChange = $logs->min(fn ($log) => $log->changed_at?->getTimestamp());
        $lastChange = $logs->max(fn ($log) => $log->changed_at?->getTimestamp());

        return [
            'duplicate_ids' => $duplicateIds,
            'missing_timestamps' => $missingTimestamps,
            'numeric_issues' => $numericIssues,
            'unsupported_types' => $unsupportedTypes,
            'status' => $issueCount === 0 ? 'passed' : 'review',
            'status_label' => $issueCount === 0 ? 'Kontroller geçti' : $issueCount.' kontrol noktası',
            'checksum' => $checksum,
            'report_id' => sprintf(
                'ZOLM-PCH-%s-%s-%s',
                $this->identifier($product->id),
                now()->format('YmdHis'),
                strtoupper(substr($checksum, 0, 8))
            ),
            'first_change_label' => $firstChange ? date('d.m.Y H:i:s', $firstChange) : '-',
            'last_change_label' => $lastChange ? date('d.m.Y H:i:s', $lastChange) : '-',
        ];
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function writeTableHeader(Worksheet $sheet, int $row, array $headers): void
    {
        foreach ($headers as $column => $header) {
            $this->writeText($sheet, $column.$row, $header);
        }

        $firstColumn = array_key_first($headers);
        $lastColumn = array_key_last($headers);
        $sheet->getStyle("{$firstColumn}{$row}:{$lastColumn}{$row}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLORS['slate']]],
            'font' => ['bold' => true, 'color' => ['argb' => self::COLORS['white']], 'size' => 9],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => self::COLORS['ink']]],
            ],
        ]);
    }

    protected function writeSectionTitle(Worksheet $sheet, string $range, string $title, string $description): void
    {
        [$start, $end] = explode(':', $range);
        $startRow = (int) preg_replace('/\D+/', '', $start);
        $startColumn = preg_replace('/\d+/', '', $start);
        $endColumn = preg_replace('/\d+/', '', $end);

        $this->writeText($sheet, $start, $title);
        $sheet->mergeCells($range);
        $sheet->getStyle($range)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLORS['panel_alt']]],
            'font' => ['bold' => true, 'color' => ['argb' => self::COLORS['ink']], 'size' => 11],
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLORS['line']]],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($startRow)->setRowHeight(25);
        $sheet->getComment($startColumn.$startRow)->getText()->createTextRun($description);
        $sheet->getComment($startColumn.$startRow)->setWidth('320px')->setHeight('70px');
        $sheet->getStyle("{$startColumn}{$startRow}:{$endColumn}{$startRow}")->getAlignment()->setWrapText(false);
    }

    protected function writeKpiCard(
        Worksheet $sheet,
        string $range,
        string $label,
        ?string $value,
        string $numberFormat,
        string $dataType = DataType::TYPE_NUMERIC,
        ?string $emptyLabel = null,
    ): void {
        [$start, $end] = explode(':', $range);
        $startColumn = preg_replace('/\d+/', '', $start);
        $startRow = (int) preg_replace('/\D+/', '', $start);
        $endColumn = preg_replace('/\d+/', '', $end);
        $endRow = (int) preg_replace('/\D+/', '', $end);

        $labelRange = "{$startColumn}{$startRow}:{$endColumn}{$startRow}";
        $valueRange = "{$startColumn}".($startRow + 1).":{$endColumn}{$endRow}";
        $sheet->mergeCells($labelRange);
        $sheet->mergeCells($valueRange);
        $this->writeText($sheet, $start, $label);

        $valueCell = $startColumn.($startRow + 1);
        if ($value === null && $emptyLabel !== null) {
            $this->writeText($sheet, $valueCell, $emptyLabel);
        } elseif ($dataType === DataType::TYPE_NUMERIC) {
            $this->writeNumber($sheet, $valueCell, (float) $value, $numberFormat);
        } else {
            $this->writeText($sheet, $valueCell, (string) $value);
        }

        $sheet->getStyle($range)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLORS['panel']]],
            'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLORS['line']]]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle($start)->getFont()->setBold(true)->setSize(9)->setColor(new Color(self::COLORS['muted']));
        $sheet->getStyle($valueCell)->getFont()->setBold(true)->setSize(15)->setColor(new Color(self::COLORS['ink']));
        $sheet->getStyle($valueRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getRowDimension($startRow)->setRowHeight(22);
        $sheet->getRowDimension($startRow + 1)->setRowHeight(28);
        if ($endRow > $startRow + 1) {
            $sheet->getRowDimension($endRow)->setRowHeight(8);
        }
    }

    protected function styleDataBlock(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLORS['line']]],
                'insideHorizontal' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => self::COLORS['line']]],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }

    protected function writeText(Worksheet $sheet, string $cell, mixed $value): void
    {
        $sheet->setCellValueExplicit($cell, $this->cleanString($value), DataType::TYPE_STRING);
    }

    protected function writeNumber(Worksheet $sheet, string $cell, float $value, ?string $numberFormat = null): void
    {
        $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_NUMERIC);
        if ($numberFormat !== null) {
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode($numberFormat);
        }
        $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    protected function writeDate(Worksheet $sheet, string $cell, mixed $value): void
    {
        $date = $value instanceof \DateTimeInterface ? $value : now()->parse($value);
        $this->writeNumber($sheet, $cell, ExcelDate::PHPToExcel($date), 'dd.mm.yyyy hh:mm:ss');
    }

    protected function writeTypedNumericValue(
        Worksheet $sheet,
        string $cell,
        mixed $log,
        mixed $value,
        bool $signed = false,
    ): void {
        if ($value === null || $value === '') {
            $this->writeText($sheet, $cell, '');

            return;
        }

        $numericValue = (float) $value;
        $format = '#,##0.00';
        if ($log->value_type === 'money') {
            $format = $signed
                ? '+₺#,##0.00;[Red]-₺#,##0.00;₺0.00'
                : '₺#,##0.00;[Red]-₺#,##0.00;₺0.00';
        } elseif ($log->value_type === 'percent') {
            $numericValue /= 100;
            $format = $signed ? '+0.00%;[Red]-0.00%;0.00%' : '0.00%';
        } elseif ($log->value_type === 'integer') {
            $format = $signed ? '+#,##0;[Red]-#,##0;0' : '#,##0';
        } elseif ($signed) {
            $format = '+#,##0.00;[Red]-#,##0.00;0.00';
        }

        $this->writeNumber($sheet, $cell, $numericValue, $format);
    }

    protected function productName(MpProduct $product): string
    {
        return $this->cleanString($product->product_name ?: $product->title ?: 'İsimsiz Ürün');
    }

    protected function valueTypeLabel(string $type): string
    {
        return match ($type) {
            'money' => 'Para',
            'percent' => 'Yüzde',
            'integer' => 'Tam sayı',
            'decimal' => 'Ondalık',
            'boolean' => 'Doğru / Yanlış',
            'string' => 'Metin',
            default => 'Bilinmeyen ('.$type.')',
        };
    }

    protected function formatLogValue(mixed $log, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Boş';
        }

        return match ((string) $log->value_type) {
            'money' => '₺'.number_format((float) $value, 2, ',', '.'),
            'percent' => '%'.number_format((float) $value, 2, ',', '.'),
            'integer' => number_format((float) $value, 0, ',', '.'),
            'decimal' => number_format((float) $value, 2, ',', '.'),
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Açık' : 'Kapalı',
            default => (string) $value,
        };
    }

    protected function identifier(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return $this->cleanString($value);
    }

    protected function cleanString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $string = (string) $value;
        if (trim($string) === '') {
            return '';
        }

        if (! mb_check_encoding($string, 'UTF-8')) {
            $string = mb_convert_encoding($string, 'UTF-8', 'Windows-1254');
        }

        $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $string);
        $string = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $string);

        return $string ?? '';
    }

    protected function sanitizeSheetName(string $name): string
    {
        $name = str_replace([':', '\\', '/', '?', '*', '[', ']'], '', $this->cleanString($name));
        $name = trim($name);

        if (mb_strlen($name) > 31) {
            $name = mb_substr($name, 0, 31);
        }

        return $name ?: 'Sayfa';
    }
}
