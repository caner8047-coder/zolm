<?php

namespace App\Services;

use App\Models\CargoReport;
use App\Models\CargoReportItem;
use App\Models\Product;
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
    /**
     * Karşılaştırma sonuç istatistikleri
     */
    protected array $stats = [
        'total_cargo_rows' => 0,
        'total_order_rows' => 0,
        'matched' => 0,
        'unmatched' => 0,
        'errors' => 0,
        'iade_count' => 0,
        'iade_tutar' => 0,
        'parca_count' => 0,
        'parca_tutar' => 0,
    ];

    public function __construct()
    {
        // Constructor
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
        UploadedFile $orderFile,
        ?string $reportName = null,
        ?string $cargoCompany = null
    ): array {
        try {
            Log::info('CargoComparisonEngine: Karşılaştırma başladı', [
                'cargo_file' => $cargoFile->getClientOriginalName(),
                'order_file' => $orderFile->getClientOriginalName(),
            ]);

            // 1. Excel dosyalarını parse et
            $cargoData = $this->parseCargoFile($cargoFile);
            $orderData = $this->parseOrderFile($orderFile);

            $this->stats['total_cargo_rows'] = $cargoData->count();
            $this->stats['total_order_rows'] = $orderData->count();

            Log::info('CargoComparisonEngine: Dosyalar parse edildi', [
                'cargo_rows' => $cargoData->count(),
                'order_rows' => $orderData->count(),
            ]);

            // 2. Ürün listesini al
            $products = Product::active()->get()->keyBy('stok_kodu');

            // 3. Sipariş/İade ayrımı (Çıkış İli = Denizli → Sipariş, diğerleri → İade/Değişim)
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
            // İade toplam tutar (kargo maliyeti)
            $this->stats['iade_tutar'] = $returnCargo->sum(function ($item) {
                return (float) ($item['tutar'] ?? 0);
            });

            // 4. Giden siparişler için karşılaştırma yap
            $comparisonResults = $this->performComparison($outgoingCargo, $orderData, $products);

            // Parça gönderisi sayı ve tutar hesapla
            $parcaGonderileri = $comparisonResults->filter(function ($item) {
                return ($item['is_parca_gonderi'] ?? false) === true;
            });
            $this->stats['parca_count'] = $parcaGonderileri->count();
            $this->stats['parca_tutar'] = $parcaGonderileri->sum(function ($item) {
                return (float) ($item['gercek_tutar'] ?? 0);
            });

            // 5. İade/Değişim gönderilerini ekle (ayrı liste olarak)
            $returnResults = $this->processReturns($returnCargo);
            
            // Tüm sonuçları birleştir
            $allResults = $comparisonResults->concat($returnResults);

            // 6. Rapor oluştur ve kaydet
            $report = $this->createReport(
                $allResults,
                $reportName ?? 'Kargo Raporu - ' . now()->format('d.m.Y H:i'),
                $cargoCompany ?? config('cargo.companies.' . config('cargo.default_company') . '.name'),
                $cargoFile->getClientOriginalName(),
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

    /**
     * İade/Değişim gönderilerini işle
     */
    protected function processReturns(Collection $returnCargo): Collection
    {
        return $returnCargo->map(function ($item) {
            // İade için gönderen adını kullan (müşteri), alıcı değil (firma adı)
            $musteriAdi = !empty($item['gonderen']) ? $item['gonderen'] : $item['alici'];
            
            return [
                'tarih' => $this->parseDate($item['teslim_tarihi'] ?? null),
                'musteri_adi' => $musteriAdi,
                'takip_kodu' => $item['takip_no'],
                'stok_kodu' => null,
                'urun_adi' => 'İade/Değişim Gönderisi',
                'adet' => 1,
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
                'siparis_no' => null,
                'siparis_detay' => null,
                'cikis_il' => $item['cikis_il'],
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
        foreach ($data as $row) {
            $item = $this->extractRowData($row, $columnMap, 'cargo');
            if (!empty($item['alici'])) {
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
        foreach ($data as $row) {
            $item = $this->extractRowData($row, $columnMap, 'order');
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
    protected function extractRowData(array $row, array $columnMap, string $type): array
    {
        if ($type === 'cargo') {
            return [
                'takip_no' => $this->cleanValue($row[$columnMap['takip_no'] ?? ''] ?? ''),
                'alici' => $this->cleanValue($row[$columnMap['alici'] ?? ''] ?? ''),
                'gonderen' => $this->cleanValue($row[$columnMap['gonderen'] ?? ''] ?? ''),
                'adet' => (int) ($row[$columnMap['adet'] ?? ''] ?? 1),
                'desi' => (float) ($row[$columnMap['desi'] ?? ''] ?? 0),
                'tutar' => (float) ($row[$columnMap['tutar'] ?? ''] ?? 0),
                'cikis_il' => $this->cleanValue($row[$columnMap['cikis_il'] ?? ''] ?? ''),
                'teslim_tarihi' => $row[$columnMap['teslim_tarihi'] ?? ''] ?? null,
                'durum' => $this->cleanValue($row[$columnMap['durum'] ?? ''] ?? ''),
            ];
        }

        // Order type
        return [
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
        if ($value === null || $value === '') {
            return 0.0;
        }
        
        // String ise Türkçe formatı dönüştür
        if (is_string($value)) {
            // Binlik ayracı (nokta) kaldır, ondalık ayracı (virgül) noktaya çevir
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
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
            'tarih' => $this->parseDate($cargoItem['teslim_tarihi'] ?? null),
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
            'tarih' => $this->parseDate($cargoItem['teslim_tarihi'] ?? null),
            'musteri_adi' => $cargoItem['alici'],
            'takip_kodu' => $cargoItem['takip_no'],
            'stok_kodu' => null,
            'urun_adi' => null,
            'adet' => 1,
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
            'cikis_il' => $cargoItem['cikis_il'],
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
        string $orderFileName
    ): CargoReport {
        return DB::transaction(function () use ($results, $name, $cargoCompany, $cargoFileName, $orderFileName) {
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

            return date('Y-m-d', strtotime($value));
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

    /**
     * Varsayılan kargo kolon eşleştirmeleri
     */
    protected function getDefaultCargoColumns(): array
    {
        return [
            'takip_no' => ['TakipNo', 'Takip No', 'Takip Numarası', 'Kargo Takip No'],
            'alici' => ['Alici', 'Alıcı', 'Müşteri', 'Müşteri Adı'],
            'gonderen' => ['GonderenUnvan', 'Gönderen', 'Gönderen Adı', 'Gönderen Unvan', 'Sender'],
            'adet' => ['Adet', 'Parça', 'Koli', 'Parça Sayısı'],
            'desi' => ['ToplamDesi', 'Toplam Desi', 'Desi', 'Hacim'],
            'tutar' => ['Tutar', 'Toplam Tutar', 'Kargo Ücreti', 'Ücret'],
            'cikis_il' => ['CikisIl', 'Çıkış İl', 'Çıkış İli', 'Gönderen İl'],
            'teslim_tarihi' => ['TeslimTarihi', 'Teslim Tarihi', 'Teslimat Tarihi'],
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

