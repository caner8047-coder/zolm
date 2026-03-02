<?php

namespace App\Services;

use App\Models\MpProduct;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pazaryeri Ürünleri — Excel İçe/Dışa Aktarma Servisi
 *
 * İki farklı Excel formatını otomatik algılayarak import eder:
 *  1. Manuel Ürün Listesi (Stok Kodu – Ürün Adı – Maliyet – Desi – Durum – Yayında)
 *  2. Trendyol Dışa Aktarma (Barkod – Fiyatlar – Komisyon – Stok – Görseller – vb.)
 *
 * /excel-export workflow kurallarını uygular:
 *  - cleanString(): UTF-8 encoding + XML kontrol karakteri temizleme
 *  - setCellValueExplicit(): tip belirten Excel yazımı
 *  - sanitizeSheetName(): 31 karakter + yasak karakter kontrolü
 */
class MpProductImportService
{
    protected int $userId;
    protected int $imported = 0;
    protected int $updated  = 0;
    protected int $skipped  = 0;
    protected array $errors = [];

    // ─── Manuel Excel Sütun Eşleştirme ─────────────────────
    protected const MANUAL_HEADERS = [
        'Stok Kodu'       => 'stock_code',
        'Ürün Adı'        => 'product_name',
        'Kategori'        => 'category_name',
        'Marka'           => 'brand',
        'Stok'            => 'stock_quantity',
        'Variyant'        => 'variant',
        'Kargo Maliyeti'  => 'cargo_cost',
        'Ü.Maliyeti'      => 'cogs',
        'Parça'           => 'pieces',
        'Desi'            => 'desi',
        'Durum'           => 'status',
        'Yayında'         => 'platforms',
    ];

    // ─── Trendyol Excel Sütun Eşleştirme ───────────────────
    protected const TRENDYOL_HEADERS = [
        'Partner ID'                              => 'partner_id',
        'Barkod'                                  => 'barcode',
        'Komisyon Oranı'                          => 'commission_rate',
        'Model Kodu'                              => 'model_code',
        'Ürün Rengi'                              => 'color',
        'Beden'                                   => 'size',
        'Boyut/Ebat'                              => 'dimension',
        'Cinsiyet'                                => 'gender',
        'Marka'                                   => 'brand',
        'Kategori İsmi'                           => 'category_name',
        'Tedarikçi Stok Kodu'                     => 'stock_code',
        'Ürün Adı'                                => 'product_name',
        'Ürün Açıklaması'                         => 'description',
        'Piyasa Satış Fiyatı (KDV Dahil)'         => 'market_price',
        "Trendyol'da Satılacak Fiyat (KDV Dahil)" => 'sale_price',
        'BuyBox Fiyatı'                           => 'buybox_price',
        'Ürün Stok Adedi'                         => 'stock_quantity',
        'KDV Oranı'                               => 'vat_rate',
        'ÖTV Oranı'                               => 'otv_rate',
        'Desi'                                    => 'desi',
        'Görsel 1'                                => 'image_url',
        'Görsel 2'                                => '_image_2',
        'Görsel 3'                                => '_image_3',
        'Görsel 4'                                => '_image_4',
        'Görsel 5'                                => '_image_5',
        'Görsel 6'                                => '_image_6',
        'Görsel 7'                                => '_image_7',
        'Görsel 8'                                => '_image_8',
        'Sevkiyat Süresi'                         => 'shipping_days',
        'Sevkiyat Tipi'                           => 'shipping_type',
        'Durum'                                   => 'status',
        'Durum Açıklaması'                        => 'status_description',
        'Trendyol.com Linki'                      => 'trendyol_link',
    ];

    public function __construct()
    {
        $this->userId = Auth::id() ?? 1;
    }

    // ═══════════════════════════════════════════════════════════
    //  ANA İMPORT — Otomatik Format Algılama
    // ═══════════════════════════════════════════════════════════

    /**
     * Dosya tipini otomatik algıla ve import et
     */
    public function import(UploadedFile $file): array
    {
        try {
            $rows = $this->readExcel($file);
        } catch (\Throwable $e) {
            return $this->result('Excel dosyası okunamadı: ' . $e->getMessage());
        }

        if ($rows->isEmpty()) {
            return $this->result('Dosya boş veya okunamadı. Lütfen .xlsx formatında olduğundan emin olun.');
        }

        $headers = $rows->first();
        $type = $this->detectType($headers);

        if ($type === 'trendyol') {
            return $this->importTrendyol($rows);
        } elseif ($type === 'manual') {
            return $this->importManual($rows);
        }

        return $this->result('Excel dosyasının formatı tanınamadı. Algılanan başlıklar: ' . implode(', ', array_filter(array_map('trim', $headers))));
    }

    /**
     * Header satırından dosya tipini algıla
     */
    protected function detectType(array $headers): ?string
    {
        $headerSet = array_map(fn($h) => mb_strtolower(trim($h ?? '')), $headers);

        // Trendyol: "Barkod" + "Partner ID" + "Komisyon Oranı" varsa
        if (
            in_array('barkod', $headerSet) &&
            in_array('partner id', $headerSet)
        ) {
            return 'trendyol';
        }

        // Manuel: "Stok Kodu" + "Ü.Maliyeti" varsa
        if (
            in_array('stok kodu', $headerSet) &&
            (in_array('ü.maliyeti', $headerSet) || in_array('maliyet', $headerSet))
        ) {
            return 'manual';
        }

        // Fallback: "Stok Kodu" olan herhangi bir dosya
        if (in_array('stok kodu', $headerSet)) {
            return 'manual';
        }

        return null;
    }

    // ═══════════════════════════════════════════════════════════
    //  TRENDYOL İMPORT
    // ═══════════════════════════════════════════════════════════

    protected function importTrendyol(Collection $rows): array
    {
        $headers = $rows->shift();
        $columnMap = $this->buildColumnMap($headers, self::TRENDYOL_HEADERS);

        foreach ($rows as $index => $row) {
            try {
                $data = $this->mapRow($row, $columnMap);
                $barcode = trim($data['barcode'] ?? '');

                if (empty($barcode)) {
                    $this->skipped++;
                    continue;
                }

                // Görsel URL'leri topla
                $imageUrls = array_values(array_filter([
                    $data['image_url'] ?? null,
                    $data['_image_2'] ?? null,
                    $data['_image_3'] ?? null,
                    $data['_image_4'] ?? null,
                    $data['_image_5'] ?? null,
                    $data['_image_6'] ?? null,
                    $data['_image_7'] ?? null,
                    $data['_image_8'] ?? null,
                ]));

                // Durum normalizasyonu
                $status = $this->normalizeStatus($data['status'] ?? '');

                // Upsert verisi
                $productData = [
                    'user_id'            => $this->userId,
                    'barcode'            => $barcode,
                    'stock_code'         => $this->clean($data['stock_code'] ?? null),
                    'model_code'         => $this->clean($data['model_code'] ?? null),
                    'partner_id'         => $this->clean($data['partner_id'] ?? null),
                    'product_name'       => $this->clean($data['product_name'] ?? null),
                    'color'              => $this->clean($data['color'] ?? null),
                    'size'               => $this->clean($data['size'] ?? null),
                    'dimension'          => $this->clean($data['dimension'] ?? null),
                    'gender'             => $this->clean($data['gender'] ?? null),
                    'brand'              => $this->clean($data['brand'] ?? null),
                    'category_name'      => $this->clean($data['category_name'] ?? null),
                    'description'        => $this->clean($data['description'] ?? null),
                    'market_price'       => $this->parseNumber($data['market_price'] ?? 0),
                    'sale_price'         => $this->parseNumber($data['sale_price'] ?? 0),
                    'buybox_price'       => $this->parseNumber($data['buybox_price'] ?? null),
                    'commission_rate'    => $this->parseNumber($data['commission_rate'] ?? 0),
                    'stock_quantity'     => (int) ($data['stock_quantity'] ?? 0),
                    'vat_rate'           => $this->parseNumber($data['vat_rate'] ?? 10),
                    'otv_rate'           => $this->parseNumber($data['otv_rate'] ?? 0),
                    'desi'               => $this->parseNumber($data['desi'] ?? 0),
                    'status'             => $status,
                    'status_description' => $this->clean($data['status_description'] ?? null),
                    'image_url'          => $imageUrls[0] ?? null,
                    'image_urls'         => !empty($imageUrls) ? $imageUrls : null,
                    'shipping_days'      => !empty($data['shipping_days']) ? (int) $data['shipping_days'] : null,
                    'shipping_type'      => $this->clean($data['shipping_type'] ?? null),
                    'trendyol_link'      => $this->clean($data['trendyol_link'] ?? null),
                    'import_source'      => 'trendyol',
                    'last_synced_at'     => now(),
                ];

                $existing = MpProduct::where('user_id', $this->userId)
                    ->where('barcode', $barcode)
                    ->first();

                if ($existing) {
                    // Mevcut COGS/packaging_cost/cargo_cost değerlerini koruyarak güncelle
                    // (kullanıcının manuel girdiği maliyetleri ezmez)
                    unset($productData['cogs'], $productData['packaging_cost']);
                    if ((float) $existing->cargo_cost > 0 && (float) ($productData['cargo_cost'] ?? 0) <= 0) {
                        unset($productData['cargo_cost']);
                    }
                    $existing->update($productData);
                    $this->updated++;
                } else {
                    MpProduct::create($productData);
                    $this->imported++;
                }
            } catch (\Throwable $e) {
                $this->errors[] = "Satır " . ($index + 2) . ": " . $e->getMessage();
                $this->skipped++;
            }
        }

        return $this->result(null, 'trendyol');
    }

    // ═══════════════════════════════════════════════════════════
    //  MANUEL İMPORT
    // ═══════════════════════════════════════════════════════════

    protected function importManual(Collection $rows): array
    {
        $headers = $rows->shift();
        $columnMap = $this->buildColumnMap($headers, self::MANUAL_HEADERS);

        foreach ($rows as $index => $row) {
            try {
                $data = $this->mapRow($row, $columnMap);
                $stockCode = trim($data['stock_code'] ?? '');

                if (empty($stockCode)) {
                    $this->skipped++;
                    continue;
                }

                // Durum normalizasyonu
                $status = $this->normalizeStatus($data['status'] ?? '');

                $productData = [
                    'user_id'        => $this->userId,
                    'stock_code'     => $stockCode,
                    'barcode'        => $stockCode, // Stok kodu = barkod (Manuel listede barkod ayrı yok)
                    'product_name'   => $this->clean($data['product_name'] ?? null),
                    'category_name'  => $this->clean($data['category_name'] ?? null),
                    'brand'          => $this->clean($data['brand'] ?? null),
                    'stock_quantity' => (int) ($data['stock_quantity'] ?? 0),
                    'variant'        => $this->clean($data['variant'] ?? null),
                    'cargo_cost'     => $this->parseNumber($data['cargo_cost'] ?? 0),
                    'cogs'           => $this->parseNumber($data['cogs'] ?? 0),
                    'pieces'         => (int) ($data['pieces'] ?? 1),
                    'desi'           => $this->parseNumber($data['desi'] ?? 0),
                    'status'         => $status,
                    'platforms'      => $this->clean($data['platforms'] ?? null),
                    'import_source'  => 'manual',
                    'last_synced_at' => now(),
                ];

                // Stock code bazlı upsert (barcode = stock_code olduğu için)
                $existing = MpProduct::where('user_id', $this->userId)
                    ->where(function ($q) use ($stockCode) {
                        $q->where('barcode', $stockCode)
                          ->orWhere('stock_code', $stockCode);
                    })
                    ->first();

                if ($existing) {
                    // Trendyol'dan gelen zengin veriler varsa ezmeyelim
                    $updateData = $productData;
                    // Sadece maliyet ve stok bilgilerini güncelle, ürün adını koruyalım (Trendyol'dan gelmiş olabilir)
                    if ($existing->import_source === 'trendyol' && !empty($existing->product_name)) {
                        unset($updateData['product_name']);
                        unset($updateData['barcode']); // Trendyol barkodunu koruyalım
                    }
                    $existing->update($updateData);
                    $this->updated++;
                } else {
                    MpProduct::create($productData);
                    $this->imported++;
                }
            } catch (\Throwable $e) {
                $this->errors[] = "Satır " . ($index + 2) . ": " . $e->getMessage();
                $this->skipped++;
            }
        }

        return $this->result(null, 'manual');
    }

    // ═══════════════════════════════════════════════════════════
    //  EXCEL EXPORT
    // ═══════════════════════════════════════════════════════════

    /**
     * /excel-export workflow kurallarına uygun export
     */
    public function exportProducts(?array $filters = []): StreamedResponse
    {
        $query = MpProduct::where('user_id', $this->userId);

        // Filtreleri uygula
        if (!empty($filters['search']))      $query->search($filters['search']);
        if (!empty($filters['status']))       $query->byStatus($filters['status']);
        if (!empty($filters['category']))     $query->byCategory($filters['category']);
        if (!empty($filters['brand']))        $query->byBrand($filters['brand']);
        if (!empty($filters['stock_level']))  $query->byStockLevel($filters['stock_level']);

        $products = $query->orderBy('product_name')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sanitizeSheetName('Ürün Listesi'));

        // Header satırı
        $exportHeaders = [
            'Barkod', 'Stok Kodu', 'Ürün Adı', 'Kategori', 'Marka',
            'Satış Fiyatı', 'Birim Maliyet (COGS)', 'Ambalaj Gideri', 'Kargo Maliyeti',
            'KDV Oranı (%)', 'Komisyon Oranı (%)', 'Stok Adedi', 'Desi',
            'Durum', 'Yayın Platformları', 'Model Kodu', 'Renk', 'Beden',
        ];

        foreach ($exportHeaders as $colIdx => $header) {
            $cell = Coordinate::stringFromColumnIndex($colIdx + 1) . '1';
            $sheet->setCellValueExplicit($cell, $this->cleanString($header), DataType::TYPE_STRING);
        }

        // Veri satırları
        $rowNum = 2;
        foreach ($products as $product) {
            $values = [
                [$product->barcode, DataType::TYPE_STRING],
                [$product->stock_code, DataType::TYPE_STRING],
                [$product->product_name, DataType::TYPE_STRING],
                [$product->category_name, DataType::TYPE_STRING],
                [$product->brand, DataType::TYPE_STRING],
                [$product->sale_price, DataType::TYPE_NUMERIC],
                [$product->cogs, DataType::TYPE_NUMERIC],
                [$product->packaging_cost, DataType::TYPE_NUMERIC],
                [$product->cargo_cost, DataType::TYPE_NUMERIC],
                [$product->vat_rate, DataType::TYPE_NUMERIC],
                [$product->commission_rate, DataType::TYPE_NUMERIC],
                [$product->stock_quantity, DataType::TYPE_NUMERIC],
                [$product->desi, DataType::TYPE_NUMERIC],
                [$product->status_label, DataType::TYPE_STRING],
                [$product->platforms, DataType::TYPE_STRING],
                [$product->model_code, DataType::TYPE_STRING],
                [$product->color, DataType::TYPE_STRING],
                [$product->size, DataType::TYPE_STRING],
            ];

            foreach ($values as $colIdx => [$value, $type]) {
                $cell = Coordinate::stringFromColumnIndex($colIdx + 1) . $rowNum;
                $cleanValue = $type === DataType::TYPE_STRING
                    ? $this->cleanString($value)
                    : $value;
                $sheet->setCellValueExplicit($cell, $cleanValue, $type);
            }
            $rowNum++;
        }

        // Header stili
        $lastCol = Coordinate::stringFromColumnIndex(count($exportHeaders));
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);

        // Auto-fit sütun genişlikleri
        foreach (range(1, count($exportHeaders)) as $colIdx) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIdx))
                ->setAutoSize(true);
        }

        $filename = 'Urun_Listesi_' . date('Y-m-d_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  YARDIMCI METOTLAR
    // ═══════════════════════════════════════════════════════════

    /**
     * Excel dosyasını oku — MarketplaceImportService ile aynı kanıtlanmış yaklaşım
     * toArray() + RichText için getCalculatedValue() kullanır
     */
    protected function readExcel(UploadedFile $file): Collection
    {
        try {
            // Dosya uzantısına göre reader seç
            $extension = strtolower($file->getClientOriginalExtension());
            $readerType = ($extension === 'xls') ? 'Xls' : 'Xlsx';

            $reader = IOFactory::createReader($readerType);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            $spreadsheet = $reader->load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();

            // toArray: hesaplanmış değerleri al (RichText otomatik çözülür)
            $data = $sheet->toArray(null, true, true, false);

            if (empty($data)) {
                Log::warning('MpProductImportService.readExcel: toArray boş döndü');
                return collect();
            }

            // İlk satırı header olarak al, geri kalanını veri olarak döndür
            $rows = collect();
            foreach ($data as $row) {
                // Her hücre değerini string'e dönüştür (RichText artıkları için)
                $cleanRow = array_map(function ($val) {
                    if ($val instanceof RichText) {
                        return $val->getPlainText();
                    }
                    return $val;
                }, $row);

                // Tamamen boş satırları atla
                if (!empty(array_filter($cleanRow, fn($v) => $v !== null && $v !== ''))) {
                    $rows->push($cleanRow);
                }
            }

            return $rows;
        } catch (\Throwable $e) {
            Log::error('MpProductImportService.readExcel error', [
                'error' => $e->getMessage(),
                'file'  => $file->getClientOriginalName(),
                'trace' => $e->getTraceAsString(),
            ]);
            return collect();
        }
    }

    /**
     * Header satırından sütun eşleştirme haritası oluştur
     */
    protected function buildColumnMap(array $headers, array $mapping): array
    {
        $map = [];
        foreach ($headers as $colIdx => $header) {
            $headerClean = mb_strtolower(trim($header ?? ''));
            foreach ($mapping as $excelName => $dbField) {
                if ($headerClean === mb_strtolower($excelName)) {
                    $map[$colIdx] = $dbField;
                    break;
                }
            }
        }
        return $map;
    }

    /**
     * Tek satırı sütun eşleştirmesine göre dönüştür
     */
    protected function mapRow(array $row, array $columnMap): array
    {
        $data = [];
        foreach ($columnMap as $colIdx => $field) {
            $data[$field] = $row[$colIdx] ?? null;
        }
        return $data;
    }

    /**
     * Durum normalizasyonu
     */
    protected function normalizeStatus(?string $status): string
    {
        if (empty($status)) return 'active';

        $status = mb_strtolower(trim($status));
        return match (true) {
            str_contains($status, 'satış')    => 'active',
            str_contains($status, 'tüken')    => 'out_of_stock',
            str_contains($status, 'onay')     => 'pending',
            str_contains($status, 'bekle')    => 'suspended',
            str_contains($status, 'pasif')    => 'suspended',
            str_contains($status, 'aktif')    => 'active',
            default                           => 'active',
        };
    }

    /**
     * Türkçe formattaki sayıyı parse et: "1.049,90" → 1049.90
     */
    protected function parseNumber($value): float
    {
        if ($value === null || $value === '') return 0;
        if (is_numeric($value)) return (float) $value;

        $str = (string) $value;
        $str = str_replace(' ', '', $str);
        $str = str_replace('%', '', $str);

        // Türkçe format: nokta = binlik, virgül = ondalık
        if (preg_match('/^\d{1,3}(\.\d{3})*(,\d+)?$/', $str)) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        } else {
            $str = str_replace(',', '.', $str);
        }

        return is_numeric($str) ? (float) $str : 0;
    }

    /**
     * UTF-8 ve XML kontrol karakteri temizleme (/excel-export workflow)
     */
    protected function cleanString($value): ?string
    {
        if ($value === null) return null;
        $value = (string) $value;
        if ($value === '') return null;

        // Windows-1254 → UTF-8
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1254');
        }

        // Kontrol karakterlerini kaldır (tab ve newline hariç)
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

        // XML-safe karakterler
        $value = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $value);

        return trim($value) ?: null;
    }

    /**
     * Temizleme kısayolu (alias)
     */
    protected function clean($value): ?string
    {
        return $this->cleanString($value);
    }

    /**
     * Sheet ismi temizleme (/excel-export workflow)
     */
    protected function sanitizeSheetName(string $name): string
    {
        $name = str_replace([':', '\\', '/', '?', '*', '[', ']'], '', $name);
        return mb_substr($name, 0, 31);
    }

    /**
     * Sonuç dizisi oluştur
     */
    protected function result(?string $error = null, ?string $type = null): array
    {
        return [
            'success'  => $error === null,
            'type'     => $type,
            'imported' => $this->imported,
            'updated'  => $this->updated,
            'skipped'  => $this->skipped,
            'errors'   => $this->errors,
            'message'  => $error ?? sprintf(
                '%d yeni ürün eklendi, %d ürün güncellendi, %d satır atlandı.',
                $this->imported,
                $this->updated,
                $this->skipped
            ),
        ];
    }
}
