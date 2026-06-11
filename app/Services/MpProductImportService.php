<?php

namespace App\Services;

use App\Models\MpProduct;
use App\Services\MpProductChangeLogger;
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
        'Barkod'          => 'barcode',
        'Stok Kodu'       => 'stock_code',
        'Tedarikçi Stok Kodu' => 'stock_code',
        'Ürün Adı'        => 'product_name',
        'Kategori'        => 'category_name',
        'Marka'           => 'brand',
        'Stok'            => 'stock_quantity',
        'Stok Adedi'      => 'stock_quantity',
        'Kritik Stok Eşiği' => 'critical_stock_threshold',
        'Variyant'        => 'variant',
        'Satış Fiyatı'    => 'sale_price',
        'Piyasa Fiyatı'   => 'market_price',
        'Kargo Maliyeti'  => 'cargo_cost',
        'Ekstra Gider'    => 'extra_cost_fixed',
        'Ekstra Gider (%)' => 'extra_cost_percentage',
        'Maliyet'         => 'cogs',
        'MF Fiyatı'       => 'cogs',
        'Ü.Maliyeti'      => 'cogs',
        'Birim Maliyet (COGS)' => 'cogs',
        'Ambalaj Gideri'  => 'packaging_cost',
        'KDV Oranı (%)'   => 'vat_rate',
        'Maliyet KDV Oranı' => 'cost_vat_rate',
        'Maliyet KDV Oranı (%)' => 'cost_vat_rate',
        'Komisyon Oranı (%)' => 'commission_rate',
        'Parça'           => 'pieces',
        'Desi'            => 'desi',
        'İade Oranı'      => 'return_rate',
        'İade Oranı (%)'  => 'return_rate',
        'Teslimat Tipi'   => 'fast_delivery_type',
        'Durum'           => 'status',
        'Yayında'         => 'platforms',
        'Yayın Platformları' => 'platforms',
        'Model Kodu'      => 'model_code',
        'Renk'            => 'color',
        'Beden'           => 'size',
    ];

    protected const COST_UPDATE_HEADERS = [
        'Stok Kodu' => 'stock_code',
        'Stok kodu' => 'stock_code',
        'Tedarikçi Stok Kodu' => 'stock_code',
        'Satıcı Stok Kodu' => 'stock_code',
        'Ürün Kodu' => 'stock_code',
        'Barkod' => 'barcode',
        'Barcode' => 'barcode',
        'Maliyet' => 'cogs',
        'Ü.Maliyeti' => 'cogs',
        'Ürün Maliyeti' => 'cogs',
        'Birim Maliyet' => 'cogs',
        'Birim Maliyet (COGS)' => 'cogs',
        'COGS' => 'cogs',
        'MF Fiyatı' => 'cogs',
        'Mf Fiyatı' => 'cogs',
        'M.F Fiyatı' => 'cogs',
        'MF Fiyati' => 'cogs',
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
        $this->resetState();

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

    public function importCostUpdates(UploadedFile $file, bool $applyZeroValues = false): array
    {
        $this->resetState();

        try {
            $rows = $this->readExcel($file);
        } catch (\Throwable $e) {
            return $this->result('Excel dosyası okunamadı: ' . $e->getMessage(), 'cost_update');
        }

        if ($rows->isEmpty()) {
            return $this->result('Dosya boş veya okunamadı. Lütfen .xlsx formatında olduğundan emin olun.', 'cost_update');
        }

        $headerMatch = $this->findHeaderRow($rows, self::COST_UPDATE_HEADERS);

        if ($headerMatch === null) {
            return $this->result(
                'Maliyet güncelleme formatı tanınamadı. Dosyada Stok Kodu/Barkod ve Maliyet/MF Fiyatı sütunları olmalı.',
                'cost_update'
            );
        }

        [$headerIndex, $columnMap] = $headerMatch;
        $matched = 0;
        $notFound = 0;
        $blankCost = 0;
        $zeroCost = 0;

        foreach ($rows->slice($headerIndex + 1)->values() as $index => $row) {
            $lineNumber = $headerIndex + $index + 2;

            try {
                $data = $this->mapRow($row, $columnMap);
                $stockCode = trim((string) ($this->clean($data['stock_code'] ?? null) ?? ''));
                $barcode = trim((string) ($this->clean($data['barcode'] ?? null) ?? ''));
                $rawCost = $data['cogs'] ?? null;

                if ($stockCode === '' && $barcode === '') {
                    $this->skipped++;
                    continue;
                }

                if ($rawCost === null || trim((string) $rawCost) === '') {
                    $blankCost++;
                    $this->skipped++;
                    continue;
                }

                $cost = $this->parseNumber($rawCost);

                if ($cost <= 0 && !$applyZeroValues) {
                    $zeroCost++;
                    $this->skipped++;
                    continue;
                }

                $product = $this->findProductByStockCodeOrBarcode($stockCode, $barcode);

                if (!$product) {
                    $notFound++;
                    $this->skipped++;

                    if ($notFound <= 10) {
                        $identifier = $stockCode !== '' ? $stockCode : $barcode;
                        $this->errors[] = "Satır {$lineNumber}: Ürün bulunamadı ({$identifier}).";
                    }

                    continue;
                }

                $logger = app(MpProductChangeLogger::class);
                $beforeSnapshot = $logger->productSnapshot($product);
                $product->update([
                    'cogs' => round($cost, 2),
                ]);
                $logger->logProductSnapshotChanges(
                    $product->fresh() ?: $product,
                    $beforeSnapshot,
                    'cost_excel_update',
                    Auth::id(),
                    "Maliyet Excel satırı {$lineNumber}"
                );

                $matched++;
                $this->updated++;
            } catch (\Throwable $e) {
                $this->errors[] = "Satır {$lineNumber}: " . $e->getMessage();
                $this->skipped++;
            }
        }

        if ($notFound > 10) {
            $this->errors[] = '... ve ' . ($notFound - 10) . ' bulunamayan ürün daha.';
        }

        return [
            'success' => true,
            'type' => 'cost_update',
            'imported' => 0,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'matched' => $matched,
            'not_found' => $notFound,
            'blank_cost' => $blankCost,
            'zero_cost' => $zeroCost,
            'errors' => $this->errors,
            'message' => sprintf(
                '%d ürünün maliyeti güncellendi, %d satır atlandı.',
                $this->updated,
                $this->skipped
            ),
        ];
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
                    $logger = app(MpProductChangeLogger::class);
                    $beforeSnapshot = $logger->productSnapshot($existing);
                    $existing->update($productData);
                    $freshProduct = $existing->fresh();

                    if ($freshProduct) {
                        $logger->logProductSnapshotChanges(
                            $freshProduct,
                            $beforeSnapshot,
                            'trendyol_import',
                            Auth::id(),
                            'Trendyol Excel import'
                        );
                        app(\App\Services\NotificationCenterService::class)->syncProductStockAlert($freshProduct);
                    }

                    $this->updated++;
                } else {
                    $product = MpProduct::create($productData);
                    app(MpProductChangeLogger::class)->logProductCreated(
                        $product,
                        'trendyol_import',
                        Auth::id(),
                        'Trendyol Excel import ile yeni ürün'
                    );
                    app(\App\Services\NotificationCenterService::class)->syncProductStockAlert($product);
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
                    'barcode'        => $this->clean($data['barcode'] ?? null) ?: $stockCode,
                    'model_code'     => $this->clean($data['model_code'] ?? null),
                    'product_name'   => $this->clean($data['product_name'] ?? null),
                    'category_name'  => $this->clean($data['category_name'] ?? null),
                    'brand'          => $this->clean($data['brand'] ?? null),
                    'color'          => $this->clean($data['color'] ?? null),
                    'size'           => $this->clean($data['size'] ?? null),
                    'stock_quantity' => (int) ($data['stock_quantity'] ?? 0),
                    'critical_stock_threshold' => filled($data['critical_stock_threshold'] ?? null) ? (int) $data['critical_stock_threshold'] : null,
                    'variant'        => $this->clean($data['variant'] ?? null),
                    'sale_price'     => $this->parseNumber($data['sale_price'] ?? 0),
                    'market_price'   => $this->parseNumber($data['market_price'] ?? 0),
                    'cargo_cost'     => $this->parseNumber($data['cargo_cost'] ?? 0),
                    'extra_cost_fixed' => $this->parseNumber($data['extra_cost_fixed'] ?? 0),
                    'extra_cost_percentage' => $this->parseNumber($data['extra_cost_percentage'] ?? 0),
                    'cogs'           => $this->parseNumber($data['cogs'] ?? 0),
                    'packaging_cost' => $this->parseNumber($data['packaging_cost'] ?? 0),
                    'vat_rate'       => filled($data['vat_rate'] ?? null) ? $this->parseNumber($data['vat_rate']) : 10,
                    'cost_vat_rate'  => filled($data['cost_vat_rate'] ?? null) ? $this->parseNumber($data['cost_vat_rate']) : null,
                    'commission_rate' => filled($data['commission_rate'] ?? null) ? $this->parseNumber($data['commission_rate']) : 0,
                    'pieces'         => (int) ($data['pieces'] ?? 1),
                    'desi'           => $this->parseNumber($data['desi'] ?? 0),
                    'return_rate'    => filled($data['return_rate'] ?? null) ? $this->parseNumber($data['return_rate']) : null,
                    'return_rate_source' => filled($data['return_rate'] ?? null) ? 'manual_import' : null,
                    'return_rate_calculated_at' => filled($data['return_rate'] ?? null) ? now() : null,
                    'fast_delivery_type' => $this->clean($data['fast_delivery_type'] ?? null),
                    'status'         => $status,
                    'platforms'      => $this->clean($data['platforms'] ?? null),
                    'import_source'  => 'manual',
                    'last_synced_at' => now(),
                ];

                foreach ([
                    'sale_price',
                    'market_price',
                    'packaging_cost',
                    'critical_stock_threshold',
                    'extra_cost_fixed',
                    'extra_cost_percentage',
                    'vat_rate',
                    'cost_vat_rate',
                    'commission_rate',
                    'return_rate',
                    'fast_delivery_type',
                ] as $optionalNumericField) {
                    if (!array_key_exists($optionalNumericField, $data)) {
                        unset($productData[$optionalNumericField]);
                    }
                }

                if (!array_key_exists('return_rate', $data)) {
                    unset($productData['return_rate_source'], $productData['return_rate_calculated_at']);
                }

                foreach (['model_code', 'color', 'size'] as $optionalStringField) {
                    if (!array_key_exists($optionalStringField, $data)) {
                        unset($productData[$optionalStringField]);
                    }
                }

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
                    $logger = app(MpProductChangeLogger::class);
                    $beforeSnapshot = $logger->productSnapshot($existing);
                    $existing->update($updateData);
                    $freshProduct = $existing->fresh();

                    if ($freshProduct) {
                        $logger->logProductSnapshotChanges(
                            $freshProduct,
                            $beforeSnapshot,
                            'manual_excel_import',
                            Auth::id(),
                            'Manuel Excel ürün import'
                        );
                        app(\App\Services\NotificationCenterService::class)->syncProductStockAlert($freshProduct);
                    }

                    $this->updated++;
                } else {
                    $product = MpProduct::create($productData);
                    app(MpProductChangeLogger::class)->logProductCreated(
                        $product,
                        'manual_excel_import',
                        Auth::id(),
                        'Manuel Excel import ile yeni ürün'
                    );
                    app(\App\Services\NotificationCenterService::class)->syncProductStockAlert($product);
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
        if (is_numeric($filters['sale_price_min'] ?? null)) $query->where('sale_price', '>=', (float) $filters['sale_price_min']);
        if (is_numeric($filters['sale_price_max'] ?? null)) $query->where('sale_price', '<=', (float) $filters['sale_price_max']);
        if (is_numeric($filters['cost_min'] ?? null))       $query->where('cogs', '>=', (float) $filters['cost_min']);
        if (is_numeric($filters['cost_max'] ?? null))       $query->where('cogs', '<=', (float) $filters['cost_max']);
        if (is_numeric($filters['stock_min'] ?? null))      $query->where('stock_quantity', '>=', (int) $filters['stock_min']);
        if (is_numeric($filters['stock_max'] ?? null))      $query->where('stock_quantity', '<=', (int) $filters['stock_max']);
        if (is_numeric($filters['desi_min'] ?? null))       $query->where('desi', '>=', (float) $filters['desi_min']);
        if (is_numeric($filters['desi_max'] ?? null))       $query->where('desi', '<=', (float) $filters['desi_max']);
        if (is_numeric($filters['return_rate_min'] ?? null)) $query->where('return_rate', '>=', (float) $filters['return_rate_min']);
        if (is_numeric($filters['return_rate_max'] ?? null)) $query->where('return_rate', '<=', (float) $filters['return_rate_max']);

        $products = $query->orderBy('product_name')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sanitizeSheetName('Ürün Listesi'));

        // Header satırı
        $exportHeaders = [
            'Barkod', 'Stok Kodu', 'Ürün Adı', 'Kategori', 'Marka',
            'Satış Fiyatı', 'Birim Maliyet (COGS)', 'Ambalaj Gideri', 'Kargo Maliyeti',
            'Ekstra Gider', 'Ekstra Gider (%)', 'KDV Oranı (%)', 'Maliyet KDV Oranı (%)',
            'Komisyon Oranı (%)', 'Stok Adedi', 'Kritik Stok Eşiği', 'Desi', 'İade Oranı (%)',
            'Teslimat Tipi', 'Durum', 'Yayın Platformları', 'Model Kodu', 'Renk', 'Beden',
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
                [$product->extra_cost_fixed, DataType::TYPE_NUMERIC],
                [$product->extra_cost_percentage, DataType::TYPE_NUMERIC],
                [$product->vat_rate, DataType::TYPE_NUMERIC],
                [$product->cost_vat_rate, DataType::TYPE_NUMERIC],
                [$product->commission_rate, DataType::TYPE_NUMERIC],
                [$product->stock_quantity, DataType::TYPE_NUMERIC],
                [$product->critical_stock_threshold, DataType::TYPE_NUMERIC],
                [$product->desi, DataType::TYPE_NUMERIC],
                [$product->return_rate, DataType::TYPE_NUMERIC],
                [$product->fast_delivery_type, DataType::TYPE_STRING],
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
        $normalizedMapping = [];
        foreach ($mapping as $excelName => $dbField) {
            $normalizedMapping[$this->normalizeHeader($excelName)] = $dbField;
        }

        foreach ($headers as $colIdx => $header) {
            $headerClean = $this->normalizeHeader($header);
            if (isset($normalizedMapping[$headerClean])) {
                $map[$colIdx] = $normalizedMapping[$headerClean];
            }
        }
        return $map;
    }

    protected function findHeaderRow(Collection $rows, array $mapping): ?array
    {
        foreach ($rows->values() as $index => $row) {
            $columnMap = $this->buildColumnMap((array) $row, $mapping);
            $fields = array_values($columnMap);

            if (in_array('cogs', $fields, true)
                && (in_array('stock_code', $fields, true) || in_array('barcode', $fields, true))) {
                return [$index, $columnMap];
            }
        }

        return null;
    }

    protected function findProductByStockCodeOrBarcode(string $stockCode, string $barcode): ?MpProduct
    {
        return MpProduct::query()
            ->where('user_id', $this->userId)
            ->where(function ($query) use ($stockCode, $barcode) {
                if ($stockCode !== '') {
                    $query->where('stock_code', $stockCode)
                        ->orWhere('barcode', $stockCode);
                }

                if ($barcode !== '') {
                    $query->orWhere('barcode', $barcode)
                        ->orWhere('stock_code', $barcode);
                }
            })
            ->first();
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

    protected function normalizeHeader($header): string
    {
        $header = $this->cleanString($header) ?? '';
        $header = mb_strtolower(trim($header), 'UTF-8');
        $header = preg_replace('/\s+/u', ' ', $header);

        return $header ?? '';
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

    protected function resetState(): void
    {
        $this->imported = 0;
        $this->updated = 0;
        $this->skipped = 0;
        $this->errors = [];
    }
}
