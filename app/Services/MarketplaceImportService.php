<?php

namespace App\Services;

use App\Models\MpPeriod;
use App\Models\MpOrder;
use App\Models\MpTransaction;
use App\Models\MpInvoice;
use App\Models\MpSettlement;
use App\Models\MpProduct;
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
        'list_price'        => ['Liste Fiyatı', 'List Price', 'İlk Fiyat', 'Orijinal Fiyat', 'Birim Fiyatı'],
        'sale_price'        => ['Satış Fiyatı', 'Sale Price', 'İndirimli Fiyat'],
        'gross_amount'      => ['Satış Tutarı', 'Sipariş Tutarı', 'Brüt Tutar', 'Gross Amount', 'Toplam Tutar', 'Brüt Satış Tutarı', 'Orijinal Tutar'],
        'discount_amount'   => ['İndirim Tutarı', 'İndirim', 'Discount Amount', 'Müşteri İndirimi'],
        'campaign_discount' => ['Kampanya İndirimi', 'Kupon İndirimi', 'Campaign Discount', 'Platform İndirimi'],
        'commission_rate'   => ['Komisyon Oranı', 'Commission Rate'],
        'commission_amount' => ['Komisyon Tutarı', 'Komisyon', 'Trendyol Komisyonu', 'Commission', 'Komisyon Bedeli'],
        'cargo_company'     => ['Kargo Firması', 'Cargo Company', 'Kargo', 'Taşıyıcı Şirket'],
        'cargo_desi'        => ['Desi', 'Desi Değeri', 'Hacimsel Ağırlık', 'Ağırlık', 'Kargodan alınan desi'],
        'cargo_amount'      => ['Kargo Tutarı', 'Gönderi Kargo Bedeli', 'Kargo Gideri', 'Kargo Kesintisi', 'Cargo Amount', 'Kargo Bedeli', 'Kargo Ücreti', 'Faturalanan Kargo Tutarı'],
        'service_fee'       => ['Hizmet Bedeli', 'Platform Hizmet Bedeli', 'Service Fee', 'İşlem Bedeli'],
        'international_service_fee' => ['Uluslararası Hizmet Bedeli'],
        'refund_amount'     => ['İade', 'İade Tutarı'],
        'cancel_amount'     => ['İptal', 'İptal Tutarı'],
        'return_cargo_amount' => ['İade Kargo Bedeli'],
        'penalty_amount'    => ['Ceza Bedeli', 'Ceza Tutarı'],
        'other_amount'      => ['Diğer', 'Diğer Tutar'],
        'intl_operation_refund' => ['Yurtdışı Operasyon İade Bedeli'],
        'net_hakedis'       => ['Net Hakediş', 'Net Tutar', 'Net Amount', 'Ödenecek Tutar', 'Tahmini Hakediş'],
        'status'            => ['Sipariş Durumu', 'Sipariş Statüsü', 'Durum', 'Status', 'Sipariş Durum', 'Siparişin Durumu', 'Sipariş Satır Durumu', 'Paket Durumu', 'Alternatif Teslimat Statüsü'],
        'delivery_date'     => ['Teslim Tarihi', 'Delivery Date', 'Teslimat Tarihi'],
        'payment_date'      => ['Ödeme Tarihi', 'Hakediş Tarihi', 'Vade Tarihi', 'Payment Date'],
        'shipment_date'     => ['Kargoya Teslim Tarihi'],
    ];

    protected array $transactionColumnAliases = [
        'transaction_date'  => ['İşlem Tarihi', 'Islem Tarihi', 'Transaction Date', 'Tarih'],
        'document_number'   => ['Belge No', 'Dekont No', 'Fatura No', 'Document Number', 'Belge Numarası', 'Kalem NO', 'Kalem No', 'Kalem Numarası'],
        'order_number'      => ['Sipariş No', 'Siparis No', 'Order No'],
        'transaction_type'  => ['İşlem Tipi', 'Fiş Türü', 'Islem Tipi', 'Transaction Type', 'İşlem Türü'],
        'description'       => ['Açıklama', 'Aciklama', 'Description'],
        'debt'              => ['Borç', 'Borc', 'Debt'],
        'credit'            => ['Alacak', 'Credit'],
        'balance'           => ['Bakiye', 'Balance'],
        'barcode'           => ['Barkod', 'Barcode'],
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
        'document_number'   => ['Kayıt No / Fatura No', 'Kayıt No', 'Fatura No', 'Record No'],
        'transaction_type'  => ['İşlem Tipi', 'Islem Tipi'],
        'order_number'      => ['Sipariş No', 'Siparis No', 'Sipariş Numarası'],
        'transaction_date'  => ['İşlem Tarihi', 'Sipariş Tarihi', 'Tarih'],
        'settlement_date'   => ['Teslim Tarihi', 'Hakediş Tarihi', 'Ödeme Tarihi', 'Settlement Date'],
        'due_date'          => ['Vade Tarihi', 'Son Ödeme Tarihi', 'Ödeme Vadesi', 'Due Date'],
        'commission_rate'   => ['Komisyon Oranı', 'Komisyon (%)'],
        'ty_hakedis'        => ['TY Hakediş', 'Trendyol Hakediş', 'Platform Hakediş'],
        'seller_hakedis'    => ['Satıcı Hakediş', 'Net Hakediş', 'Satici Hakedis', 'Satıcı Hak Ediş'],
        'total_amount'      => ['Toplam Tutar', 'Sipariş Tutarı', 'Brüt Tutar'],
        'stopaj'            => ['Stopaj', 'Stopaj Tutarı'],
        'delivery_date'     => ['Teslim Tarihi'],
        'barcode'           => ['Barkod', 'Barcode'],
        'product_name'      => ['Ürün Adı / Açıklama', 'Ürün Adı', 'Product Name'],
    ];

    public function __construct()
    {
        $this->excelService = new ExcelService();
    }

    protected array $_resolvedPeriods = [];

    protected function resolvePeriodId(?string $dateString, MpPeriod $fallbackPeriod): int
    {
        if (empty($dateString)) {
            return $fallbackPeriod->id;
        }

        try {
            $date = \Carbon\Carbon::parse($dateString);
            $year = $date->year;
            $month = $date->month;
        } catch (\Exception $e) {
            return $fallbackPeriod->id;
        }

        $key = "{$year}_{$month}";

        if (isset($this->_resolvedPeriods[$key])) {
            return $this->_resolvedPeriods[$key];
        }

        $period = MpPeriod::firstOrCreate(
            [
                'user_id' => $fallbackPeriod->user_id,
                'year'    => $year,
                'month'   => $month,
            ],
            [
                'marketplace' => $fallbackPeriod->marketplace ?? 'Trendyol',
                'status'      => 'draft',
            ]
        );

        $this->_resolvedPeriods[$key] = $period->id;
        return $period->id;
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
                $maxService = $orderRows->max(function ($r) {
                    return $this->parseNumber($r['service_fee'] ?? 0)
                        + $this->parseNumber($r['international_service_fee'] ?? 0);
                });

                // Trendyol çoklu sepeti ayrı satır verdiğinde, finansal toplamları
                // (net_hakedis, komisyon, indirim, stopaj) HER SATIRA AYNI TUTARI yazar.
                // Bunları da max() ile alıp oransal dağıtmalıyız.
                $maxNetHakedis     = $orderRows->max(fn($r) => $this->parseNumber($r['net_hakedis'] ?? 0));
                $maxCommission     = $orderRows->max(fn($r) => abs($this->parseNumber($r['commission_amount'] ?? 0)));
                $maxDiscount       = $orderRows->max(fn($r) => abs($this->parseNumber($r['discount_amount'] ?? 0)));
                $maxCampaign       = $orderRows->max(fn($r) => abs($this->parseNumber($r['campaign_discount'] ?? 0)));
                $maxWithholding    = $orderRows->max(fn($r) => abs($this->parseNumber($r['withholding_tax'] ?? 0)));

                $cargoDistributed = 0;
                $serviceDistributed = 0;
                $hakedisDistributed = 0;
                $commissionDistributed = 0;
                $discountDistributed = 0;
                $campaignDistributed = 0;
                $withholdingDistributed = 0;
                $rowCount = $orderRows->count();
                $processedCount = 0;

                // Dağıtılması gereken alanlardaki tüm satırlardaki değerler aynı mı kontrol et
                // Eğer aynıysa Trendyol toplam tutarı her satıra yazmış → dağıtmamız gerekir
                // Farklıysa her satırda zaten kendi değeri var → dağıtmaya gerek yok
                $needsFinancialSplit = $rowCount > 1 && $this->allRowsSameValue($orderRows, 'net_hakedis');

                foreach ($orderRows as $r) {
                    $processedCount++;
                    $rowGross = $this->parseNumber($r['gross_amount'] ?? 0);

                    if ($rowCount > 1) { // Çoklu Sepet
                        $ratio = $totalGross > 0 ? ($rowGross / $totalGross) : (1 / $rowCount);
                        $isLastRow = ($processedCount === $rowCount);

                        // Kargo ve hizmet bedeli her zaman dağıtılır
                        $allocatedCargo   = $isLastRow ? round($maxCargo - $cargoDistributed, 2) : round($maxCargo * $ratio, 2);
                        $allocatedService = $isLastRow ? round($maxService - $serviceDistributed, 2) : round($maxService * $ratio, 2);

                        $cargoDistributed += $allocatedCargo;
                        $serviceDistributed += $allocatedService;

                        $r['cargo_amount'] = $allocatedCargo;
                        $r['service_fee']  = $allocatedService;
                        $r['_original_international_service_fee'] = $r['international_service_fee'] ?? 0;
                        $r['international_service_fee'] = 0;

                        // Finansal alanlar: sadece tüm satırlarda aynı değer varsa dağıt
                        if ($needsFinancialSplit) {
                            $allocatedHakedis    = $isLastRow ? round($maxNetHakedis - $hakedisDistributed, 2) : round($maxNetHakedis * $ratio, 2);
                            $allocatedCommission = $isLastRow ? round($maxCommission - $commissionDistributed, 2) : round($maxCommission * $ratio, 2);
                            $allocatedDiscount   = $isLastRow ? round($maxDiscount - $discountDistributed, 2) : round($maxDiscount * $ratio, 2);
                            $allocatedCampaign   = $isLastRow ? round($maxCampaign - $campaignDistributed, 2) : round($maxCampaign * $ratio, 2);
                            $allocatedWithholding = $isLastRow ? round($maxWithholding - $withholdingDistributed, 2) : round($maxWithholding * $ratio, 2);

                            $hakedisDistributed += $allocatedHakedis;
                            $commissionDistributed += $allocatedCommission;
                            $discountDistributed += $allocatedDiscount;
                            $campaignDistributed += $allocatedCampaign;
                            $withholdingDistributed += $allocatedWithholding;

                            $r['net_hakedis']       = $allocatedHakedis;
                            $r['commission_amount'] = $allocatedCommission;
                            $r['discount_amount']   = $allocatedDiscount;
                            $r['campaign_discount'] = $allocatedCampaign;
                            $r['withholding_tax']   = $allocatedWithholding;
                        }
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
                        
                        // Yeni format Excel'de commission_amount yok ama commission_rate var
                        // Komisyonu oran üzerinden hesapla
                        $commissionRate = abs($this->parseNumber($row['commission_rate'] ?? 0));
                        if ($commissionAmount == 0 && $commissionRate > 0 && $grossAmount > 0) {
                            $commissionAmount = round($grossAmount * ($commissionRate / 100), 2);
                        } elseif ($commissionAmount > 0 && $grossAmount > 0) {
                            $commissionRate = round(($commissionAmount / $grossAmount) * 100, 2);
                        }
                        
                        $quantity = (int) ($row['quantity'] ?? 1);
                        
                        $parsedOrderDate = $this->parseDate($row['order_date'] ?? null);
                        $targetPeriodId = $this->resolvePeriodId($parsedOrderDate, $period);

                        // COGS, ambalaj ve kargo: birim maliyet × adet (SyncOperationalToFinancialJob ile tutarlı)
                        $unitCogs = (float) ($costData['production_cost'] ?? 0);
                        $unitPackaging = (float) ($costData['packaging_cost'] ?? 0);
                        $unitCargo = (float) ($costData['cargo_cost'] ?? 0);
                        $totalCogs = $unitCogs > 0 ? round($unitCogs * $quantity, 2) : null;
                        $totalPackaging = $unitPackaging > 0 ? round($unitPackaging * $quantity, 2) : null;
                        $totalOwnCargo = $unitCargo > 0 ? round($unitCargo * $quantity, 2) : null;

                        $data = [
                            'period_id'             => $targetPeriodId,
                            'order_number'          => mb_substr($orderNumber, 0, 100),
                            'barcode'               => mb_substr(trim($row['barcode'] ?? ''), 0, 100),
                            'stock_code'            => $this->resolveStockCode($row['stock_code'] ?? '', $row['barcode'] ?? ''),
                            'product_name'          => $this->resolveProductName($row['product_name'] ?? '', $row['barcode'] ?? '', $row['stock_code'] ?? ''),
                            'quantity'              => $quantity,
                            'order_date'            => $parsedOrderDate,
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
                            'service_fee'           => abs($this->parseNumber($row['service_fee'] ?? 0))
                                + abs($this->parseNumber($row['international_service_fee'] ?? 0)),
                            'withholding_tax'       => abs($this->parseNumber($row['withholding_tax'] ?? 0)),
                            'net_hakedis'           => $this->parseNumber($row['net_hakedis'] ?? 0), // Can be negative
                            'product_vat_rate'      => $costData['vat_rate'] ?? $defaultVatRate,
                            'cogs_at_time'          => $totalCogs,
                            'packaging_cost_at_time' => $totalPackaging,
                            'own_cargo_cost_at_time' => $totalOwnCargo,
                            'raw_data'              => $row,
                        ];


                        // Mükerrer Koruma: UNIQUE INDEX (order_number, barcode, period_id) ile DB düzeyinde engellenir
                        $barcode = trim($data['barcode'] ?? '') ?: null; // Boş barkod → NULL (UNIQUE INDEX uyumlu)
                        
                        // ─── Çoklu Ürün Ayrıştırma (Split) Mantığı ───
                        // Eğer bu sipariş numarası için barkodlu yeni satır geliyorsa VE
                        // önceden barkodu NULL olan tek bir "birleşik" kayıt varsa → eski kaydı sil
                        if ($barcode) {
                            $oldMergedRecord = MpOrder::where('order_number', $orderNumber)
                                ->whereNull('barcode')
                                ->first();
                            
                            if ($oldMergedRecord) {
                                // Eski kaydın finansal verilerini oran bazlı dağıtmak için saklayalım
                                // (Toplam komisyon, net hakediş, service_fee vs.)
                                $oldNetHakedis = (float) $oldMergedRecord->net_hakedis;
                                $oldCommission = (float) $oldMergedRecord->commission_amount;
                                $oldServiceFee = (float) $oldMergedRecord->service_fee;
                                $oldWithholding = (float) $oldMergedRecord->withholding_tax;
                                $oldGrossTotal = (float) $oldMergedRecord->gross_amount;
                                
                                // Eski birleşik kaydın sileceğimize dair flagimız
                                // (İlk barkodlu satırda silinecek, sonraki satırlarda zaten silinmiş olacak)
                                $oldMergedRecord->delete();
                                Log::info("MpImport: Eski birleşik kayıt silindi, ürün bazlı ayrıştırılıyor", [
                                    'order_number' => $orderNumber,
                                    'old_qty' => $oldMergedRecord->quantity,
                                ]);
                                
                                // Finansalları oran bazlı dağıt (bu satırın payı)
                                if ($oldGrossTotal > 0 && $grossAmount > 0) {
                                    $ratio = $grossAmount / $oldGrossTotal;
                                    if ($data['commission_amount'] == 0 && $oldCommission > 0) {
                                        $data['commission_amount'] = round($oldCommission * $ratio, 2);
                                        $data['commission_tax'] = round($data['commission_amount'] * 0.20, 2);
                                    }
                                    if (($data['service_fee'] ?? 0) == 0 && $oldServiceFee > 0) {
                                        $data['service_fee'] = round($oldServiceFee * $ratio, 2);
                                    }
                                    if (($data['net_hakedis'] ?? 0) == 0 && $oldNetHakedis != 0) {
                                        $data['net_hakedis'] = round($oldNetHakedis * $ratio, 2);
                                    }
                                    if (($data['withholding_tax'] ?? 0) == 0 && $oldWithholding > 0) {
                                        $data['withholding_tax'] = round($oldWithholding * $ratio, 2);
                                    }
                                }
                            }
                        }
                        
                        // ─── Akıllı Merge: Mevcut kaydın finansal verilerini koru ───
                        // Yeni Excel'de (ürün detaylı) komisyon/net_hakedis yoksa
                        // ama veritabanında var → koruyalım (eski finansal Excel'den gelmiş olabilir)
                        $existingOrder = MpOrder::where('order_number', $orderNumber)
                            ->where('barcode', $barcode)
                            ->where('period_id', $targetPeriodId)
                            ->first();
                        
                        if ($existingOrder) {
                            // Mevcut kayıt varsa: sıfır olan yeni alanları eskiden doldur
                            $preserveFields = ['commission_amount', 'commission_rate', 'commission_tax', 
                                             'net_hakedis', 'service_fee', 'withholding_tax'];
                            foreach ($preserveFields as $field) {
                                $newVal = $data[$field] ?? 0;
                                $oldVal = $existingOrder->$field;
                                if (($newVal == 0 || $newVal === null) && $oldVal != 0 && $oldVal !== null) {
                                    $data[$field] = $oldVal;
                                }
                            }
                        }
                        
                        MpOrder::updateOrCreate(
                            [
                                'order_number' => $orderNumber,
                                'barcode'      => $barcode,
                                'period_id'    => $targetPeriodId,
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

                        $targetPeriodId = $this->resolvePeriodId($date, $period);

                        $data = [
                            'period_id'        => $targetPeriodId,
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

                        // Mükerrer sipariş verisi güncelleme - Barkod eksikse Cari'den tamamla ve kârlılık (COGS) yeniden hesapla
                        if (!empty($row['barcode']) && !empty($data['order_number'])) {
                            $barcode = mb_substr(trim($row['barcode']), 0, 100);
                            $order = MpOrder::where('order_number', $data['order_number'])->first();
                            if ($order && empty($order->barcode)) {
                                $order->barcode = $barcode;
                                
                                $costData = $this->lookupProductCost($order->stock_code, $barcode);
                                $quantity = $order->quantity ?: 1;
                                $unitCogs = (float) ($costData['production_cost'] ?? 0);
                                $unitPackaging = (float) ($costData['packaging_cost'] ?? 0);
                                
                                if ($unitCogs > 0) {
                                    $order->cogs_at_time = round($unitCogs * $quantity, 2);
                                }
                                if ($unitPackaging > 0) {
                                    $order->packaging_cost_at_time = round($unitPackaging * $quantity, 2);
                                }
                                if (isset($costData['vat_rate'])) {
                                    $order->product_vat_rate = $costData['vat_rate'];
                                }

                                // Ürün adını ve stok kodunu da mp_products'tan doldur
                                if (empty($order->product_name) || empty($order->stock_code)) {
                                    $matchedProduct = MpProduct::where('barcode', $barcode)->first();
                                    if ($matchedProduct) {
                                        if (empty($order->product_name) && $matchedProduct->product_name) {
                                            $order->product_name = $matchedProduct->product_name;
                                        }
                                        if (empty($order->stock_code) && $matchedProduct->stock_code) {
                                            $order->stock_code = $matchedProduct->stock_code;
                                        }
                                    }
                                }
                                
                                $order->save();
                            }
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

                    $parsedInvoiceDate = $this->parseDate($row['invoice_date'] ?? null);
                    $targetPeriodId = $this->resolvePeriodId($parsedInvoiceDate, $period);

                    $data = [
                        'period_id'      => $targetPeriodId,
                        'invoice_number' => mb_substr($invoiceNo, 0, 100),
                        'invoice_date'   => $parsedInvoiceDate,
                        'invoice_type'   => $invoiceType,
                        'net_amount'     => $netAmount,
                        'vat_amount'     => $vatAmount,
                        'vat_rate'       => $vatRate,
                        'total_amount'   => $totalAmount ?: ($netAmount + $vatAmount),
                        'description'    => mb_substr($row['description'] ?? '', 0, 250),
                    ];

                    $existing = MpInvoice::where('period_id', $targetPeriodId)
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

            // Self-Healing: Önceki hatalı import'tan kalan order_number'sız yetim kayıtları temizle
            $orphanedCount = MpSettlement::whereNull('order_number')->orWhere('order_number', '')->count();
            if ($orphanedCount > 0) {
                MpSettlement::whereNull('order_number')->orWhere('order_number', '')->delete();
                Log::info("Settlement Import: {$orphanedCount} orphaned settlement records (NULL order_number) cleaned up.");
            }

            // Kullanıcının ID'si (chunk dışında bir kez alalım)
            $authUserId = \Illuminate\Support\Facades\Auth::id() ?? 1;
            $userPeriodIds = MpPeriod::where('user_id', $authUserId)->pluck('id');
            if ($userPeriodIds->isEmpty()) {
                $userPeriodIds = collect([$period->id]);
            }

            foreach ($mapped->chunk(1000) as $chunk) {
                // N+1 sorgu hatasını ve mükerrer kayıt ezilmesini önlemek için mevcut kayıtları önbelleğe al
                $chunkOrderNumbers = [];
                foreach ($chunk as $r) {
                    $oNum = trim($r['order_number'] ?? '');
                    if (!empty($oNum)) {
                        $chunkOrderNumbers[] = $oNum;
                    }
                }
                $chunkOrderNumbers = array_unique($chunkOrderNumbers);
                
                $existingMap = [];
                if (!empty($chunkOrderNumbers)) {
                    $existingSettlements = MpSettlement::where('user_id', $authUserId)
                        ->whereIn('order_number', $chunkOrderNumbers)
                        ->orderBy('id')
                        ->get();
                        
                    foreach ($existingSettlements as $s) {
                        $docNum = trim($s->document_number ?? '');
                        $dateStr = $s->transaction_date ? \Carbon\Carbon::parse($s->transaction_date)->format('Y-m-d') : '';
                        $amountStr = number_format((float) $s->seller_hakedis, 2, '.', '');
                        $typeStr = mb_strtolower(trim($s->transaction_type ?? 'hakediş'));
                        
                        // document_number benzersizdir — hash key'e dahil edilerek aynı siparişteki
                        // çoklu adet satış satırlarının (qty>1) birbirini ezmesi önlenir
                        $key = !empty($docNum)
                            ? "{$s->order_number}_{$docNum}"
                            : "{$s->order_number}_{$dateStr}_{$typeStr}_{$amountStr}";
                        if (!isset($existingMap[$key])) {
                            $existingMap[$key] = [];
                        }
                        $existingMap[$key][] = $s;
                    }
                }
                
                $localCounter = [];

                foreach ($chunk as $index => $row) {
                    try {
                        $orderNumber = trim($row['order_number'] ?? '');
                        if (empty($orderNumber)) {
                            $stats['skipped']++;
                            continue;
                        }

                        $settlementBarcode = trim((string) ($row['barcode'] ?? ''));
                        // Önce sipariş no + barkod ile, bulunamazsa sadece sipariş no ile eşleştir.
                        $order = MpOrder::where('order_number', $orderNumber)
                            ->whereIn('period_id', $userPeriodIds)
                            ->when($settlementBarcode !== '', fn($q) => $q->where('barcode', $settlementBarcode))
                            ->first();
                        if (!$order) {
                            $order = MpOrder::where('order_number', $orderNumber)
                                ->whereIn('period_id', $userPeriodIds)
                                ->first();
                        }
                        $orderId = $order ? $order->id : null;
                        
                        $parsedTransactionDate = $this->parseDate($row['transaction_date'] ?? null);
                        $targetPeriodId = $this->resolvePeriodId($parsedTransactionDate, $period);

                        // Settlement'ı siparişin kendi dönemine (eğer varsa) yoksa transaction date ile at
                        $effectivePeriodId = $order ? $order->period_id : $targetPeriodId;

                        $data = [
                            'user_id'          => $authUserId,
                            'period_id'        => $effectivePeriodId,
                            'order_id'         => $orderId,
                            'order_number'     => $orderNumber,
                            'document_number'  => mb_substr(trim($row['document_number'] ?? ''), 0, 100),
                            'transaction_type' => mb_substr($row['transaction_type'] ?? '', 0, 100),
                            'transaction_date' => $parsedTransactionDate,

                            'due_date'         => $this->parseDate($row['due_date'] ?? null),
                            'commission_rate'  => abs($this->parseNumber($row['commission_rate'] ?? 0)),
                            'ty_hakedis'       => $this->parseNumber($row['ty_hakedis'] ?? 0),
                            'seller_hakedis'   => $this->parseNumber($row['seller_hakedis'] ?? 0),
                            'total_amount'     => abs($this->parseNumber($row['total_amount'] ?? 0)),
                            'is_reconciled'    => false,
                        ];

                        // settlement_date belirleme (Bankaya Fiilen Yattığı Tarih):
                        // Trendyol Ödeme Detay Excel'inde ayrı bir "Hakediş Tarihi" sütunu yoktur.
                        // "İşlem Tarihi" (transaction_date) = muhasebe kaydı tarihi (genelde teslimat günü).
                        // Gerçek banka transfer tarihi ise VADE TARİHİ üzerinden Trendyol takvimine göre hesaplanır:
                        //   Vade Pzt/Sal/Çar → Aynı haftanın Perşembesi
                        //   Vade Per/Cum/Cmt/Paz → Takip eden Pazartesi
                        $rawSettlementDate = $this->parseDate($row['settlement_date'] ?? null);
                        if ($rawSettlementDate) {
                            $data['settlement_date'] = $rawSettlementDate;
                        } else {
                            $dueDate = $data['due_date'] ? \Carbon\Carbon::parse($data['due_date']) : null;
                            if ($dueDate) {
                                $dow = $dueDate->dayOfWeekIso; // 1=Pzt ... 7=Paz
                                if (in_array($dow, [1, 2, 3])) {
                                    // Pzt/Sal/Çar → Aynı haftanın Perşembesi
                                    $data['settlement_date'] = $dueDate->copy()->startOfWeek()->addDays(3)->startOfDay();
                                } else {
                                    // Per/Cum/Cmt/Paz → Takip eden Pazartesi
                                    $data['settlement_date'] = $dueDate->copy()->next(\Carbon\Carbon::MONDAY)->startOfDay();
                                }
                            } else {
                                // due_date yoksa transaction_date ile fallback
                                $txType = mb_strtolower($data['transaction_type']);
                                $isPaymentRow = str_contains($txType, 'satış') 
                                    || str_contains($txType, 'satis')
                                    || str_contains($txType, 'iade')
                                    || str_contains($txType, 'ödeme')
                                    || str_contains($txType, 'hakedis')
                                    || str_contains($txType, 'hakediş');
                                $data['settlement_date'] = $isPaymentRow ? $parsedTransactionDate : null;
                            }
                        }

                        // Mükerrer satırları (aynı siparişteki aynı tutarlı 2 ürünü) ayırma mantığı
                        // document_number (Kayıt No) her satır için benzersizdir — bu sayede
                        // qty>1 siparişlerdeki adet-bazlı ödeme satırları birbirini ezmez.
                        $docNumForHash = trim($row['document_number'] ?? '');
                        $dateStrForHash = $data['transaction_date'] ? \Carbon\Carbon::parse($data['transaction_date'])->format('Y-m-d') : '';
                        $amountStrForHash = number_format((float) $data['seller_hakedis'], 2, '.', '');
                        $typeStrForHash = mb_strtolower(trim($data['transaction_type'] ?? 'hakediş'));
                        
                        $hashKey = !empty($docNumForHash)
                            ? "{$orderNumber}_{$docNumForHash}"
                            : "{$orderNumber}_{$dateStrForHash}_{$typeStrForHash}_{$amountStrForHash}";
                        
                        if (!isset($localCounter[$hashKey])) {
                            $localCounter[$hashKey] = 0;
                        }
                        $occurrenceIndex = $localCounter[$hashKey];
                        $localCounter[$hashKey]++;
                        
                        if (isset($existingMap[$hashKey]) && isset($existingMap[$hashKey][$occurrenceIndex])) {
                            // Güncelle
                            $existingRecord = $existingMap[$hashKey][$occurrenceIndex];
                            $existingRecord->update($data);
                            $stats['updated']++;
                        } else {
                            // Yeni oluştur
                            MpSettlement::create($data);
                            $stats['imported']++;
                            
                            // Yeni oluşturduğumuz kaydı aynı çalışmada bir daha ezmemek için local map'e de ekleyebiliriz (opsiyonel)
                            // $existingMap[$hashKey][] = $newRecord; (Gerek yok, loop zaten hash index iterasyonu yapıyor)
                        }

                        // Eğer siparişi bulduysak, eksik verileri OdemeDetay'dan tamamla (Kazan-Kazan)
                        if ($order) {
                            $updates = [];
                            
                            $deliveryDate = $this->parseDate($row['delivery_date'] ?? null);
                            if ($deliveryDate && !$order->delivery_date) {
                                $updates['delivery_date'] = $deliveryDate;
                            }

                            if (!empty($settlementBarcode) && empty($order->barcode)) {
                                $updates['barcode'] = mb_substr($settlementBarcode, 0, 100);
                            }

                            $rawProductName = trim((string) ($row['product_name'] ?? ''));
                            if ($rawProductName !== '' && empty($order->product_name)) {
                                $updates['product_name'] = mb_substr($rawProductName, 0, 255);
                            }

                            $stopaj = $this->parseNumber($row['stopaj'] ?? 0);
                            // Trendyol stopajı eksi yazar, biz pozitif (veya AuditEngine'e uygun) tutalım
                            if ($stopaj != 0 && $order->withholding_tax == 0) {
                                $updates['withholding_tax'] = abs($stopaj);
                            }

                            if (!empty($settlementBarcode) && (empty($order->stock_code) || empty($order->product_name))) {
                                $matchedProduct = MpProduct::where('user_id', $authUserId)
                                    ->where('barcode', $settlementBarcode)
                                    ->first();
                                if ($matchedProduct) {
                                    if (empty($order->stock_code) && !empty($matchedProduct->stock_code)) {
                                        $updates['stock_code'] = mb_substr((string) $matchedProduct->stock_code, 0, 100);
                                    }
                                    if (empty($order->product_name) && !empty($matchedProduct->product_name)) {
                                        $updates['product_name'] = mb_substr((string) $matchedProduct->product_name, 0, 255);
                                    }
                                }
                            }

                            if (!empty($updates)) {
                                $order->update($updates);
                            }
                        }

                        
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

    /**
     * Çoklu sepet satırlarındaki belirli bir alanın tüm satırlarda aynı mı olduğunu kontrol et.
     * Trendyol toplam tutarı her satıra kopyalayarak yazdığında tüm değerler aynı olur → dağıtmamız gerekir.
     * Her satırda farklı değer varsa zaten ürün bazlı verilmiş → dağıtmaya gerek yok.
     */
    protected function allRowsSameValue($rows, string $field): bool
    {
        $values = $rows->map(fn($r) => $this->parseNumber($r[$field] ?? 0))->unique();
        return $values->count() === 1 && $values->first() != 0;
    }

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
    }    /**
     * Excel'den gelen stok kodu boşsa, barkoda göre mp_products tablosundan bul
     */
    protected function resolveStockCode(?string $excelStockCode, ?string $barcode): string
    {
        $code = mb_substr(trim($excelStockCode ?? ''), 0, 100);
        
        if (!empty($code)) {
            return $code;
        }

        if (!empty($barcode)) {
            $userId = \Illuminate\Support\Facades\Auth::id() ?? 1;
            $product = \App\Models\MpProduct::where('user_id', $userId)
                ->where('barcode', $barcode)
                ->first();
                
            if ($product && !empty($product->stock_code)) {
                return $product->stock_code;
            }
        }

        return '';
    }

    /**
     * Excel'den gelen ürün adı boşsa, barkod veya stok koduna göre mp_products tablosundan bul
     */
    protected function resolveProductName(?string $excelName, ?string $barcode, ?string $stockCode): string
    {
        $name = mb_substr(trim($excelName ?? ''), 0, 250);
        
        // Excel'de ad varsa doğrudan döndür
        if (!empty($name)) {
            return $name;
        }

        $userId = \Illuminate\Support\Facades\Auth::id() ?? 1;

        // Barkod üzerinden ara
        if (!empty($barcode)) {
            $product = \App\Models\MpProduct::where('user_id', $userId)
                ->where('barcode', $barcode)
                ->first();
                
            if ($product && !empty($product->product_name)) {
                return $product->product_name;
            }
        }

        // Stok kodu üzerinden ara
        if (!empty($stockCode)) {
            $product = \App\Models\MpProduct::where('user_id', $userId)
                ->where('stock_code', $stockCode)
                ->first();
                
            if ($product && !empty($product->product_name)) {
                return $product->product_name;
            }
        }

        return '';
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
