<?php

namespace App\Services;

use App\Models\CargoReport;
use App\Models\CargoReportItem;
use App\Models\MpOperationalOrder;
use App\Models\MpProduct;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Kargo Karşılaştırma Motoru
 * 
 * Kargo firması raporları ile sipariş detaylarını karşılaştırarak
 * desi/tutar uyumsuzluklarını tespit eden ana servis.
 * 
 * 5 Adımlı Kontrol Algoritması:
 * 1. Sipariş/İade Ayrımı: CikisIl kontrol
 * 2. Müşteri Eşleştirme: Kargo.Alici == Siparis.Sevk_Müşteri
 * 3. Stok Kodu Doğrulama: Product tablosu ile eşleştirme
 * 4. Beklenen Değer Hesaplama: Adet × (Parça, Desi, Tutar)
 * 5. Gerçek vs Beklenen Karşılaştırma
 */
class CargoComparisonEngine
{
    protected ExcelService $excelService;
    protected ?bool $supportsReferenceMissingErrorType = null;

    /**
     * Karşılaştırma sonuç istatistikleri
     */
    protected array $stats = [
        'total_cargo_rows' => 0,
        'total_order_rows' => 0,
        'loaded_marketplace_orders' => 0,
        'matched' => 0,
        'unmatched' => 0,
        'errors' => 0,
        'iade_count' => 0,
        'iade_tutar' => 0,
        'parca_count' => 0,
        'parca_tutar' => 0,
        'reference_issues' => 0,
    ];

    public function __construct(?ExcelService $excelService = null)
    {
        $this->excelService = $excelService ?? app(ExcelService::class);
    }

    /**
     * Ana karşılaştırma metodu
     * 
     * @param UploadedFile $cargoFile Kargo firması Excel dosyası
     * @param UploadedFile $orderFile Sipariş detayları Excel dosyası
     * @param string|null $reportName Rapor adı
     * @param string|null $cargoCompany Kargo firması
     * @return array{success: bool, message: string, report: ?CargoReport, stats: array}
     */
    public function compare(
        UploadedFile $cargoFile,
        ?UploadedFile $orderFile = null,
        ?string $reportName = null,
        ?string $cargoCompany = null
    ): array {
        try {
            $this->resetStats();

            Log::info('CargoComparisonEngine: Karşılaştırma başladı', [
                'cargo_file' => $cargoFile->getClientOriginalName(),
                'order_file' => $orderFile?->getClientOriginalName(),
            ]);

            $cargoData = $this->parseCargoFile($cargoFile);
            $this->stats['total_cargo_rows'] = $cargoData->count();

            Log::info('CargoComparisonEngine: Dosyalar parse edildi', [
                'cargo_rows' => $cargoData->count(),
                'order_rows' => $orderFile ? 'legacy_excel' : 'marketplace_database',
            ]);

            if ($orderFile) {
                return $this->compareUsingLegacyExcel(
                    $cargoData,
                    $orderFile,
                    $reportName,
                    $cargoCompany,
                    $cargoFile->getClientOriginalName()
                );
            }

            return $this->compareUsingMarketplaceData(
                $cargoData,
                $reportName,
                $cargoCompany,
                $cargoFile->getClientOriginalName()
            );
        } catch (\Exception $e) {
            Log::error('CargoComparisonEngine: Hata', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Karşılaştırma hatası: ' . $e->getMessage(),
                'report' => null,
                'stats' => $this->stats,
            ];
        }
    }

    protected function compareUsingLegacyExcel(
        Collection $cargoData,
        UploadedFile $orderFile,
        ?string $reportName,
        ?string $cargoCompany,
        string $cargoFileName
    ): array {
        try {
            $orderData = $this->parseOrderFile($orderFile);
            $this->stats['total_order_rows'] = $orderData->count();

            $products = Product::active()->get()->keyBy('stok_kodu');

            $originCity = config('cargo.origin_city', 'Denizli');

            $outgoingCargo = $cargoData->filter(function ($item) use ($originCity) {
                $cikisIl = $this->normalizeString($item['cikis_il'] ?? '');
                return $cikisIl === $this->normalizeString($originCity);
            });

            $returnCargo = $cargoData->filter(function ($item) use ($originCity) {
                $cikisIl = $this->normalizeString($item['cikis_il'] ?? '');
                return !empty($cikisIl) && $cikisIl !== $this->normalizeString($originCity);
            });

            $this->stats['iade_count'] = $returnCargo->count();
            $this->stats['iade_tutar'] = $returnCargo->sum(function ($item) {
                return (float) ($item['tutar'] ?? 0);
            });

            $comparisonResults = $this->performComparison($outgoingCargo, $orderData, $products);

            $parcaGonderileri = $comparisonResults->filter(function ($item) {
                return ($item['is_parca_gonderi'] ?? false) === true;
            });
            $this->stats['parca_count'] = $parcaGonderileri->count();
            $this->stats['parca_tutar'] = $parcaGonderileri->sum(function ($item) {
                return (float) ($item['gercek_tutar'] ?? 0);
            });

            $returnResults = $this->processReturns($returnCargo);
            $allResults = $comparisonResults->concat($returnResults);

            $report = $this->createReport(
                $allResults,
                $reportName ?? 'Kargo Raporu - ' . now()->format('d.m.Y H:i'),
                $cargoCompany ?? config('cargo.companies.' . config('cargo.default_company') . '.name'),
                $cargoFileName,
                $orderFile->getClientOriginalName()
            );

            Log::info('CargoComparisonEngine: Karşılaştırma tamamlandı', [
                'report_id' => $report->id,
                'matched' => $this->stats['matched'],
                'errors' => $this->stats['errors'],
                'iade' => $this->stats['iade_count'],
                'parca' => $this->stats['parca_count'],
            ]);

            return [
                'success' => true,
                'message' => "Karşılaştırma tamamlandı. {$this->stats['matched']} sipariş eşleşti, {$this->stats['errors']} hata, {$this->stats['iade_count']} iade/değişim, {$this->stats['parca_count']} parça gönderisi.",
                'report' => $report,
                'stats' => $this->stats,
            ];
        } catch (\Exception $e) {
            Log::error('CargoComparisonEngine: Legacy karşılaştırma hatası', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Karşılaştırma hatası: ' . $e->getMessage(),
                'report' => null,
                'stats' => $this->stats,
            ];
        }
    }

    protected function compareUsingMarketplaceData(
        Collection $cargoData,
        ?string $reportName,
        ?string $cargoCompany,
        string $cargoFileName
    ): array {
        $dominantSender = $this->detectDominantSender($cargoData);
        $outgoingCargo = $cargoData->filter(fn(array $item) => $this->isOutgoingCargoRow($item, $dominantSender));
        $returnCargo = $cargoData->reject(fn(array $item) => $this->isOutgoingCargoRow($item, $dominantSender));

        $outgoingBuckets = $this->groupCargoCompareBuckets($outgoingCargo);
        $returnBuckets = $this->groupCargoCompareBuckets($returnCargo);

        $this->stats['total_order_rows'] = $outgoingBuckets->count();
        $this->stats['iade_count'] = $returnBuckets->count();
        $this->stats['iade_tutar'] = $returnBuckets->sum('tutar');

        $orders = $this->loadMarketplaceOrders($outgoingBuckets);
        $products = $this->loadMarketplaceProducts();
        $this->stats['loaded_marketplace_orders'] = $orders->count();

        Log::info('CargoComparisonEngine: Pazaryeri eşleştirme havuzu hazırlandı', [
            'cargo_bucket_count' => $outgoingBuckets->count(),
            'loaded_orders' => $orders->count(),
            'sample_cargo_refs' => $outgoingBuckets->pluck('web_siparis_kodu')->filter()->unique()->take(5)->values()->all(),
            'sample_order_numbers' => $orders->pluck('order_number')->filter()->take(5)->values()->all(),
            'sample_package_numbers' => $orders->pluck('package_number')->filter()->take(5)->values()->all(),
            'sample_tracking_numbers' => $orders->pluck('tracking_number')->filter()->take(5)->values()->all(),
        ]);

        $comparisonResults = $this->performMarketplaceComparison($outgoingBuckets, $orders, $products);
        $parcaGonderileri = $comparisonResults->where('is_parca_gonderi', true);

        $this->stats['parca_count'] = $parcaGonderileri->count();
        $this->stats['parca_tutar'] = $parcaGonderileri->sum('gercek_tutar');
        $this->stats['reference_issues'] = $comparisonResults->where('error_type', 'referans_eksik')->count();

        $returnResults = $this->processReturns($returnBuckets, $dominantSender);
        $allResults = $comparisonResults->concat($returnResults);

        $sourceLabel = 'Pazaryeri Siparişlerim + Pazaryeri Ürünlerim';

        $report = $this->createReport(
            $allResults,
            $reportName ?? 'Kargo Raporu - ' . now()->format('d.m.Y H:i'),
            $cargoCompany ?? config('cargo.companies.' . config('cargo.default_company') . '.name'),
            $cargoFileName,
            $sourceLabel
        );

        Log::info('CargoComparisonEngine: Pazaryeri veri kaynağı ile karşılaştırma tamamlandı', [
            'report_id' => $report->id,
            'loaded_orders' => $orders->count(),
            'matched' => $this->stats['matched'],
            'unmatched' => $this->stats['unmatched'],
            'errors' => $this->stats['errors'],
            'reference_issues' => $this->stats['reference_issues'],
            'iade' => $this->stats['iade_count'],
            'parca' => $this->stats['parca_count'],
        ]);

        $message = "Karşılaştırma tamamlandı. {$this->stats['matched']} sipariş eşleşti, {$this->stats['unmatched']} eşleşmedi, {$this->stats['errors']} hata, {$this->stats['reference_issues']} referans uyarısı, {$this->stats['iade_count']} iade/değişim, {$this->stats['parca_count']} parça gönderisi.";

        if ($this->stats['matched'] === 0) {
            if ($orders->isEmpty()) {
                $message .= ' Pazaryeri Siparişlerim içinde bu tarih aralığına ait operasyonel sipariş bulunamadı.';
            } else {
                $message .= ' Sürat dosyasındaki WebSiparisKodu, pazaryeri Sipariş No ile aynı formatta görünmüyor; paket no, takip no ve müşteri eşleşmeleri ile fallback denendi.';
            }
        }

        return [
            'success' => true,
            'message' => $message,
            'report' => $report,
            'stats' => $this->stats,
        ];
    }

    /**
     * İade/Değişim gönderilerini işle
     */
    protected function processReturns(Collection $returnCargo, ?string $dominantSender = null): Collection
    {
        $buckets = $returnCargo->isNotEmpty() && array_key_exists('tracking_numbers', $returnCargo->first())
            ? $returnCargo
            : $this->groupCargoCompareBuckets($returnCargo);

        return $buckets->map(function ($item) use ($dominantSender) {
            $dominantSender = $this->normalizeCustomerName((string) $dominantSender);
            $gonderen = $item['gonderen'] ?? '';
            $normalizedGonderen = $this->normalizeCustomerName($gonderen);
            $musteriAdi = !empty($gonderen) && $normalizedGonderen !== $dominantSender
                ? $gonderen
                : ($item['alici'] ?? $gonderen);

            return [
                'tarih' => $this->parseDate($item['teslim_tarihi'] ?? $item['fatura_tarihi'] ?? null),
                'musteri_adi' => $musteriAdi,
                'takip_kodu' => $this->formatTrackingLabel($item['tracking_numbers'] ?? [$item['takip_no'] ?? null]),
                'stok_kodu' => null,
                'urun_adi' => 'İade/Değişim Gönderisi',
                'adet' => (int) ($item['adet'] ?? 1),
                'beklenen_parca' => 0,
                'beklenen_desi' => 0,
                'beklenen_tutar' => 0,
                'gercek_parca' => (int) ($item['adet'] ?? 1),
                'gercek_desi' => (float) ($item['desi'] ?? 0),
                'gercek_tutar' => (float) ($item['tutar'] ?? 0),
                'parca_fark' => 0,
                'desi_fark' => 0,
                'tutar_fark' => 0,
                'error_type' => 'none',
                'has_error' => false,
                'is_matched' => false,
                'match_type' => 'iade',
                'pazaryeri' => null,
                'magaza' => null,
                'siparis_no' => $item['web_siparis_kodu'] ?? null,
                'siparis_detay' => null,
                'cikis_il' => $item['alici_il'] ?? $item['cikis_il'] ?? null,
                'is_iade' => true,
                'is_parca_gonderi' => false,
            ];
        });
    }

    /**
     * Kargo Excel dosyasını parse et
     */
    protected function parseCargoFile(UploadedFile $file): Collection
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, true, true);

        $headers = array_shift($data);
        $columnConfig = config('cargo.cargo_columns') ?? $this->getDefaultCargoColumns();
        $columnMap = $this->mapColumns($headers, $columnConfig);

        $result = collect();
        foreach ($data as $rowNumber => $row) {
            $item = $this->extractRowData($row, $columnMap, 'cargo', (int) $rowNumber);
            if (!empty($item['alici']) || !empty($item['gonderen']) || !empty($item['web_siparis_kodu']) || !empty($item['takip_no'])) {
                $result->push($item);
            }
        }

        return $result;
    }

    /**
     * Sipariş Excel dosyasını parse et
     */
    protected function parseOrderFile(UploadedFile $file): Collection
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, true, true);

        $headers = array_shift($data);
        $columnConfig = config('cargo.order_columns') ?? $this->getDefaultOrderColumns();
        $columnMap = $this->mapColumns($headers, $columnConfig);

        $result = collect();
        foreach ($data as $rowNumber => $row) {
            $item = $this->extractRowData($row, $columnMap, 'order', (int) $rowNumber);
            if (!empty($item['musteri'])) {
                $result->push($item);
            }
        }

        return $result;
    }

    /**
     * Kolon eşleştirmesi yap
     */
    protected function mapColumns(array $headers, array $columnConfig): array
    {
        $map = [];

        foreach ($columnConfig as $key => $possibleNames) {
            foreach ($headers as $colLetter => $header) {
                $normalizedHeader = $this->normalizeString($header);
                foreach ($possibleNames as $possible) {
                    if ($normalizedHeader === $this->normalizeString($possible)) {
                        $map[$key] = $colLetter;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Satır verilerini çıkar
     */
    protected function extractRowData(array $row, array $columnMap, string $type, int $rowNumber = 0): array
    {
        if ($type === 'cargo') {
            return [
                '_row_number' => $rowNumber,
                'web_siparis_kodu' => $this->cleanValue($row[$columnMap['web_siparis_kodu'] ?? ''] ?? ''),
                'takip_no' => $this->cleanValue($row[$columnMap['takip_no'] ?? ''] ?? ''),
                'alici' => $this->cleanValue($row[$columnMap['alici'] ?? ''] ?? ''),
                'gonderen' => $this->cleanValue($row[$columnMap['gonderen'] ?? ''] ?? ''),
                'borclu_unvan' => $this->cleanValue($row[$columnMap['borclu_unvan'] ?? ''] ?? ''),
                'adet' => (int) ($row[$columnMap['adet'] ?? ''] ?? 1),
                'desi' => $this->parseNumber($row[$columnMap['desi'] ?? ''] ?? null),
                'tutar' => $this->parsePrice($row[$columnMap['tutar'] ?? ''] ?? null),
                'cikis_il' => $this->cleanValue($row[$columnMap['cikis_il'] ?? ''] ?? ''),
                'teslim_tarihi' => $row[$columnMap['teslim_tarihi'] ?? ''] ?? null,
                'fatura_tarihi' => $row[$columnMap['fatura_tarihi'] ?? ''] ?? null,
                'tesellum_fatura_no' => $this->cleanValue($row[$columnMap['tesellum_fatura_no'] ?? ''] ?? ''),
                'barkod' => $this->cleanValue($row[$columnMap['barkod'] ?? ''] ?? ''),
                'alici_il' => $this->cleanValue($row[$columnMap['alici_il'] ?? ''] ?? ''),
                'alici_ilce' => $this->cleanValue($row[$columnMap['alici_ilce'] ?? ''] ?? ''),
                'durum' => $this->cleanValue($row[$columnMap['durum'] ?? ''] ?? ''),
            ];
        }

        // Order type
        return [
            '_row_number' => $rowNumber,
            'musteri' => $this->cleanValue($row[$columnMap['musteri'] ?? ''] ?? ''),
            'stok_kodu' => $this->cleanValue($row[$columnMap['stok_kodu'] ?? ''] ?? ''),
            'urun_adi' => $this->cleanValue($row[$columnMap['urun_adi'] ?? ''] ?? ''),
            'adet' => (int) ($row[$columnMap['adet'] ?? ''] ?? 1),
            'pazaryeri' => $this->cleanValue($row[$columnMap['pazaryeri'] ?? ''] ?? ''),
            'magaza' => $this->cleanValue($row[$columnMap['magaza'] ?? ''] ?? ''),
            'siparis_no' => $this->cleanValue($row[$columnMap['siparis_no'] ?? ''] ?? ''),
            'kargo_takip' => $this->cleanValue($row[$columnMap['kargo_takip'] ?? ''] ?? ''),
            'satir_fiyat' => $this->parsePrice($row[$columnMap['satir_fiyat'] ?? ''] ?? null),
        ];
    }

    /**
     * Fiyat değerini parse et (Türkçe formatı destekle: 1.049,90)
     */
    protected function parsePrice($value): float
    {
        return $this->parseNumber($value);
    }

    /**
     * Sayısal değerleri hem TR hem noktalı ondalık formatta güvenli parse et.
     */
    protected function parseNumber($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);
        $value = str_replace(["\u{00A0}", ' '], '', $value);
        $value = preg_replace('/[^\d,\.\-]/u', '', $value) ?? '';

        if ($value === '' || $value === '-' || $value === '.' || $value === ',') {
            return 0.0;
        }

        $lastDot = strrpos($value, '.');
        $lastComma = strrpos($value, ',');

        if ($lastDot !== false && $lastComma !== false) {
            $decimalSeparator = $lastDot > $lastComma ? '.' : ',';
            $thousandSeparator = $decimalSeparator === '.' ? ',' : '.';

            $value = str_replace($thousandSeparator, '', $value);

            if ($decimalSeparator === ',') {
                $value = str_replace(',', '.', $value);
            }

            return (float) $value;
        }

        if ($lastComma !== false) {
            $fractionLength = strlen($value) - $lastComma - 1;

            if ($fractionLength > 0 && $fractionLength <= 2) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }

            return (float) $value;
        }

        if ($lastDot !== false) {
            $fractionLength = strlen($value) - $lastDot - 1;

            if ($fractionLength === 0 || $fractionLength > 2) {
                $value = str_replace('.', '', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        }

        return (float) $value;
    }

    /**
     * Sipariş/İade ayrımı
     * Çıkış ili = Denizli ise sipariş, değilse iade
     */
    public function filterOutgoingOrders(Collection $cargoData): Collection
    {
        $originCity = config('cargo.origin_city', 'Denizli');

        return $cargoData->filter(function ($item) use ($originCity) {
            $cikisIl = $this->normalizeString($item['cikis_il'] ?? '');
            $normalizedOrigin = $this->normalizeString($originCity);

            return $cikisIl === $normalizedOrigin;
        });
    }

    protected function loadMarketplaceProducts(): Collection
    {
        $userId = auth()->id() ?? 1;

        return MpProduct::query()
            ->where('user_id', $userId)
            ->get();
    }

    protected function loadMarketplaceOrders(Collection $cargoBuckets): Collection
    {
        $webOrderRefs = $cargoBuckets
            ->pluck('web_siparis_kodu')
            ->filter()
            ->map(fn($value) => $this->normalizeOrderNumber($value))
            ->unique()
            ->values();

        $trackingNumbers = $cargoBuckets
            ->flatMap(fn(array $bucket) => $bucket['tracking_numbers'] ?? [])
            ->filter()
            ->map(fn($value) => $this->normalizeTrackingNumber((string) $value))
            ->unique()
            ->values();

        $allReferenceCodes = $cargoBuckets
            ->flatMap(function (array $bucket) {
                return array_filter(array_merge(
                    [$bucket['web_siparis_kodu'] ?? null],
                    $bucket['tracking_numbers'] ?? []
                ));
            })
            ->map(fn($value) => $this->normalizeOrderNumber((string) $value))
            ->filter()
            ->unique()
            ->values();

        $dateCandidates = $cargoBuckets
            ->flatMap(function (array $bucket) {
                return array_filter([
                    $bucket['fatura_tarihi'] ?? null,
                    $bucket['teslim_tarihi'] ?? null,
                ]);
            })
            ->filter()
            ->map(fn($date) => Carbon::parse($date));

        $query = MpOperationalOrder::query()->with('items');
        $dateRange = $dateCandidates->isNotEmpty()
            ? [
                $dateCandidates->min()->copy()->subDays(14)->startOfDay(),
                $dateCandidates->max()->copy()->addDays(14)->endOfDay(),
            ]
            : null;

        $query->where(function ($subQuery) use ($webOrderRefs, $trackingNumbers, $allReferenceCodes, $dateRange) {
            $hasConstraint = false;

            if ($webOrderRefs->isNotEmpty()) {
                $subQuery->orWhereIn('order_number', $webOrderRefs->all());
                $hasConstraint = true;
            }

            if ($trackingNumbers->isNotEmpty()) {
                $subQuery->orWhereIn('tracking_number', $trackingNumbers->all());
                $subQuery->orWhereIn('second_tracking_number', $trackingNumbers->all());
                $hasConstraint = true;
            }

            if ($allReferenceCodes->isNotEmpty()) {
                $subQuery->orWhereIn('package_number', $allReferenceCodes->all());
                $subQuery->orWhereIn('cargo_code', $allReferenceCodes->all());
                $hasConstraint = true;
            }

            if ($dateRange) {
                $subQuery->orWhereBetween('order_date', $dateRange);
                $hasConstraint = true;
            }

            if (!$hasConstraint) {
                $subQuery->whereNotNull('id');
            }
        });

        return $query->get();
    }

    protected function performMarketplaceComparison(
        Collection $cargoBuckets,
        Collection $orders,
        Collection $products
    ): Collection {
        $results = collect();

        $ordersByNumber = $orders->keyBy(fn(MpOperationalOrder $order) => $this->normalizeOrderNumber($order->order_number));
        $ordersByPackageNumber = $this->buildOrderIndex($orders, function (MpOperationalOrder $order) {
            return [$this->normalizeOrderNumber($order->package_number)];
        });
        $ordersByTracking = $this->buildOrderIndex($orders, function (MpOperationalOrder $order) {
            return [
                $this->normalizeTrackingNumber($order->tracking_number),
                $this->normalizeTrackingNumber($order->second_tracking_number),
            ];
        });
        $ordersByCargoCode = $this->buildOrderIndex($orders, function (MpOperationalOrder $order) {
            return [$this->normalizeOrderNumber($order->cargo_code)];
        });
        $ordersByCustomer = $orders->groupBy(fn(MpOperationalOrder $order) => $this->normalizeCustomerName((string) $order->customer_name));

        $usedOrderNumbers = [];

        foreach ($cargoBuckets as $bucket) {
            [$matchedOrder, $matchType] = $this->findMarketplaceOrderForBucket(
                $bucket,
                $ordersByNumber,
                $ordersByPackageNumber,
                $ordersByTracking,
                $ordersByCargoCode,
                $ordersByCustomer,
                $usedOrderNumbers
            );

            if (!$matchedOrder) {
                $results->push($this->createUnmatchedResult([
                    'takip_no' => $this->formatTrackingLabel($bucket['tracking_numbers'] ?? []),
                    'alici' => $bucket['alici'] ?? '',
                    'adet' => $bucket['adet'] ?? 1,
                    'desi' => $bucket['desi'] ?? 0,
                    'tutar' => $bucket['tutar'] ?? 0,
                    'teslim_tarihi' => $bucket['teslim_tarihi'] ?? null,
                    'cikis_il' => $bucket['alici_il'] ?? '',
                ] + $bucket));
                $this->stats['unmatched']++;
                continue;
            }

            $usedOrderNumbers[$matchedOrder->order_number] = true;

            $comparisonResult = $this->compareWithMarketplaceOrder($bucket, $matchedOrder, $products);
            $comparisonResult['match_type'] = $matchType;
            $results->push($comparisonResult);

            $this->stats['matched']++;

            if ($comparisonResult['has_error']) {
                $this->stats['errors']++;
            }
        }

        return $results;
    }

    protected function findMarketplaceOrderForBucket(
        array $bucket,
        Collection $ordersByNumber,
        Collection $ordersByPackageNumber,
        Collection $ordersByTracking,
        Collection $ordersByCargoCode,
        Collection $ordersByCustomer,
        array $usedOrderNumbers = []
    ): array {
        $orderNumber = $this->normalizeOrderNumber($bucket['web_siparis_kodu'] ?? '');
        if (!empty($orderNumber) && $ordersByNumber->has($orderNumber)) {
            return [$ordersByNumber->get($orderNumber), 'order_number'];
        }

        if (!empty($orderNumber) && $ordersByPackageNumber->has($orderNumber)) {
            $bestMatch = $this->chooseBestOperationalOrderMatch(
                $ordersByPackageNumber->get($orderNumber),
                $bucket,
                $usedOrderNumbers
            );

            if ($bestMatch) {
                return [$bestMatch, 'package_number'];
            }
        }

        if (!empty($orderNumber) && $ordersByCargoCode->has($orderNumber)) {
            $bestMatch = $this->chooseBestOperationalOrderMatch(
                $ordersByCargoCode->get($orderNumber),
                $bucket,
                $usedOrderNumbers
            );

            if ($bestMatch) {
                return [$bestMatch, 'cargo_code'];
            }
        }

        foreach (($bucket['tracking_numbers'] ?? []) as $trackingNumber) {
            $normalizedTracking = $this->normalizeTrackingNumber($trackingNumber);
            if (empty($normalizedTracking) || !$ordersByTracking->has($normalizedTracking)) {
                $normalizedReference = $this->normalizeOrderNumber($trackingNumber);
                if (empty($normalizedReference) || !$ordersByCargoCode->has($normalizedReference)) {
                    continue;
                }

                $bestMatch = $this->chooseBestOperationalOrderMatch(
                    $ordersByCargoCode->get($normalizedReference),
                    $bucket,
                    $usedOrderNumbers
                );

                if ($bestMatch) {
                    return [$bestMatch, 'cargo_code'];
                }

                continue;
            }

            $bestMatch = $this->chooseBestOperationalOrderMatch(
                $ordersByTracking->get($normalizedTracking),
                $bucket,
                $usedOrderNumbers
            );

            if ($bestMatch) {
                return [$bestMatch, 'tracking'];
            }
        }

        $customerKey = $this->normalizeCustomerName((string) ($bucket['alici'] ?? ''));
        if (!empty($customerKey) && $ordersByCustomer->has($customerKey)) {
            $bestMatch = $this->chooseBestOperationalOrderMatch(
                $ordersByCustomer->get($customerKey),
                $bucket,
                $usedOrderNumbers
            );

            if ($bestMatch) {
                return [$bestMatch, 'customer'];
            }
        }

        $threshold = config('cargo.matching.fuzzy_threshold', 85);
        foreach ($ordersByCustomer as $customerName => $candidates) {
            if ($this->similarityScore($customerKey, $customerName) < $threshold) {
                continue;
            }

            $bestMatch = $this->chooseBestOperationalOrderMatch($candidates, $bucket, $usedOrderNumbers);
            if ($bestMatch) {
                return [$bestMatch, 'customer_fuzzy'];
            }
        }

        return [null, 'none'];
    }

    protected function buildOrderIndex(Collection $orders, callable $resolver): Collection
    {
        return $orders
            ->reduce(function (Collection $index, MpOperationalOrder $order) use ($resolver) {
                $keys = array_values(array_unique(array_filter($resolver($order))));

                foreach ($keys as $key) {
                    $group = $index->get($key, collect());
                    $group->push($order);
                    $index->put($key, $group->unique('id')->values());
                }

                return $index;
            }, collect());
    }

    protected function chooseBestOperationalOrderMatch(
        Collection $candidates,
        array $bucket,
        array $usedOrderNumbers = []
    ): ?MpOperationalOrder {
        return $candidates
            ->reject(fn(MpOperationalOrder $order) => isset($usedOrderNumbers[$order->order_number]))
            ->sortByDesc(fn(MpOperationalOrder $order) => $this->scoreOperationalOrderMatch($order, $bucket))
            ->first();
    }

    protected function scoreOperationalOrderMatch(MpOperationalOrder $order, array $bucket): int
    {
        $score = 0;
        $bucketCustomer = $this->normalizeCustomerName((string) ($bucket['alici'] ?? ''));
        $orderCustomer = $this->normalizeCustomerName((string) $order->customer_name);

        if ($bucketCustomer !== '' && $bucketCustomer === $orderCustomer) {
            $score += 40;
        } elseif ($bucketCustomer !== '' && $orderCustomer !== '') {
            $score += (int) round($this->similarityScore($bucketCustomer, $orderCustomer) / 4);
        }

        $bucketDate = $bucket['fatura_tarihi'] ?? $bucket['teslim_tarihi'] ?? null;
        if ($bucketDate && $order->order_date) {
            $dayDiff = abs(Carbon::parse($bucketDate)->diffInDays($order->order_date, false));
            $score += max(0, 30 - ($dayDiff * 2));
        }

        $bucketCity = $this->normalizeCustomerName((string) ($bucket['alici_il'] ?? ''));
        $orderCity = $this->normalizeCustomerName((string) $order->customer_city);
        if ($bucketCity !== '' && $bucketCity === $orderCity) {
            $score += 10;
        }

        return $score;
    }

    protected function compareWithMarketplaceOrder(
        array $bucket,
        MpOperationalOrder $order,
        Collection $products
    ): array {
        $expectedValues = [
            'parca' => 0,
            'desi' => 0,
            'tutar' => 0,
        ];

        $stokKodlari = [];
        $urunAdlari = [];
        $siparisDetay = [];
        $referenceIssues = [];
        $totalAdet = 0;

        foreach ($order->items as $item) {
            $qty = max(1, (int) ($item->quantity ?? 1));
            $totalAdet += $qty;

            $stokKodu = trim((string) ($item->stock_code ?? ''));
            $urunAdi = trim((string) ($item->product_name ?? ''));

            if ($stokKodu !== '') {
                $stokKodlari[] = $stokKodu;
            }

            if ($urunAdi !== '') {
                $urunAdlari[] = $urunAdi;
            }

            $detail = [
                'stok_kodu' => $stokKodu,
                'urun_adi' => $urunAdi,
                'adet' => $qty,
            ];

            $product = $this->resolveMarketplaceProduct($item->stock_code, $item->barcode, $products);

            if (!$product) {
                $detail['not_found'] = true;
                $detail['reference_note'] = 'Pazaryeri ürün kartı bulunamadı.';
                $referenceIssues[] = $stokKodu ?: ($item->barcode ?? 'ÜRÜN');
                $siparisDetay[] = $detail;
                continue;
            }

            $pieces = max(1, (int) ($product->pieces ?? 1));
            $desi = (float) ($product->desi ?? 0);
            $tutar = (float) ($product->cargo_cost ?? 0);

            $expectedValues['parca'] += $pieces * $qty;
            $expectedValues['desi'] += $desi * $qty;
            $expectedValues['tutar'] += $tutar * $qty;

            $detail['parca'] = $pieces * $qty;
            $detail['desi'] = round($desi * $qty, 2);
            $detail['tutar'] = round($tutar * $qty, 2);

            if ($desi <= 0 || $tutar <= 0) {
                $detail['reference_note'] = 'Desi veya kargo fiyatı eksik.';
                $referenceIssues[] = $stokKodu ?: ($item->barcode ?? 'ÜRÜN');
            }

            $siparisDetay[] = $detail;
        }

        $actualValues = [
            'parca' => (int) ($bucket['adet'] ?? 1),
            'desi' => (float) ($bucket['desi'] ?? 0),
            'tutar' => (float) ($bucket['tutar'] ?? 0),
        ];

        $diff = [
            'parca' => $actualValues['parca'] - $expectedValues['parca'],
            'desi' => $actualValues['desi'] - $expectedValues['desi'],
            'tutar' => $actualValues['tutar'] - $expectedValues['tutar'],
        ];

        $preliminaryErrorType = $this->determineErrorType($diff);
        $hasReferenceIssue = !empty($referenceIssues);
        $errorType = $hasReferenceIssue ? 'referans_eksik' : $preliminaryErrorType;

        return [
            'tarih' => $this->parseDate($bucket['teslim_tarihi'] ?? $bucket['fatura_tarihi'] ?? null),
            'musteri_adi' => $bucket['alici'] ?? $order->customer_name,
            'takip_kodu' => $this->formatTrackingLabel($bucket['tracking_numbers'] ?? []),
            'stok_kodu' => mb_strimwidth(implode(', ', array_unique(array_filter($stokKodlari))), 0, 30, '...'),
            'urun_adi' => mb_strimwidth(implode(', ', array_unique(array_filter($urunAdlari))), 0, 250, '...'),
            'adet' => max(1, $totalAdet),
            'beklenen_parca' => $expectedValues['parca'],
            'beklenen_desi' => $expectedValues['desi'],
            'beklenen_tutar' => $expectedValues['tutar'],
            'gercek_parca' => $actualValues['parca'],
            'gercek_desi' => $actualValues['desi'],
            'gercek_tutar' => $actualValues['tutar'],
            'parca_fark' => $diff['parca'],
            'desi_fark' => $diff['desi'],
            'tutar_fark' => $diff['tutar'],
            'error_type' => $errorType,
            'has_error' => $errorType !== 'none',
            'is_matched' => true,
            'pazaryeri' => null,
            'magaza' => null,
            'siparis_no' => $order->order_number,
            'siparis_detay' => $siparisDetay,
            'cikis_il' => $bucket['alici_il'] ?? null,
            'is_iade' => false,
            'is_parca_gonderi' => $this->isMarketplacePartShipment($order),
        ];
    }

    protected function resolveMarketplaceProduct(?string $stockCode, ?string $barcode, Collection $products): ?MpProduct
    {
        $normalizedStockCode = $this->normalizeOrderNumber((string) $stockCode);
        if ($normalizedStockCode !== '') {
            $match = $products->first(function (MpProduct $product) use ($normalizedStockCode) {
                return $this->normalizeOrderNumber((string) $product->stock_code) === $normalizedStockCode;
            });

            if ($match) {
                return $match;
            }
        }

        $normalizedBarcode = $this->normalizeOrderNumber((string) $barcode);
        if ($normalizedBarcode === '') {
            return null;
        }

        return $products->first(function (MpProduct $product) use ($normalizedBarcode) {
            return $this->normalizeOrderNumber((string) $product->barcode) === $normalizedBarcode;
        });
    }

    protected function isMarketplacePartShipment(MpOperationalOrder $order): bool
    {
        if ($order->items->isEmpty()) {
            return false;
        }

        return $order->items->every(function ($item) {
            $billable = (float) ($item->billable_amount ?? 0);
            $sale = (float) ($item->sale_price ?? 0);
            $unit = (float) ($item->unit_price ?? 0);

            return $billable <= 0 && $sale <= 0 && $unit <= 0;
        });
    }

    protected function groupCargoCompareBuckets(Collection $cargoRows): Collection
    {
        return $this->groupCargoShipments($cargoRows)
            ->groupBy(fn(array $shipment) => $this->buildCompareBucketKey($shipment))
            ->map(function (Collection $shipments) {
                $first = $shipments->first();

                return [
                    'web_siparis_kodu' => $first['web_siparis_kodu'] ?? null,
                    'tracking_numbers' => $shipments->pluck('tracking_no')->filter()->unique()->values()->all(),
                    'alici' => $first['alici'] ?? null,
                    'gonderen' => $first['gonderen'] ?? null,
                    'adet' => (int) $shipments->sum('adet'),
                    'desi' => (float) $shipments->sum('desi'),
                    'tutar' => (float) $shipments->sum('tutar'),
                    'teslim_tarihi' => $shipments->pluck('teslim_tarihi')->filter()->sort()->first(),
                    'fatura_tarihi' => $shipments->pluck('fatura_tarihi')->filter()->sort()->first(),
                    'alici_il' => $first['alici_il'] ?? null,
                    'alici_ilce' => $first['alici_ilce'] ?? null,
                    'shipment_count' => $shipments->count(),
                    'tesellum_fatura_no' => $shipments->pluck('tesellum_fatura_no')->filter()->unique()->implode(', '),
                    'barcodes' => $shipments->flatMap(fn(array $shipment) => $shipment['barcodes'] ?? [])->filter()->unique()->values()->all(),
                ];
            })
            ->values();
    }

    protected function groupCargoShipments(Collection $cargoRows): Collection
    {
        return $cargoRows
            ->groupBy(function (array $item) {
                $tracking = $this->normalizeTrackingNumber($item['takip_no'] ?? '');
                $tesellumNo = $this->normalizeOrderNumber($item['tesellum_fatura_no'] ?? '');
                $orderNumber = $this->normalizeOrderNumber($item['web_siparis_kodu'] ?? '');
                $rowNumber = (int) ($item['_row_number'] ?? 0);

                if ($tracking !== '') {
                    return 'tracking:' . $tracking;
                }

                if ($tesellumNo !== '') {
                    return 'invoice:' . $orderNumber . '|' . $tesellumNo;
                }

                if ($orderNumber !== '') {
                    return 'order:' . $orderNumber . '|' . ($item['fatura_tarihi'] ?? $item['teslim_tarihi'] ?? $rowNumber);
                }

                return 'row:' . $rowNumber;
            })
            ->map(function (Collection $rows) {
                $first = $rows->first();

                return [
                    'web_siparis_kodu' => $first['web_siparis_kodu'] ?? null,
                    'tracking_no' => $first['takip_no'] ?? null,
                    'alici' => $first['alici'] ?? null,
                    'gonderen' => $first['gonderen'] ?? ($first['borclu_unvan'] ?? null),
                    'adet' => max((int) $rows->sum('adet'), $rows->count()),
                    'desi' => (float) $rows->sum('desi'),
                    'tutar' => $this->resolveShipmentAmount($rows),
                    'teslim_tarihi' => $first['teslim_tarihi'] ?? null,
                    'fatura_tarihi' => $first['fatura_tarihi'] ?? null,
                    'tesellum_fatura_no' => $first['tesellum_fatura_no'] ?? null,
                    'alici_il' => $first['alici_il'] ?? null,
                    'alici_ilce' => $first['alici_ilce'] ?? null,
                    'barcodes' => $rows->pluck('barkod')->filter()->unique()->values()->all(),
                ];
            })
            ->values();
    }

    protected function resolveShipmentAmount(Collection $rows): float
    {
        $amounts = $rows
            ->pluck('tutar')
            ->map(fn($amount) => round((float) $amount, 2))
            ->filter(fn($amount) => $amount > 0)
            ->values();

        if ($amounts->isEmpty()) {
            return 0.0;
        }

        $uniqueAmounts = $amounts->unique()->values();

        if ($uniqueAmounts->count() === 1) {
            return (float) $uniqueAmounts->first();
        }

        return (float) $uniqueAmounts->sum();
    }

    protected function buildCompareBucketKey(array $shipment): string
    {
        $orderNumber = $this->normalizeOrderNumber($shipment['web_siparis_kodu'] ?? '');
        if ($orderNumber !== '') {
            return 'order:' . $orderNumber;
        }

        $tracking = $this->normalizeTrackingNumber($shipment['tracking_no'] ?? '');
        if ($tracking !== '') {
            return 'tracking:' . $tracking;
        }

        return 'customer:' . $this->normalizeCustomerName((string) ($shipment['alici'] ?? '')) . '|' . ($shipment['fatura_tarihi'] ?? $shipment['teslim_tarihi'] ?? '');
    }

    protected function detectDominantSender(Collection $cargoData): ?string
    {
        $senderKey = $cargoData
            ->map(function (array $row) {
                return $this->normalizeCustomerName((string) ($row['gonderen'] ?: ($row['borclu_unvan'] ?? '')));
            })
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        if (!$senderKey) {
            return null;
        }

        return $cargoData
            ->map(fn(array $row) => $row['gonderen'] ?: ($row['borclu_unvan'] ?? null))
            ->first(fn($value) => $this->normalizeCustomerName((string) $value) === $senderKey);
    }

    protected function isOutgoingCargoRow(array $item, ?string $dominantSender = null): bool
    {
        $sender = $this->normalizeCustomerName((string) ($item['gonderen'] ?: ($item['borclu_unvan'] ?? '')));
        $receiver = $this->normalizeCustomerName((string) ($item['alici'] ?? ''));
        $dominantSender = $this->normalizeCustomerName((string) $dominantSender);

        if ($sender !== '' && $dominantSender !== '') {
            if ($sender === $dominantSender) {
                return true;
            }

            if ($receiver === $dominantSender) {
                return false;
            }
        }

        $cikisIl = $this->normalizeString($item['cikis_il'] ?? '');
        if ($cikisIl !== '') {
            return $cikisIl === $this->normalizeString(config('cargo.origin_city', 'Denizli'));
        }

        return !empty($item['web_siparis_kodu']) || $sender === $dominantSender;
    }

    protected function formatTrackingLabel(array $trackingNumbers): ?string
    {
        $trackingNumbers = array_values(array_filter(array_unique(array_map('strval', $trackingNumbers))));

        if (empty($trackingNumbers)) {
            return null;
        }

        if (count($trackingNumbers) === 1) {
            return $trackingNumbers[0];
        }

        return $trackingNumbers[0] . ' +' . (count($trackingNumbers) - 1);
    }

    /**
     * Ana karşılaştırma işlemi
     */
    protected function performComparison(
        Collection $cargoData,
        Collection $orderData,
        Collection $products
    ): Collection {
        $results = collect();

        // İndeksler oluştur
        // 1. Takip numarasına göre indeks (birincil eşleştirme)
        $ordersByTracking = $orderData->filter(function ($item) {
            return !empty($item['kargo_takip']);
        })->groupBy(function ($item) {
            return $this->normalizeTrackingNumber($item['kargo_takip']);
        });

        // 2. Müşteri adına göre indeks (yedek eşleştirme)
        $ordersByCustomer = $orderData->groupBy(function ($item) {
            return $this->normalizeCustomerName($item['musteri']);
        });

        // Kullanılmış siparişleri takip et (aynı siparişi birden fazla kez kullanmamak için)
        $usedOrderKeys = [];

        foreach ($cargoData as $cargoItem) {
            $takipNo = $this->normalizeTrackingNumber($cargoItem['takip_no'] ?? '');
            $normalizedAlici = $this->normalizeCustomerName($cargoItem['alici']);

            // 1. Önce takip numarasıyla eşleştir (en doğru yöntem)
            $matchedOrders = collect();
            $matchType = 'none';

            if (!empty($takipNo) && $ordersByTracking->has($takipNo)) {
                $matchedOrders = $ordersByTracking->get($takipNo);
                $matchType = 'tracking';
            }

            // 2. Takip numarası bulunamazsa, müşteri adıyla dene
            if ($matchedOrders->isEmpty()) {
                $matchedOrders = $this->findMatchingOrdersByCustomer(
                    $normalizedAlici, 
                    $ordersByCustomer, 
                    $usedOrderKeys
                );
                if ($matchedOrders->isNotEmpty()) {
                    $matchType = 'customer';
                }
            }

            if ($matchedOrders->isEmpty()) {
                // Eşleşme yok
                $results->push($this->createUnmatchedResult($cargoItem));
                $this->stats['unmatched']++;
                continue;
            }

            // Kullanılan siparişleri işaretle (müşteri bazlı eşleşmelerde tekrar kullanılmaması için)
            if ($matchType === 'customer') {
                foreach ($matchedOrders as $order) {
                    $orderKey = ($order['musteri'] ?? '') . '|' . ($order['stok_kodu'] ?? '') . '|' . ($order['siparis_no'] ?? '');
                    $usedOrderKeys[$orderKey] = true;
                }
            }

            // Eşleşen siparişler için değerleri hesapla
            $comparisonResult = $this->compareWithOrders($cargoItem, $matchedOrders, $products);
            $comparisonResult['match_type'] = $matchType; // Eşleşme tipini kaydet
            $results->push($comparisonResult);

            $this->stats['matched']++;

            if ($comparisonResult['has_error']) {
                $this->stats['errors']++;
            }
        }

        return $results;
    }

    /**
     * Takip numarasını normalize et
     */
    protected function normalizeTrackingNumber(?string $trackingNo): string
    {
        if (empty($trackingNo)) {
            return '';
        }
        // Sadece rakamları al, boşlukları ve özel karakterleri kaldır
        return preg_replace('/[^0-9]/', '', trim($trackingNo));
    }

    protected function normalizeOrderNumber(?string $orderNumber): string
    {
        if (empty($orderNumber)) {
            return '';
        }

        return preg_replace('/\s+/', '', trim((string) $orderNumber));
    }

    /**
     * Müşteri adına göre eşleşen siparişleri bul (yedek yöntem)
     */
    protected function findMatchingOrdersByCustomer(
        string $normalizedName, 
        Collection $ordersByCustomer,
        array $usedOrderKeys = []
    ): Collection {
        $candidates = collect();

        // Tam eşleşme
        if ($ordersByCustomer->has($normalizedName)) {
            $candidates = $ordersByCustomer->get($normalizedName);
        } else {
            // Fuzzy matching
            $threshold = config('cargo.matching.fuzzy_threshold', 85);

            foreach ($ordersByCustomer as $customerName => $orders) {
                if ($this->similarityScore($normalizedName, $customerName) >= $threshold) {
                    $candidates = $orders;
                    break;
                }
            }
        }

        // Daha önce kullanılmış siparişleri filtrele
        if (!empty($usedOrderKeys)) {
            $candidates = $candidates->filter(function ($order) use ($usedOrderKeys) {
                $orderKey = ($order['musteri'] ?? '') . '|' . ($order['stok_kodu'] ?? '') . '|' . ($order['siparis_no'] ?? '');
                return !isset($usedOrderKeys[$orderKey]);
            });
        }

        return $candidates;
    }

    /**
     * Kargo ve sipariş verilerini karşılaştır
     */
    protected function compareWithOrders(
        array $cargoItem,
        Collection $orders,
        Collection $products
    ): array {
        // Tüm siparişler için beklenen değerleri hesapla
        $expectedValues = [
            'parca' => 0,
            'desi' => 0,
            'tutar' => 0,
        ];

        $stokKodlari = [];
        $urunAdlari = [];
        $pazaryeri = null;
        $magaza = null;
        $siparisNo = null;
        $totalAdet = 0;
        
        // Sipariş detaylarını topla (tooltip için)
        $siparisDetay = [];

        foreach ($orders as $order) {
            $stokKodu = $order['stok_kodu'] ?? '';
            $adet = max(1, (int) ($order['adet'] ?? 1));
            $totalAdet += $adet;

            if (!empty($stokKodu)) {
                $stokKodlari[] = $stokKodu;
            }
            if (!empty($order['urun_adi'])) {
                $urunAdlari[] = $order['urun_adi'];
            }

            $pazaryeri = $pazaryeri ?? $order['pazaryeri'] ?? null;
            $magaza = $magaza ?? $order['magaza'] ?? null;
            $siparisNo = $siparisNo ?? $order['siparis_no'] ?? null;

            // Sipariş detayını kaydet
            $siparisDetay[] = [
                'stok_kodu' => $stokKodu,
                'urun_adi' => $order['urun_adi'] ?? '',
                'adet' => $adet,
                'pazaryeri' => $order['pazaryeri'] ?? null,
                'magaza' => $order['magaza'] ?? null,
            ];

            // Ürün bilgilerini çek
            if (!empty($stokKodu) && $products->has($stokKodu)) {
                $product = $products->get($stokKodu);
                $productValues = $product->calculateExpectedValues($adet);
                $expectedValues['parca'] += $productValues['parca'];
                $expectedValues['desi'] += $productValues['desi'];
                $expectedValues['tutar'] += $productValues['tutar'];
                
                // Ürün bilgilerini detaya ekle
                $siparisDetay[count($siparisDetay) - 1]['parca'] = $productValues['parca'];
                $siparisDetay[count($siparisDetay) - 1]['desi'] = $productValues['desi'];
                $siparisDetay[count($siparisDetay) - 1]['tutar'] = $productValues['tutar'];
            } else {
                // Ürün bulunamadı
                $siparisDetay[count($siparisDetay) - 1]['parca'] = 0;
                $siparisDetay[count($siparisDetay) - 1]['desi'] = 0;
                $siparisDetay[count($siparisDetay) - 1]['tutar'] = 0;
                $siparisDetay[count($siparisDetay) - 1]['not_found'] = true;
            }
        }

        // Gerçek değerler (kargo raporundan)
        $actualValues = [
            'parca' => (int) ($cargoItem['adet'] ?? 1),
            'desi' => (float) ($cargoItem['desi'] ?? 0),
            'tutar' => (float) ($cargoItem['tutar'] ?? 0),
        ];

        // Fark hesapla
        $diff = [
            'parca' => $actualValues['parca'] - $expectedValues['parca'],
            'desi' => $actualValues['desi'] - $expectedValues['desi'],
            'tutar' => $actualValues['tutar'] - $expectedValues['tutar'],
        ];

        // Hata tipi belirle
        $errorType = $this->determineErrorType($diff);

        return [
            'tarih' => $this->parseDate($cargoItem['teslim_tarihi'] ?? $cargoItem['fatura_tarihi'] ?? null),
            'musteri_adi' => $cargoItem['alici'],
            'takip_kodu' => $cargoItem['takip_no'],
            'stok_kodu' => mb_strimwidth(implode(', ', array_unique($stokKodlari)), 0, 30, '...'),
            'urun_adi' => mb_strimwidth(implode(', ', array_unique($urunAdlari)), 0, 250, '...'),
            'adet' => $totalAdet,
            'beklenen_parca' => $expectedValues['parca'],
            'beklenen_desi' => $expectedValues['desi'],
            'beklenen_tutar' => $expectedValues['tutar'],
            'gercek_parca' => $actualValues['parca'],
            'gercek_desi' => $actualValues['desi'],
            'gercek_tutar' => $actualValues['tutar'],
            'parca_fark' => $diff['parca'],
            'desi_fark' => $diff['desi'],
            'tutar_fark' => $diff['tutar'],
            'error_type' => $errorType,
            'has_error' => $errorType !== 'none',
            'is_matched' => true,
            'pazaryeri' => $pazaryeri,
            'magaza' => $magaza,
            'siparis_no' => $siparisNo,
            'siparis_detay' => $siparisDetay,
            'cikis_il' => $cargoItem['cikis_il'],
            'is_iade' => false,
            // Parça gönderisi: Tüm siparişlerin satır fiyatı 0 ise
            'is_parca_gonderi' => $this->isParcaGonderi($orders),
        ];
    }

    /**
     * Parça gönderisi mi kontrol et
     * Sipariş satır fiyatı 0 TL ise parça gönderisi
     */
    protected function isParcaGonderi(Collection $orders): bool
    {
        if ($orders->isEmpty()) {
            return false;
        }

        // Tüm siparişlerin satır fiyatı toplamı 0 ise parça gönderisi
        $totalFiyat = $orders->sum(function ($order) {
            return (float) ($order['satir_fiyat'] ?? -1); // -1 = kolon yok, 0 = gerçekten 0
        });

        // Tüm siparişlerin satir_fiyat değeri var ve toplam 0 ise
        $hasAllPrices = $orders->every(function ($order) {
            return isset($order['satir_fiyat']);
        });

        return $hasAllPrices && $totalFiyat == 0;
    }

    /**
     * Eşleşmeyen kargo için sonuç oluştur
     */
    protected function createUnmatchedResult(array $cargoItem): array
    {
        return [
            'tarih' => $this->parseDate($cargoItem['teslim_tarihi'] ?? $cargoItem['fatura_tarihi'] ?? null),
            'musteri_adi' => $cargoItem['alici'],
            'takip_kodu' => $cargoItem['takip_no'] ?? $this->formatTrackingLabel($cargoItem['tracking_numbers'] ?? []),
            'stok_kodu' => null,
            'urun_adi' => null,
            'adet' => (int) ($cargoItem['adet'] ?? 1),
            'beklenen_parca' => 0,
            'beklenen_desi' => 0,
            'beklenen_tutar' => 0,
            'gercek_parca' => (int) ($cargoItem['adet'] ?? 1),
            'gercek_desi' => (float) ($cargoItem['desi'] ?? 0),
            'gercek_tutar' => (float) ($cargoItem['tutar'] ?? 0),
            'parca_fark' => 0,
            'desi_fark' => 0,
            'tutar_fark' => 0,
            'error_type' => 'eslesmedi',
            'has_error' => true,
            'is_matched' => false,
            'pazaryeri' => null,
            'magaza' => null,
            'siparis_no' => null,
            'cikis_il' => $cargoItem['cikis_il'] ?? ($cargoItem['alici_il'] ?? null),
            'is_iade' => false,
            'is_parca_gonderi' => false,
            'siparis_detay' => null,
        ];
    }

    /**
     * Hata tipini belirle
     */
    protected function determineErrorType(array $diff): string
    {
        $tolerances = config('cargo.tolerances') ?? $this->getDefaultTolerances();

        // Parça kontrolü
        if ($diff['parca'] < -abs($tolerances['parca'])) {
            return 'parca_eksik';
        }
        if ($diff['parca'] > abs($tolerances['parca'])) {
            return 'parca_fazla';
        }

        // Desi kontrolü
        if ($diff['desi'] < -abs($tolerances['desi'])) {
            return 'desi_eksik';
        }
        if ($diff['desi'] > abs($tolerances['desi'])) {
            return 'desi_fazla';
        }

        // Tutar kontrolü
        if ($diff['tutar'] < -abs($tolerances['tutar'])) {
            return 'tutar_eksik';
        }
        if ($diff['tutar'] > abs($tolerances['tutar'])) {
            return 'tutar_fazla';
        }

        return 'none';
    }

    /**
     * Rapor oluştur ve kaydet
     */
    protected function createReport(
        Collection $results,
        string $name,
        string $cargoCompany,
        string $cargoFileName,
        ?string $orderFileName
    ): CargoReport {
        $supportsReferenceMissingErrorType = $this->ensureReferenceMissingErrorTypeSupport();

        return DB::transaction(function () use ($results, $name, $cargoCompany, $cargoFileName, $orderFileName, $supportsReferenceMissingErrorType) {
            // Ana raporu oluştur
            $report = CargoReport::create([
                'user_id' => auth()->id(),
                'name' => $name,
                'cargo_company' => $cargoCompany,
                'report_date' => now()->toDateString(),
                'total_orders' => $results->count(),
                'matched_orders' => $results->where('is_matched', true)->count(),
                'unmatched_orders' => $results->where('is_matched', false)->count(),
                'error_count' => $results->where('has_error', true)->count(),
                'total_expected_desi' => $results->sum('beklenen_desi'),
                'total_actual_desi' => $results->sum('gercek_desi'),
                'total_desi_diff' => $results->sum('desi_fark'),
                'total_expected_tutar' => $results->sum('beklenen_tutar'),
                'total_actual_tutar' => $results->sum('gercek_tutar'),
                'total_tutar_diff' => $results->sum('tutar_fark'),
                'cargo_file_name' => $cargoFileName,
                'order_file_name' => $orderFileName,
                'status' => 'completed',
            ]);

            // Toplu insert için hazırla
            $now = now();
            $chunks = $results->chunk(500); // 500'lük paketler halinde işle

            foreach ($chunks as $chunk) {
                $insertData = [];
                foreach ($chunk as $item) {
                    // Veritabanı şemasında olmayan geçici alanları temizle
                    $matchType = $item['match_type'] ?? null; // Log için tutulabilir ama DB'de yoksa çıkar
                    unset($item['match_type']);

                    // Dizileri JSON'a çevir (insert cast yapmaz)
                    if (isset($item['siparis_detay']) && is_array($item['siparis_detay'])) {
                        $item['siparis_detay'] = json_encode($item['siparis_detay']);
                    }

                    $originalErrorType = (string) ($item['error_type'] ?? 'none');
                    $item['error_type'] = $this->normalizePersistedErrorType($originalErrorType, $supportsReferenceMissingErrorType);

                    if ($originalErrorType === 'referans_eksik' && $item['error_type'] !== 'referans_eksik') {
                        $item['has_error'] = false;
                    }

                    // Metadata ekle
                    $item['cargo_report_id'] = $report->id;
                    $item['created_at'] = $now;
                    $item['updated_at'] = $now;

                    $insertData[] = $item;
                }
                
                CargoReportItem::insert($insertData);
            }

            return $report;
        });
    }

    protected function ensureReferenceMissingErrorTypeSupport(): bool
    {
        if ($this->supportsReferenceMissingErrorType !== null) {
            return $this->supportsReferenceMissingErrorType;
        }

        try {
            if ($this->cargoReportItemErrorTypeIncludesReferenceMissing()) {
                return $this->supportsReferenceMissingErrorType = true;
            }

            DB::statement("
                ALTER TABLE cargo_report_items
                MODIFY COLUMN error_type ENUM(
                    'none',
                    'referans_eksik',
                    'desi_eksik',
                    'desi_fazla',
                    'tutar_eksik',
                    'tutar_fazla',
                    'parca_eksik',
                    'parca_fazla',
                    'eslesmedi'
                ) NOT NULL DEFAULT 'none'
            ");

            $this->supportsReferenceMissingErrorType = $this->cargoReportItemErrorTypeIncludesReferenceMissing();

            if ($this->supportsReferenceMissingErrorType) {
                Log::info('CargoComparisonEngine: error_type enum referans_eksik desteğiyle güncellendi.');
            }
        } catch (\Throwable $e) {
            Log::warning('CargoComparisonEngine: error_type enum güncellenemedi, referans uyarıları geriye uyumlu kaydedilecek.', [
                'error' => $e->getMessage(),
            ]);

            $this->supportsReferenceMissingErrorType = false;
        }

        return $this->supportsReferenceMissingErrorType;
    }

    protected function cargoReportItemErrorTypeIncludesReferenceMissing(): bool
    {
        $column = DB::selectOne("SHOW COLUMNS FROM cargo_report_items LIKE 'error_type'");
        $type = is_object($column) ? ($column->Type ?? null) : ($column['Type'] ?? null);

        return is_string($type) && str_contains($type, "'referans_eksik'");
    }

    protected function normalizePersistedErrorType(string $errorType, bool $supportsReferenceMissingErrorType): string
    {
        $supportedErrorTypes = array_keys(CargoReportItem::ERROR_TYPES);

        if (!$supportsReferenceMissingErrorType) {
            $supportedErrorTypes = array_values(array_diff($supportedErrorTypes, ['referans_eksik']));
        }

        return in_array($errorType, $supportedErrorTypes, true) ? $errorType : 'none';
    }

    /**
     * Müşteri adını normalize et
     */
    public function normalizeCustomerName(string $name): string
    {
        // Küçük harfe çevir
        $name = mb_strtolower($name, 'UTF-8');

        // Türkçe karakter dönüşümü (opsiyonel)
        if (config('cargo.matching.normalize_chars')) {
            $map = [
                'ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u',
                'Ç' => 'c', 'Ğ' => 'g', 'İ' => 'i', 'Ö' => 'o', 'Ş' => 's', 'Ü' => 'u',
            ];
            $name = strtr($name, $map);
        }

        // Özel karakterleri kaldır
        $name = preg_replace('/[^a-z0-9\s]/u', '', $name);

        // Çoklu boşlukları tek boşluğa indir
        $name = preg_replace('/\s+/', ' ', trim($name));

        return $name;
    }

    /**
     * Benzerlik skoru hesapla
     */
    protected function similarityScore(string $a, string $b): float
    {
        similar_text($a, $b, $percent);
        return $percent;
    }

    /**
     * String değeri normalize et
     */
    protected function normalizeString(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return mb_strtolower(trim($value), 'UTF-8');
    }

    /**
     * Değeri temizle
     */
    protected function cleanValue($value): string
    {
        if ($value === null) {
            return '';
        }
        return trim((string) $value);
    }

    /**
     * Tarihi parse et
     */
    protected function parseDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            if ($value instanceof \DateTime) {
                return $value->format('Y-m-d');
            }

            if (is_numeric($value)) {
                // Excel serial date
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
                return $date->format('Y-m-d');
            }

            $timestamp = strtotime((string) $value);
            if ($timestamp === false) {
                return null;
            }

            return date('Y-m-d', $timestamp);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * İstatistikleri al
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    protected function resetStats(): void
    {
        $this->stats = [
            'total_cargo_rows' => 0,
            'total_order_rows' => 0,
            'loaded_marketplace_orders' => 0,
            'matched' => 0,
            'unmatched' => 0,
            'errors' => 0,
            'iade_count' => 0,
            'iade_tutar' => 0,
            'parca_count' => 0,
            'parca_tutar' => 0,
            'reference_issues' => 0,
        ];
    }

    /**
     * Varsayılan kargo kolon eşleştirmeleri
     */
    protected function getDefaultCargoColumns(): array
    {
        return [
            'web_siparis_kodu' => ['WebSiparisKodu', 'Web Sipariş Kodu', 'Sipariş No', 'Order No'],
            'takip_no' => ['TakipNo', 'Takip No', 'Takip Numarası', 'Kargo Takip No'],
            'alici' => ['Alici', 'AliciUnvan', 'Alıcı', 'Alıcı Unvan', 'Müşteri', 'Müşteri Adı'],
            'gonderen' => ['GonderenUnvan', 'Gönderen', 'Gönderen Adı', 'Gönderen Unvan', 'Sender'],
            'borclu_unvan' => ['BorcluUnvan', 'Borçlu Unvan', 'Borçlu'],
            'adet' => ['Adet', 'Parça', 'Koli', 'Parça Sayısı'],
            'desi' => ['ToplamDesi', 'Toplam Desi', 'Desi', 'Hacim'],
            'tutar' => ['Tutar', 'Toplam Tutar', 'Kargo Ücreti', 'Ücret'],
            'cikis_il' => ['CikisIl', 'Çıkış İl', 'Çıkış İli', 'Gönderen İl'],
            'teslim_tarihi' => ['TeslimTarihi', 'Teslim Tarihi', 'Teslimat Tarihi'],
            'fatura_tarihi' => ['FaturaTarihi', 'Fatura Tarihi'],
            'tesellum_fatura_no' => ['TesellumdenFaturaNo', 'Tesellümden Fatura No', 'Tesellum Fatura No'],
            'barkod' => ['Barkod', 'Kargo Barkodu'],
            'alici_il' => ['AliciIlAdi', 'Alıcı İl', 'Alıcı İli'],
            'alici_ilce' => ['AliciIlceAdi', 'Alıcı İlçe', 'Alıcı İlçesi'],
            'durum' => ['TeslimatDurum', 'Teslimat Durum', 'Durum', 'Kargo Durumu'],
        ];
    }

    /**
     * Varsayılan sipariş kolon eşleştirmeleri
     */
    protected function getDefaultOrderColumns(): array
    {
        return [
            'musteri' => ['Sevk - Müşteri', 'Müşteri', 'Müşteri Adı', 'Alıcı', 'Müşteri Sevk - [Fatura]'],
            'stok_kodu' => ['Stok Kodu', 'StokKodu', 'SKU', 'Ürün Kodu'],
            'urun_adi' => ['Ürün', 'Ürün Adı', 'Ürün Açıklaması'],
            'adet' => ['Adet', 'Miktar', 'Sipariş Adedi'],
            'pazaryeri' => ['Pazaryeri', 'Platform', 'Kanal'],
            'magaza' => ['Mağaza', 'Satıcı', 'Store'],
            'siparis_no' => ['Sipariş No', 'SiparişNo', 'Order No', 'Sipariş Numarası', 'Muhasebe Sip. No'],
            'kargo_takip' => ['Kargo Takip No', 'Takip No', 'Tracking', 'Sip. No', 'Gönderi No', 'Kargo No', 'Sevkiyat No'],
        ];
    }

    /**
     * Varsayılan toleranslar
     */
    protected function getDefaultTolerances(): array
    {
        return [
            'desi' => 2.0,
            'tutar' => 5.0,
            'parca' => 0,
        ];
    }
}
