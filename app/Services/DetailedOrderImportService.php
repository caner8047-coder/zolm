<?php

namespace App\Services;

use App\Models\MpOperationalOrder;
use App\Models\MpOperationalOrderItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Carbon\Carbon;

class DetailedOrderImportService
{
    protected ExcelService $excelService;

    // Sütun eşleştirme sözlüğü
    protected array $columnAliases = [
        // ── Sipariş Tanımlayıcıları ──
        'order_number'      => ['Sipariş No', 'Siparis No', 'Order No', 'Order Number', 'Sipariş Numarası'],
        'package_number'    => ['Paket No', 'Package No', 'Paket Numarası'],

        // ── Tarihler ──
        'order_date'        => ['Sipariş Tarihi', 'Siparis Tarihi', 'Order Date', 'Tarih'],
        'delivery_date'     => ['Teslim Tarihi', 'Delivery Date', 'Teslimat Tarihi'],
        'deadline_date'     => ['Termin Süresinin Bittiği Tarih', 'Termin Tarihi', 'Son Tarih'],
        'cargo_delivery_date' => ['Kargoya Teslim Tarihi', 'Kargoya Veriliş Tarihi'],
        'invoice_date'      => ['Fatura Tarihi'],

        // ── Müşteri Bilgileri ──
        'customer_name'     => ['Müşteri', 'Müşteri Adı', 'Müşteri Adresi Ad Soyad', 'Alıcı Adı', 'Müşteri İsim', 'Alıcı'],
        'customer_city'     => ['Şehir', 'İl', 'Müşteri Şehir', 'Teslimat İli', 'Fatura İli', 'Teslimat İl', 'Fatura İl'],
        'customer_district' => ['İlçe', 'Müşteri İlçe', 'Teslimat İlçesi', 'Fatura İlçesi', 'Teslimat İlçe', 'Fatura İlçe'],
        'customer_address'  => ['Alıcı Adresi', 'Teslimat Adresi', 'Adres', 'Müşteri Adresi', 'Fatura Adres'],
        'customer_phone'    => ['Müşteri Telefon No', 'Telefon', 'Cep Telefonu Numarası', 'Müşteri Telefon'],
        'email'             => ['E-Posta', 'Email', 'E-Mail', 'Mail Adresi'],
        'customer_age'      => ['Yaş', 'Müşteri Yaş'],
        'customer_gender'   => ['Cinsiyet', 'Müşteri Cinsiyet'],
        'customer_order_count' => ['Müşteri Sipariş Adedi', 'Sipariş Adedi'],
        'country'           => ['Ülke', 'Country'],

        // ── Fatura Bilgileri ──
        'billing_address'   => ['Fatura Adresi', 'Fatura Adres'],
        'billing_name'      => ['Alıcı - Fatura Adresi', 'Fatura Alıcı'],
        'company_name'      => ['Şirket İsmi', 'Firma Adı', 'Şirket Adı', 'Fatura Ünvanı'],
        'tax_office'        => ['Vergi Dairesi', 'Fatura Vergi Dairesi'],
        'tax_number'        => ['Vergi Kimlik Numarası', 'TC / VKN', 'TCKN', 'Fatura Vergi No'],
        'invoice_number'    => ['Fatura No', 'Fatura Numarası'],
        'is_invoiced'       => ['Fatura'],
        'is_corporate_invoice' => ['Kurumsal Faturalı Sipariş', 'Kurumsal Fatura'],

        // ── Lojistik ──
        'cargo_company'     => ['Kargo Firması', 'Kargo', 'Kargo Şirketi', 'Taşıyıcı Şirket'],
        'cargo_partner'     => ['Kargo Partner İsmi', 'Kargo Partner'],
        'tracking_number'   => ['Teslimat Numarası', 'Kargo Takip No', 'Gönderi Numarası', 'Takip Numarası'],
        'cargo_code'        => ['Kargo Kodu'],
        'status'            => ['Sipariş Statüsü', 'Sipariş Durumu', 'Durum', 'Sipariş Satır Durumu', 'Paket Durumu'],
        'alt_delivery_status' => ['Alternatif Teslimat Statüsü', 'Alt. Teslimat Durumu'],
        'second_delivery_status' => ['2.Teslimat Paketi Statüsü', '2. Teslimat Durumu'],
        'second_tracking_number' => ['2.Teslimat Takip Numarası', '2. Teslimat Takip No'],

        // ── Ürün Bilgileri ──
        'barcode'           => ['Barkod', 'Ürün Barkodu', 'Barcode', 'Satıcı Barkodu'],
        'stock_code'        => ['Model Kodu', 'Stok Kodu', 'Tedarikçi Stok Kodu', 'Satıcı Ürün Kodu'],
        'product_name'      => ['Ürün Adı', 'Ürün', 'Urun Adi', 'Ürün Adı / Açıklama'],
        'brand'             => ['Marka', 'Brand'],
        'quantity'          => ['Adet', 'Miktar', 'Ürün Adedi'],

        // ── Fiyatlandırma ──
        'unit_price'        => ['Birim Fiyatı', 'Birim Fiyat', 'Liste Fiyatı'],
        'sale_price'        => ['Satış Tutarı', 'Sipariş Tutarı', 'Sale Price', 'Satış Fiyatı', 'İndirimli Fiyat'],
        'discount_amount'   => ['İndirim Tutarı', 'Müşteri İndirimi', 'İndirim', 'Kampanya İndirimi'],
        'trendyol_discount' => ['Trendyol İndirim Tutarı', 'Platform İndirimi'],
        'billable_amount'   => ['Faturalanacak Tutar', 'Faturalanan Tutar'],
        'commission_rate'   => ['Komisyon Oranı', 'Komisyon (%)'],
        'boutique_number'   => ['Butik Numarası', 'Butik No'],

        // ── Desi & Kargo Maliyet ──
        'cargo_desi'        => ['Kargodan alınan desi', 'Kargo Desi', 'Desi'],
        'calculated_desi'   => ['Hesapladığım desi', 'Hesaplanan Desi'],
        'invoiced_cargo_amount' => ['Faturalanan Kargo Tutarı', 'Kargo Fatura Tutarı'],
    ];

    public function __construct(ExcelService $excelService)
    {
        $this->excelService = $excelService;
    }

    /**
     * ChunkReadFilter kullanarak RAM dostu şekilde Excel'i işler.
     */
    public function importDetailedOrders(string $filePath): array
    {
        $stats = ['imported_master' => 0, 'imported_items' => 0, 'updated_master' => 0, 'updated_items' => 0, 'errors' => []];

        try {
            $chunkSize = 1000;
            $startRow = 2; // Trendyol operasyonel exceli için başlık satırı tahmin edilemez, genelde 1 veya 2

            // Önce başlıkları tespit et (İlk 5 satırı tara)
            $headersInfo = $this->detectHeaders($filePath);
            if (!$headersInfo) {
                throw new \Exception("Excel formül yapısı veya başlık satırı (Örn: Sipariş No) bulunamadı.");
            }

            $headerRowIndex = $headersInfo['rowIndex'];
            $headerMap = $headersInfo['map']; // excelColumnIndex => dbField

            $inputFileType = IOFactory::identify($filePath);
            $reader = IOFactory::createReader($inputFileType);
            $reader->setReadDataOnly(true);

            // ÖNCELİKLE: Dosyanın gerçek satır sayısını öğren (filtre OLMADAN)
            $tempSpreadsheet = $reader->load($filePath);
            $highestRow = $tempSpreadsheet->getActiveSheet()->getHighestDataRow();
            $tempSpreadsheet->disconnectWorksheets();
            unset($tempSpreadsheet);
            gc_collect_cycles();

            Log::info("DetailedOrderImport: File has $highestRow rows, header at row $headerRowIndex", ['file' => basename($filePath)]);

            $chunkSize = 1000;

            for ($currentRow = $headerRowIndex + 1; $currentRow <= $highestRow; $currentRow += $chunkSize) {
                $chunkFilter = new \App\Services\ChunkReadFilter($currentRow, $chunkSize);
                $reader->setReadFilter($chunkFilter);

                $spreadsheet = $reader->load($filePath);
                $worksheet = $spreadsheet->getActiveSheet();

                $chunkData = [];
                $endRow = min($currentRow + $chunkSize - 1, $highestRow);

                foreach ($worksheet->getRowIterator($currentRow, $endRow) as $row) {
                    $itemData = [];
                    foreach ($row->getCellIterator() as $colIndex => $cell) {
                        $val = $cell->getValue();
                        if ($val instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                            $val = $val->getPlainText();
                        }
                        
                        $mappedField = $headerMap[$colIndex] ?? null;
                        if ($mappedField && $val !== null) {
                            $itemData[$mappedField] = $this->excelService->cleanString($val);
                        }
                    }

                    if (!empty($itemData['order_number'])) {
                        $chunkData[] = $itemData;
                    }
                }

                if (!empty($chunkData)) {
                    $this->processChunkGrouped($chunkData, $stats);
                }

                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                gc_collect_cycles();
            }

        } catch (\Exception $e) {
            Log::error('DetailedOrderImportError', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $stats['errors'][] = $e->getMessage();
        }

        return $stats;
    }

    /**
     * İlk 5 satırı tarayarak başlıkları bulur
     */
    protected function detectHeaders(string $filePath): ?array
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $chunkFilter = new \App\Services\ChunkReadFilter(1, 10); // İlk 10 satırı oku
        $reader->setReadFilter($chunkFilter);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        foreach ($worksheet->getRowIterator(1, 10) as $row) {
            $rowVals = [];
            foreach ($row->getCellIterator() as $colIndex => $cell) {
                $val = $cell->getValue();
                if ($val instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                    $val = $val->getPlainText();
                }
                if ($val) {
                    $rowVals[$colIndex] = $this->excelService->cleanString($val);
                }
            }

            // Eğer satırda 'Sipariş No' gibi kilit bir başlık geçiyorsa
            $hasOrderNo = false;
            foreach ($rowVals as $val) {
                $lowerVal = mb_strtolower(trim($val));
                foreach ($this->columnAliases['order_number'] as $alias) {
                    if ($lowerVal === mb_strtolower($alias)) {
                        $hasOrderNo = true;
                        break 2;
                    }
                }
            }

            if ($hasOrderNo) {
                // Eşleştirme yap
                $map = [];
                foreach ($rowVals as $colIndex => $headerText) {
                    $lowerHeader = mb_strtolower(trim($headerText));
                    foreach ($this->columnAliases as $dbField => $possibleNames) {
                        foreach ($possibleNames as $pName) {
                            if ($lowerHeader === mb_strtolower($pName)) {
                                $map[$colIndex] = $dbField;
                                break 2;
                            }
                        }
                    }
                }
                return ['rowIndex' => $row->getRowIndex(), 'map' => $map];
            }
        }
        return null;
    }

    /**
     * Okunan satırları Order bazında gruplayıp Master-Detail olarak DB'ye yazar.
     */
    protected function processChunkGrouped(array $rows, array &$stats): void
    {
        // order_number'a göre grupla
        $grouped = collect($rows)->groupBy('order_number');

        DB::beginTransaction();
        try {
            foreach ($grouped as $orderNumber => $items) {
                // Master order verilerini ilk satırdan al (ortak veriler)
                $firstRow = $items->first();
                
                $totalGross = $items->sum(function($i) { return $this->parseNumber($i['sale_price'] ?? 0); });
                $totalDiscount = $items->sum(function($i) { return $this->parseNumber($i['discount_amount'] ?? 0); });

                // Kargo firması: 'Kargo Partner İsmi' varsa onu tercih et, yoksa 'Kargo Firması'nı kullan
                $cargoCompany = $firstRow['cargo_partner'] ?? $firstRow['cargo_company'] ?? null;

                $masterData = [
                    'order_number'       => $orderNumber,
                    'package_number'     => $firstRow['package_number'] ?? null,
                    // Tarihler
                    'order_date'         => $this->parseDate($firstRow['order_date'] ?? null),
                    'delivery_date'      => $this->parseDate($firstRow['delivery_date'] ?? null),
                    'deadline_date'      => $this->parseDate($firstRow['deadline_date'] ?? null),
                    'cargo_delivery_date'=> $this->parseDate($firstRow['cargo_delivery_date'] ?? null),
                    'invoice_date'       => $this->parseDate($firstRow['invoice_date'] ?? null),
                    // Müşteri
                    'customer_name'      => $firstRow['customer_name'] ?? null,
                    'customer_city'      => $firstRow['customer_city'] ?? null,
                    'customer_district'  => $firstRow['customer_district'] ?? null,
                    'customer_address'   => $firstRow['customer_address'] ?? null,
                    'customer_phone'     => $firstRow['customer_phone'] ?? null,
                    'email'              => $firstRow['email'] ?? null,
                    'customer_age'       => $firstRow['customer_age'] ?? null,
                    'customer_gender'    => $firstRow['customer_gender'] ?? null,
                    'customer_order_count' => $firstRow['customer_order_count'] ?? null,
                    'country'            => $firstRow['country'] ?? null,
                    // Fatura
                    'billing_address'    => $firstRow['billing_address'] ?? null,
                    'billing_name'       => $firstRow['billing_name'] ?? null,
                    'company_name'       => $firstRow['company_name'] ?? null,
                    'tax_office'         => $firstRow['tax_office'] ?? null,
                    'tax_number'         => $firstRow['tax_number'] ?? null,
                    'invoice_number'     => $firstRow['invoice_number'] ?? null,
                    'is_invoiced'        => $firstRow['is_invoiced'] ?? null,
                    'is_corporate_invoice' => $firstRow['is_corporate_invoice'] ?? null,
                    // Lojistik
                    'cargo_company'      => $cargoCompany,
                    'tracking_number'    => $firstRow['tracking_number'] ?? null,
                    'cargo_code'         => $firstRow['cargo_code'] ?? null,
                    'status'             => $firstRow['status'] ?? null,
                    'alt_delivery_status' => $firstRow['alt_delivery_status'] ?? null,
                    'second_delivery_status' => $firstRow['second_delivery_status'] ?? null,
                    'second_tracking_number' => $firstRow['second_tracking_number'] ?? null,
                    // Toplamlar
                    'total_gross_amount' => $totalGross,
                    'total_discount'     => $totalDiscount,
                ];

                $masterOrder = MpOperationalOrder::where('order_number', $orderNumber)->first();
                
                if ($masterOrder) {
                    $masterOrder->update($masterData);
                    $stats['updated_master']++;
                } else {
                    $masterOrder = MpOperationalOrder::create($masterData);
                    $stats['imported_master']++;
                }

                // Satırları (Detail) ekle/güncelle
                foreach ($items as $item) {
                    $barcode     = $item['barcode'] ?? null;
                    $productName = $item['product_name'] ?? null;
                    if ($productName) {
                        // Normalize the product name (Epic 1 Best Practices)
                        $productName = \App\Services\DynamicTransformEngine::normalizeSingleProduct($productName);
                    }

                    $itemData = [
                        'operational_order_id' => $masterOrder->id,
                        'order_number'         => $orderNumber,
                        'barcode'              => $barcode,
                        'stock_code'           => $item['stock_code'] ?? null,
                        'product_name'         => $productName,
                        'brand'                => $item['brand'] ?? null,
                        'quantity'             => (int) ($item['quantity'] ?? 1),
                        'unit_price'           => $this->parseNumber($item['unit_price'] ?? 0),
                        'sale_price'           => $this->parseNumber($item['sale_price'] ?? 0),
                        'discount_amount'      => $this->parseNumber($item['discount_amount'] ?? 0),
                        'trendyol_discount'    => $this->parseNumber($item['trendyol_discount'] ?? 0),
                        'billable_amount'      => $this->parseNumber($item['billable_amount'] ?? 0),
                        'commission_rate'      => $this->parseNumber($item['commission_rate'] ?? 0),
                        'boutique_number'      => $item['boutique_number'] ?? null,
                        'cargo_desi'           => $this->parseNumber($item['cargo_desi'] ?? null) ?: null,
                        'calculated_desi'      => $this->parseNumber($item['calculated_desi'] ?? null) ?: null,
                        'invoiced_cargo_amount'=> $item['invoiced_cargo_amount'] ?? null,
                    ];

                    // Satırı bul veya yarat. (Belirli bir satır ID'si Excel'de genelde yoktur, bu yüzden barkod ile unique tutmaya çalışıyoruz)
                    // Ancak aynı siparişte aynı barkod tekrar geçmiş olabilir. O yüzden order_id + barcode eşleştirmesi yapıp update ediyoruz.
                    $existingItem = MpOperationalOrderItem::where('operational_order_id', $masterOrder->id)
                                        ->where('barcode', $barcode)
                                        ->first();
                    
                    if ($existingItem) {
                        $existingItem->update($itemData);
                        $stats['updated_items']++;
                    } else {
                        MpOperationalOrderItem::create($itemData);
                        $stats['imported_items']++;
                    }
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function parseNumber($val): float
    {
        if (empty($val)) return 0.00;
        if (is_numeric($val)) return (float) $val;

        $val = preg_replace('/[^0-9,\.-]/', '', (string)$val);
        if (strpos($val, ',') !== false && strpos($val, '.') !== false) {
            $val = str_replace('.', '', $val);
            $val = str_replace(',', '.', $val);
        } else {
            $val = str_replace(',', '.', $val);
        }
        return (float) $val;
    }

    protected function parseDate($val): ?Carbon
    {
        if (empty($val) || $val == 'Belirtilmedi' || $val == '-') return null;

        try {
            if (is_numeric($val)) {
                return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val));
            }
            return Carbon::parse($val);
        } catch (\Exception $e) {
            return null;
        }
    }
}
