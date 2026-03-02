<?php

namespace App\Services;

use App\Models\MpPeriod;
use App\Models\MpOrder;
use App\Models\MpTransaction;
use App\Models\MpInvoice;
use App\Models\MpSettlement;
use App\Models\ProductCost;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Carbon\Carbon;

/**
 * Pazaryeri Muhasebe — Excel Import Servisi
 *
 * 5 farklı Trendyol Excel dosyasını parse ederek veritabanına kaydeder.
 * Mevcut ExcelService::cleanString() pattern'ini kullanır.
 * Türkçe tarih/sayı formatlarını otomatik parse eder.
 * Upsert mantığıyla çalışır (var olan kayıtları günceller).
 */
class MarketplaceImportService
{
    protected ExcelService $excelService;

    // ─── Sütun Alias Mapping ─────────────────────────────────────

    /**
     * Trendyol Excel sütun başlıklarının standartlaştırılması.
     * Sol taraf = veritabanı alanı, sağ taraf = Excel'de karşılaşılabilecek başlıklar.
     */
    protected array $orderColumnAliases = [
        'order_number'      => ['Sipariş No', 'Siparis No', 'Order No', 'Order Number', 'Sipariş Numarası'],
        'order_date'        => ['Sipariş Tarihi', 'Siparis Tarihi', 'Order Date', 'Tarih'],
        'product_name'      => ['Ürün Adı', 'Urun Adi', 'Product Name', 'Ürün', 'Ürün Adı / Açıklama'],
        'barcode'           => ['Barkod', 'Barcode', 'Ürün Barkodu', 'Satıcı Barkodu'],
        'stock_code'        => ['Stok Kodu', 'Stock Code', 'Model Kodu', 'Satıcı Ürün Kodu'],
        'quantity'          => ['Adet', 'Quantity', 'Miktar', 'Ürün Adedi'],
        'list_price'        => ['Liste Fiyatı', 'List Price', 'İlk Fiyat', 'Orijinal Fiyat'],
        'sale_price'        => ['Satış Fiyatı', 'Sale Price', 'İndirimli Fiyat', 'Satış Tutarı'],
        'gross_amount'      => ['Satış Tutarı', 'Sipariş Tutarı', 'Brüt Tutar', 'Gross Amount', 'Toplam Tutar', 'Brüt Satış Tutarı', 'Orijinal Tutar'],
        'discount_amount'   => ['İndirim Tutarı', 'İndirim', 'Discount Amount', 'Müşteri İndirimi'],
        'campaign_discount' => ['Kampanya İndirimi', 'Kupon İndirimi', 'Campaign Discount', 'Platform İndirimi'],
        'commission_amount' => ['Komisyon Tutarı', 'Komisyon', 'Trendyol Komisyonu', 'Commission', 'Komisyon Bedeli'],
        'cargo_company'     => ['Kargo Firması', 'Cargo Company', 'Kargo', 'Taşıyıcı Şirket'],
        'cargo_desi'        => ['Desi', 'Desi Değeri', 'Hacimsel Ağırlık', 'Ağırlık'],
        'cargo_amount'      => ['Kargo Tutarı', 'Gönderi Kargo Bedeli', 'Kargo Gideri', 'Kargo Kesintisi', 'Cargo Amount', 'Kargo Bedeli', 'Kargo Ücreti'],
        'service_fee'       => ['Hizmet Bedeli', 'Platform Hizmet Bedeli', 'Uluslararası Hizmet Bedeli', 'Service Fee', 'İşlem Bedeli'],
        'net_hakedis'       => ['Net Hakediş', 'Net Tutar', 'Net Amount', 'Ödenecek Tutar', 'Tahmini Hakediş'],
        'status'            => ['Sipariş Durumu', 'Sipariş Statüsü', 'Durum', 'Status', 'Sipariş Durum', 'Siparişin Durumu', 'Sipariş Satır Durumu', 'Paket Durumu'],
        'delivery_date'     => ['Teslim Tarihi', 'Delivery Date', 'Teslimat Tarihi'],
        'payment_date'      => ['Ödeme Tarihi', 'Hakediş Tarihi', 'Vade Tarihi', 'Payment Date'],
    ];

    protected array $transactionColumnAliases = [
        'transaction_date'  => ['İşlem Tarihi', 'Islem Tarihi', 'Transaction Date', 'Tarih'],
        'document_number'   => ['Belge No', 'Dekont No', 'Fatura No', 'Document Number', 'Belge Numarası'],
        'order_number'      => ['Sipariş No', 'Siparis No', 'Order No'],
        'transaction_type'  => ['İşlem Tipi', 'Fiş Türü', 'Islem Tipi', 'Transaction Type', 'İşlem Türü'],
        'description'       => ['Açıklama', 'Aciklama', 'Description'],
        'debt'              => ['Borç', 'Borc', 'Debt'],
        'credit'            => ['Alacak', 'Credit'],
        'balance'           => ['Bakiye', 'Balance'],
    ];

    protected array $stopajColumnAliases = [
        'order_number'      => ['Sipariş Numarası', 'Sipariş No', 'Siparis No', 'Order No'],
        'transaction_date'  => ['Sipariş Tarihi', 'İşlem Tarihi', 'Tarih'],
        'gross_amount'      => ['E-Ticaret Stopaj Matrahı (KDV Hariç Ürün Bedeli)', 'Brüt Satış Matrahı', 'Brüt Tutar', 'Matrah', 'Brüt Satış Tutarı'],
        'withholding_tax'   => ['Hesaplanan Stopaj Tutarı', 'Bidirilen Toplam E-ticaret Stopajı Tutarı', 'Stopaj Tutarı', 'Kesilen Stopaj', 'Tevkifat', 'Stopaj'],
    ];

    protected array $invoiceColumnAliases = [
        'invoice_number'    => ['Fatura No', 'Fatura Numarası', 'Invoice No'],
        'invoice_date'      => ['Fatura Tarihi', 'Invoice Date', 'Tarih'],
        'invoice_type'      => ['Fatura Tipi', 'Fatura Türü', 'Invoice Type'],
        'net_amount'        => ['Tutar', 'KDV Hariç Tutar', 'Net Tutar', 'Net Amount'],
        'vat_amount'        => ['KDV Tutarı', 'KDV', 'VAT Amount'],
        'total_amount'      => ['KDV Dahil Tutar', 'Toplam Tutar', 'Total Amount'],
        'description'       => ['Açıklama', 'Description'],
    ];

    protected array $settlementColumnAliases = [
        'transaction_type'  => ['İşlem Tipi', 'Islem Tipi'],
        'order_number'      => ['Sipariş No', 'Siparis No', 'Kayıt No / Fatura No', 'Sipariş Numarası'],
        'transaction_date'  => ['İşlem Tarihi', 'Sipariş Tarihi', 'Tarih'],
        'settlement_date'   => ['Teslim Tarihi', 'Hakediş Tarihi', 'Ödeme Tarihi', 'Settlement Date'],
        'due_date'          => ['Vade Tarihi', 'Son Ödeme Tarihi', 'Ödeme Vadesi', 'Due Date'],
        'commission_rate'   => ['Komisyon Oranı', 'Komisyon (%)'],
        'ty_hakedis'        => ['TY Hakediş', 'Trendyol Hakediş', 'Platform Hakediş'],
        'seller_hakedis'    => ['Satıcı Hakediş', 'Net Hakediş', 'Satici Hakedis', 'Satıcı Hak Ediş'],
        'total_amount'      => ['Toplam Tutar', 'Sipariş Tutarı', 'Brüt Tutar'],
        'stopaj'            => ['Stopaj', 'Stopaj Tutarı'],
        'delivery_date'     => ['Teslim Tarihi'],
    ];

    public function __construct()
    {
        $this->excelService = new ExcelService();
    }

    // ═══════════════════════════════════════════════════════════════
    // IMPORT METHODS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Sipariş Kayıtları Excel'ini import et
     */
    public function importOrders(UploadedFile $file, MpPeriod $period): array
    {
        $stats = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        try {
            $rows = $this->readExcel($file);
            $mapped = $this->mapColumns($rows, $this->orderColumnAliases);

            // Paylaşımlı Kargo (Shared Cargo) ve Çoklu Sepet Algoritması
            // Trendyol Excel'de aynı sipariş numarasının her bir satırına kargo/hizmet bedelini tam (aynı) yansıtır.
            // Bunu mükerrer yazmamak için satır fiyatlarına göre orantılayıp dağıtacağız.
            $groupedByOrder = collect($mapped)->groupBy(fn($row) => trim($row['order_number'] ?? ''));
            $adjustedMapped = collect();
            $defaultVatRate = (\App\Models\MpFinancialRule::getRuleFloat('default_product_vat_rate') ?: 0.20) * 100;

            foreach ($groupedByOrder as $orderNumber => $orderRows) {
                if (empty($orderNumber)) {
                    $adjustedMapped = $adjustedMapped->merge($orderRows);
                    continue;
                }

                $totalGross = $orderRows->sum(fn($r) => $this->parseNumber($r['gross_amount'] ?? 0));
                $maxCargo   = $orderRows->max(fn($r) => $this->parseNumber($r['cargo_amount'] ?? 0));
                $maxService = $orderRows->max(fn($r) => $this->parseNumber($r['service_fee'] ?? 0));

                $cargoDistributed = 0;
                $serviceDistributed = 0;
                $rowCount = $orderRows->count();
                $processedCount = 0;

                foreach ($orderRows as $r) {
                    $processedCount++;
                    $rowGross = $this->parseNumber($r['gross_amount'] ?? 0);

                    if ($rowCount > 1) { // Çoklu Sepet
                        if ($totalGross > 0) {
                            $ratio = $rowGross / $totalGross;
                            $allocatedCargo = round($maxCargo * $ratio, 2);
                            $allocatedService = round($maxService * $ratio, 2);
                        } else {
                            $allocatedCargo = round($maxCargo / $rowCount, 2);
                            $allocatedService = round($maxService / $rowCount, 2);
                        }

                        // Küsurattan yitirilen kuruşluk farkı son satıra giydir
                        if ($processedCount === $rowCount) {
                            $allocatedCargo = round($maxCargo - $cargoDistributed, 2);
                            $allocatedService = round($maxService - $serviceDistributed, 2);
                        }

                        $cargoDistributed += $allocatedCargo;
                        $serviceDistributed += $allocatedService;

                        $r['cargo_amount'] = $allocatedCargo;
                        $r['service_fee']  = $allocatedService;
                    } 
                    
                    $adjustedMapped->push($r);
                }
            }
            
            $mapped = $adjustedMapped;

            DB::beginTransaction();

            foreach ($mapped->chunk(1000) as $chunk) {
                foreach ($chunk as $index => $row) {
                    try {
                        $orderNumber = trim($row['order_number'] ?? '');
                        if (empty($orderNumber)) {
                            $stats['skipped']++;
                            continue;
                        }

                        // ... data mapping logic ...
                        $costData = $this->lookupProductCost($row['stock_code'] ?? null, $row['barcode'] ?? null);
                        $grossAmount = abs($this->parseNumber($row['gross_amount'] ?? 0));
                        $commissionAmount = abs($this->parseNumber($row['commission_amount'] ?? 0));
                        $commissionRate = $grossAmount > 0 ? round(($commissionAmount / $grossAmount) * 100, 2) : 0;
                        $quantity = (int) ($row['quantity'] ?? 1);

                        // COGS ve ambalaj: birim maliyet × adet (SyncOperationalToFinancialJob ile tutarlı)
                        $unitCogs = (float) ($costData['production_cost'] ?? 0);
                        $unitPackaging = (float) ($costData['packaging_cost'] ?? 0);
                        $totalCogs = $unitCogs > 0 ? round($unitCogs * $quantity, 2) : null;
                        $totalPackaging = $unitPackaging > 0 ? round($unitPackaging * $quantity, 2) : null;

                        $data = [
                            'period_id'             => $period->id,
                            'order_number'          => mb_substr($orderNumber, 0, 100),
                            'barcode'               => mb_substr(trim($row['barcode'] ?? ''), 0, 100),
                            'stock_code'            => mb_substr(trim($row['stock_code'] ?? ''), 0, 100),
                            'product_name'          => mb_substr($row['product_name'] ?? '', 0, 250),
                            'quantity'              => $quantity,
                            'order_date'            => $this->parseDate($row['order_date'] ?? null),
                            'delivery_date'         => $this->parseDate($row['delivery_date'] ?? null),
                            'payment_date'          => $this->parseDate($row['payment_date'] ?? null),
                            'status'                => $this->normalizeStatus($row['status'] ?? 'Kargoda'),
                            'list_price'            => abs($this->parseNumber($row['list_price'] ?? 0)),
                            'sale_price'            => abs($this->parseNumber($row['sale_price'] ?? 0)),
                            'gross_amount'          => $grossAmount,
                            'discount_amount'       => abs($this->parseNumber($row['discount_amount'] ?? 0)),
                            'campaign_discount'     => abs($this->parseNumber($row['campaign_discount'] ?? 0)),
                            'commission_rate'       => $commissionRate,
                            'commission_amount'     => $commissionAmount,
                            'commission_tax'        => round($commissionAmount * 0.20, 2),
                            'cargo_company'         => mb_substr($row['cargo_company'] ?? '', 0, 100),
                            'cargo_desi'            => abs($this->parseNumber($row['cargo_desi'] ?? 0)),
                            'cargo_amount'          => abs($this->parseNumber($row['cargo_amount'] ?? 0)),
                            'cargo_tax'             => round(abs($this->parseNumber($row['cargo_amount'] ?? 0)) * 0.20, 2),
                            'service_fee'           => abs($this->parseNumber($row['service_fee'] ?? 0)),
                            'withholding_tax'       => abs($this->parseNumber($row['withholding_tax'] ?? 0)),
                            'net_hakedis'           => $this->parseNumber($row['net_hakedis'] ?? 0), // Can be negative
                            'product_vat_rate'      => $costData['vat_rate'] ?? $defaultVatRate,
                            'cogs_at_time'          => $totalCogs,
                            'packaging_cost_at_time' => $totalPackaging,
                            'raw_data'              => $row,
                        ];


                        // Mükerrer Koruma: UNIQUE INDEX (order_number, barcode, period_id) ile DB düzeyinde engellenir
                        // updateOrCreate ile varsa güncelle, yoksa oluştur
                        $barcode = trim($data['barcode'] ?? '') ?: null; // Boş barkod → NULL (UNIQUE INDEX uyumlu)
                        
                        MpOrder::updateOrCreate(
                            [
                                'order_number' => $orderNumber,
                                'barcode'      => $barcode,
                                'period_id'    => $period->id,
                            ],
                            array_merge($data, ['barcode' => $barcode])
                        );

                        $stats['imported']++;
                    } catch (\Exception $e) {
                        $stats['errors'][] = "Satır " . ($index + 2) . ": " . $e->getMessage();
                        Log::warning('MpImport order row error', ['row' => $index, 'error' => $e->getMessage()]);
                    }
                }
            }

            DB::commit();

            // Dönem istatistiklerini güncelle
            $period->recalculateStats();

            Log::info('MpImport: Sipariş import tamamlandı', $stats);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MpImport: Sipariş import hatası', ['error' => $e->getMessage()]);
            throw $e;
        }

        return $stats;
    }

    /**
     * Cari Hesap Ekstresi Excel'ini import et
     */
    public function importTransactions(UploadedFile $file, MpPeriod $period): array
    {
        $stats = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        try {
            $rows = $this->readExcel($file);
            $mapped = $this->mapColumns($rows, $this->transactionColumnAliases);

            // Mevcut kayıtları hafızaya al (N+1 sorgu hatasını önlemek için)
            $existingMap = MpTransaction::where('period_id', $period->id)
                ->get()
                ->groupBy(fn($item) => "{$item->document_number}_{$item->order_number}_{$item->transaction_type}");

            DB::beginTransaction();

            foreach ($mapped->chunk(1000) as $chunk) {
                foreach ($chunk as $index => $row) {
                    try {
                        $date = $this->parseDate($row['transaction_date'] ?? null);
                        if (!$date) {
                            $stats['skipped']++;
                            continue;
                        }

                        $data = [
                            'period_id'        => $period->id,
                            'transaction_date' => $date,
                            'document_number'  => mb_substr(trim($row['document_number'] ?? ''), 0, 150),
                            'order_number'     => mb_substr(trim($row['order_number'] ?? ''), 0, 100),
                            'transaction_type' => mb_substr(trim($row['transaction_type'] ?? ''), 0, 100),
                            'description'      => mb_substr($row['description'] ?? '', 0, 250),
                            'debt'             => abs($this->parseNumber($row['debt'] ?? 0)),
                            'credit'           => abs($this->parseNumber($row['credit'] ?? 0)),
                            'balance'          => $this->parseNumber($row['balance'] ?? null), // Balance can be negative
                        ];

                        // Local lookup
                        $key = "{$data['document_number']}_{$data['order_number']}_{$data['transaction_type']}";
                        $existing = $existingMap->get($key)?->first();

                        if ($existing) {
                            $existing->update($data);
                            $stats['updated']++;
                        } else {
                            MpTransaction::create($data);
                            $stats['imported']++;
                        }
                    } catch (\Exception $e) {
                        $stats['errors'][] = "Satır " . ($index + 2) . ": " . $e->getMessage();
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $stats;
    }

    /**
     * Stopaj Excel'ini import et — siparişlere withholding_tax ekler
     */
    public function importWithholdingTax(UploadedFile $file, MpPeriod $period): array
    {
        $stats = ['matched' => 0, 'unmatched' => 0, 'errors' => []];

        try {
            $rows = $this->readExcel($file);
            $mapped = $this->mapColumns($rows, $this->stopajColumnAliases);

            foreach ($mapped as $index => $row) {
                try {
                    $orderNumber = trim($row['order_number'] ?? '');
                    $stopaj = abs($this->parseNumber($row['withholding_tax'] ?? 0));

                    if (empty($orderNumber) || $stopaj <= 0) continue;

                    // ÖNEMLİ: period_id kısıtlamasını kaldırıyoruz. Çünkü Ocak ayında satılan 
                    // siparişin stopajı Şubat ayında kesilip Şubat raporuna yansıyabilir.
                    $updated = MpOrder::where('order_number', $orderNumber)
                        ->update(['withholding_tax' => $stopaj]);

                    if ($updated > 0) {
                        $stats['matched']++;
                    } else {
                        $stats['unmatched']++;
                    }
                } catch (\Exception $e) {
                    $stats['errors'][] = "Satır " . ($index + 2) . ": " . $e->getMessage();
                }
            }
        } catch (\Exception $e) {
            throw $e;
        }

        return $stats;
    }

    /**
     * Fatura Excel'ini import et
     */
    public function importInvoices(UploadedFile $file, MpPeriod $period): array
    {
        $stats = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        try {
            $rows = $this->readExcel($file);
            $mapped = $this->mapColumns($rows, $this->invoiceColumnAliases);

            DB::beginTransaction();

            foreach ($mapped as $index => $row) {
                try {
                    $invoiceNo = trim($row['invoice_number'] ?? '');
                    if (empty($invoiceNo)) {
                        $stats['skipped']++;
                        continue;
                    }

                    $invoiceType = mb_substr($row['invoice_type'] ?? 'Diğer', 0, 100);

                    $netAmount = abs($this->parseNumber($row['net_amount'] ?? 0));
                    $vatAmount = abs($this->parseNumber($row['vat_amount'] ?? 0));
                    $totalAmount = abs($this->parseNumber($row['total_amount'] ?? 0));

                    // Eğer faturada "iade" kelimesi geçiyorsa bu bir gider iadesidir (satıcıya artı yazar)
                    if (str_contains(mb_strtolower($invoiceType), 'iade') || str_contains(mb_strtolower($invoiceType), 'return')) {
                        $netAmount = -$netAmount;
                        $vatAmount = -$vatAmount;
                        $totalAmount = -$totalAmount;
                    }

                    // KDV oranını hesapla
                    $vatRate = (abs($netAmount) > 0 && abs($vatAmount) > 0)
                        ? round((abs($vatAmount) / abs($netAmount)) * 100, 0)
                        : 20;

                    $data = [
                        'period_id'      => $period->id,
                        'invoice_number' => mb_substr($invoiceNo, 0, 100),
                        'invoice_date'   => $this->parseDate($row['invoice_date'] ?? null),
                        'invoice_type'   => $invoiceType,
                        'net_amount'     => $netAmount,
                        'vat_amount'     => $vatAmount,
                        'vat_rate'       => $vatRate,
                        'total_amount'   => $totalAmount ?: ($netAmount + $vatAmount),
                        'description'    => mb_substr($row['description'] ?? '', 0, 250),
                    ];

                    $existing = MpInvoice::where('period_id', $period->id)
                        ->where('invoice_number', $invoiceNo)
                        ->first();

                    if ($existing) {
                        $existing->update($data);
                        $stats['updated']++;
                    } else {
                        MpInvoice::create($data);
                        $stats['imported']++;
                    }
                } catch (\Exception $e) {
                    $stats['errors'][] = "Satır " . ($index + 2) . ": " . $e->getMessage();
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $stats;
    }

    /**
     * Ödeme Detay (Hakedişler) Excel'ini import et
     */
    public function importSettlements(UploadedFile $file, MpPeriod $period): array
    {
        $stats = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        try {
            $rows = $this->readExcel($file);
            $mapped = $this->mapColumns($rows, $this->settlementColumnAliases);

            DB::beginTransaction();

            foreach ($mapped->chunk(1000) as $chunk) {
                foreach ($chunk as $index => $row) {
                    try {
                        $orderNumber = trim($row['order_number'] ?? '');
                        if (empty($orderNumber)) {
                            $stats['skipped']++;
                            continue;
                        }

                        // Find closest matching order system-wide (Cross-Period)
                        $order = MpOrder::where('order_number', $orderNumber)->first(); 
                        $orderId = $order ? $order->id : null;
                        
                        // Settlement'ı siparişin kendi dönemine ata (cross-period uyum)
                        $effectivePeriodId = $order ? $order->period_id : $period->id;

                        $data = [
                            'user_id'          => \Illuminate\Support\Facades\Auth::id() ?? 1,
                            'period_id'        => $effectivePeriodId,
                            'order_id'         => $orderId,
                            'transaction_type' => mb_substr($row['transaction_type'] ?? '', 0, 100),
                            'transaction_date' => $this->parseDate($row['transaction_date'] ?? null),
                            'settlement_date'  => $this->parseDate($row['settlement_date'] ?? null),
                            'due_date'         => $this->parseDate($row['due_date'] ?? null),
                            'commission_rate'  => abs($this->parseNumber($row['commission_rate'] ?? 0)),
                            'ty_hakedis'       => $this->parseNumber($row['ty_hakedis'] ?? 0),
                            'seller_hakedis'   => $this->parseNumber($row['seller_hakedis'] ?? 0),
                            'total_amount'     => abs($this->parseNumber($row['total_amount'] ?? 0)),
                            'is_reconciled'    => false,
                        ];

                        MpSettlement::updateOrCreate(
                            [
                                'user_id'          => $data['user_id'],
                                'order_number'     => $orderNumber,
                                'transaction_date' => $data['transaction_date'],
                                'transaction_type' => $data['transaction_type'] ?? 'Hakediş',
                            ],
                            $data
                        );

                        // Eğer siparişi bulduysak, eksik verileri OdemeDetay'dan tamamla (Kazan-Kazan)
                        if ($order) {
                            $updates = [];
                            
                            $deliveryDate = $this->parseDate($row['delivery_date'] ?? null);
                            if ($deliveryDate && !$order->delivery_date) {
                                $updates['delivery_date'] = $deliveryDate;
                            }

                            $stopaj = $this->parseNumber($row['stopaj'] ?? 0);
                            // Trendyol stopajı eksi yazar, biz pozitif (veya AuditEngine'e uygun) tutalım
                            if ($stopaj != 0 && $order->withholding_tax == 0) {
                                $updates['withholding_tax'] = abs($stopaj);
                            }

                            if (!empty($updates)) {
                                $order->update($updates);
                            }
                        }

                        $stats['imported']++;
                        
                    } catch (\Exception $e) {
                        $stats['skipped']++;
                        $errorMsg = "Satır " . ($index + 2) . " Hata: " . $e->getMessage();
                        $stats['errors'][] = $errorMsg;
                        Log::error('Settlement Import Error: ', ['error' => $e->getMessage(), 'row' => $row]);
                    }
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $stats;
    }

    // ═══════════════════════════════════════════════════════════════
    // HELPER METHODS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Excel dosyasını oku — tek sheet varsayımı
     */
    protected function readExcel(UploadedFile $file): Collection
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $readerType = ($extension === 'xls') ? 'Xls' : 'Xlsx';
        
        $reader = IOFactory::createReader($readerType);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        
        $spreadsheet = $reader->load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet(); // Sheet 1

        $data = $sheet->toArray(null, true, true, true);

        // Header satırını bul (Trendyol'da bazen ilk satır değil, 2. veya 3. satır olabiliyor)
        $headers = [];
        $headerRowIndex = 1;

        // İlk 10 satırı tara
        $rowIndex = 1;
        foreach ($data as $row) {
            $rowStr = implode(' ', array_filter($row, fn($v) => $v !== null && $v !== ''));
            $rowStrLower = mb_strtolower($rowStr);
            
            // Eğer satırda kritik başlık kelimelerinden biri geçiyorsa, burası başlıktır
            if (str_contains($rowStrLower, 'sipariş no') || 
                str_contains($rowStrLower, 'siparis no') || 
                str_contains($rowStrLower, 'barkod') || 
                str_contains($rowStrLower, 'fatura no') ||
                str_contains($rowStrLower, 'işlem tipi')) {
                $headers = $row;
                $headerRowIndex = $rowIndex;
                break;
            }
            $rowIndex++;
            if ($rowIndex > 15) break; // 15 satıra kadar bulamadıysa bırak
        }

        // Eğer başlık bulunamadıysa (veya dosya standart ise) ilk satırı başlık kabul et
        if (empty($headers)) {
            $headers = $data[1] ?? [];
            $headerRowIndex = 1;
        }

        // Başlıktan önceki satırları diziden at
        for ($i = 1; $i <= $headerRowIndex; $i++) {
            array_shift($data);
        }

        if (empty($headers)) return collect();

        $result = collect();
        foreach ($data as $row) {
            $item = [];
            foreach ($headers as $col => $header) {
                if ($header !== null && trim((string)$header) !== '') {
                    $cleanHeader = $this->cleanString(trim((string)$header));
                    $value = $row[$col] ?? null;
                    $item[$cleanHeader] = $this->cleanString($value);
                }
            }

            // Boş satırları atla
            if (!empty(array_filter($item, fn($v) => $v !== null && $v !== ''))) {
                $result->push($item);
            }
        }

        return $result;
    }

    /**
     * Sütun eşleştirme — Excel başlıklarını veritabanı alanlarına dönüştür
     */
    protected function mapColumns(Collection $rows, array $aliases): Collection
    {
        if ($rows->isEmpty()) return $rows;

        // Excel başlıklarını al
        $excelHeaders = array_keys($rows->first());

        \Illuminate\Support\Facades\Log::info('EXCEL RAW HEADERS DUMP:', ['headers' => $excelHeaders]);

        // Mapping oluştur: excel_header => db_field
        $mapping = [];
        foreach ($aliases as $dbField => $possibleHeaders) {
            foreach ($possibleHeaders as $header) {
                $cleanHeader = $this->cleanString(trim($header));
                foreach ($excelHeaders as $excelHeader) {
                    if (mb_strtolower(trim($excelHeader)) === mb_strtolower($cleanHeader)) {
                        $mapping[$excelHeader] = $dbField;
                        break 2;
                    }
                }
            }
        }

        // Her satırı eşleştir
        return $rows->map(function ($row) use ($mapping) {
            $mapped = [];
            foreach ($row as $key => $value) {
                $dbField = $mapping[$key] ?? null;
                if ($dbField) {
                    $mapped[$dbField] = $value;
                }
            }
            return $mapped;
        });
    }

    /**
     * Türkçe formatındaki sayıyı parse et
     * "1.049,90" → 1049.90
     */
    protected function parseNumber($value): ?float
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) return (float) $value;

        $value = (string) $value;
        $value = trim($value);

        // "TL", "₺" gibi para birimi sembollerini kaldır
        $value = preg_replace('/[^\d.,-]/', '', $value);

        // Türkçe format: nokta binlik, virgül ondalık
        if (str_contains($value, ',') && str_contains($value, '.')) {
            // 1.049,90 → 1049.90
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            // 49,90 → 49.90
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Türkçe tarih formatını parse et
     * "24.02.2025" veya "24.02.2025 14:30" → Carbon
     */
    protected function parseDate($value): ?string
    {
        if ($value === null || $value === '') return null;

        // Zaten bir tarih nesnesi ise
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $value = trim((string) $value);

        // Excel serial date number
        if (is_numeric($value) && (float)$value > 40000 && (float)$value < 60000) {
            try {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$value);
                return $date->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                return null;
            }
        }

        // Türkçe formatlar
        $formats = [
            'd.m.Y H:i:s',
            'd.m.Y H:i',
            'd.m.Y',
            'Y-m-d H:i:s',
            'Y-m-d',
            'd/m/Y H:i:s',
            'd/m/Y',
        ];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);
                if ($parsed) return $parsed->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                continue;
            }
        }

        // Son çare: Carbon parse
        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Sipariş durumunu standartlaştır
     */
    protected function normalizeStatus(string $status): string
    {
        $status = trim($status);
        $lower = mb_strtolower($status);

        return match (true) {
            str_contains($lower, 'parçalı')   => 'Kısmi Gönderim',
            str_contains($lower, 'kısmi')     => 'Kısmi Gönderim',
            str_contains($lower, 'iade')      => 'İade Edildi',
            str_contains($lower, 'iptal')     => 'İptal Edildi',
            str_contains($lower, 'return')    => 'İade Edildi',
            str_contains($lower, 'cancel')    => 'İptal Edildi',
            str_contains($lower, 'teslim')    => 'Teslim Edildi',
            str_contains($lower, 'kargo')     => 'Kargoda',
            str_contains($lower, 'delivered') => 'Teslim Edildi',
            default => mb_substr($status, 0, 50),
        };
    }

    /**
     * Ürün maliyetini mp_products tablosundan getir
     * Önce barcode ile eşleştir, eşleşmezse stock_code ile dene
     */
    protected function lookupProductCost(?string $stockCode, ?string $barcode): array
    {
        $cost = null;
        $userId = \Illuminate\Support\Facades\Auth::id() ?? 1;

        // 1. Barcode ile eşleştirme
        if ($barcode) {
            $cost = \App\Models\MpProduct::where('user_id', $userId)
                        ->where('barcode', $barcode)
                        ->first();
        }

        // 2. Barcode bulunamadıysa stock_code ile dene
        if (!$cost && $stockCode) {
            $cost = \App\Models\MpProduct::where('user_id', $userId)
                        ->where('stock_code', $stockCode)
                        ->first();
        }

        return [
            'production_cost' => $cost?->cogs,
            'packaging_cost'  => $cost?->packaging_cost ?? 0,
            'cargo_cost'      => $cost?->cargo_cost ?? 0,
            'vat_rate'        => $cost?->vat_rate ?? 10.0,
        ];
    }

    /**
     * UTF-8 ve kontrol karakteri temizleme (ExcelService uyumlu)
     */
    protected function cleanString($value): mixed
    {
        if ($value === null) return null;
        if (!is_string($value)) return $value;
        if (trim($value) === '') return '';

        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1254');
        }

        // Remove the weird Unicode dot-above character attached to i
        $value = str_replace("\u{0307}", '', $value);

        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        $value = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $value);
        
        // Collapse multiple spaces to single space so matching aliases works reliably
        $value = trim(preg_replace('/\s+/', ' ', $value));

        return $value;
    }
}
