<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStore;
use App\Services\ExcelService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarketplaceExcelFinanceImportService
{
    protected ExcelService $excelService;
    protected MarketplaceFinancialSyncService $syncService;

    protected array $columnAliases = [
        'order_number' => ['Sipariş No', 'Siparis No', 'Order No', 'Order Number', 'Sipariş Numarası', 'Siparis Numarasi'],
        'settlement_date' => ['Ödeme Tarihi', 'Hakediş Tarihi', 'Odeme Tarihi', 'Hakedis Tarihi', 'Vade Tarihi', 'Payment Date', 'Settlement Date'],
        'amount' => ['Net Hakediş', 'Net Tutar', 'Hakediş Tutarı', 'Net Amount', 'Hakedis Tutari', 'Tahmini Hakediş', 'Net Hakedis', 'Satıcı Hakediş', 'Satici Hakedis', 'Ödenecek Tutar'],
        'event_type' => ['İşlem Tipi', 'Islem Tipi', 'Fiş Türü', 'Transaction Type', 'İşlem Türü', 'Kategori', 'Açıklama', 'Aciklama'],
        'document_number' => ['Kayıt No', 'Belge No', 'Fatura No', 'Document Number', 'Dekont No'],
        'currency' => ['Para Birimi', 'Döviz Türü', 'Currency', 'Doviz'],
    ];

    public function __construct()
    {
        $this->excelService = new ExcelService();
        $this->syncService = new MarketplaceFinancialSyncService();
    }

    public function import(MarketplaceStore $store, UploadedFile $file): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        try {
            $rows = $this->excelService->readExcel($file);
            $mapped = $this->excelService->mapColumns($rows, $this->columnAliases);

            DB::beginTransaction();

            $eventsToSync = [];

            foreach ($mapped as $index => $row) {
                try {
                    $orderNumber = trim($row['order_number'] ?? '');
                    if ($orderNumber === '') {
                        continue;
                    }

                    $amountStr = $row['amount'] ?? 0;
                    $amount = $this->excelService->parseNumber($amountStr);
                    
                    if ($amount == 0) {
                        continue; // No financial movement
                    }

                    $date = $this->excelService->parseDate($row['settlement_date'] ?? null) 
                        ?: $this->excelService->parseDate($row['event_date'] ?? null)
                        ?: now();

                    $externalEventId = md5(
                        $orderNumber . '|' . 
                        ($row['event_type'] ?? 'hakedis') . '|' . 
                        $amount . '|' . 
                        $date->format('Y-m-d') . '|' .
                        ($row['document_number'] ?? $index)
                    );

                    $eventType = 'settlement'; // default
                    $rawType = mb_strtolower(trim($row['event_type'] ?? ''));
                    if (str_contains($rawType, 'kargo')) {
                        $eventType = 'cargo';
                    } elseif (str_contains($rawType, 'komisyon')) {
                        $eventType = 'commission';
                    } elseif (str_contains($rawType, 'ceza')) {
                        $eventType = 'penalty';
                    } elseif (str_contains($rawType, 'iade')) {
                        $eventType = 'refund';
                    }

                    $eventsToSync[] = [
                        'order_number' => $orderNumber,
                        'event_source' => 'excel_import',
                        'external_event_id' => $externalEventId,
                        'event_type' => $eventType,
                        'reference_number' => $row['document_number'] ?? null,
                        'settlement_date' => $date,
                        'amount' => abs($amount),
                        'currency' => trim($row['currency'] ?? 'TRY') ?: 'TRY',
                        'direction' => $amount < 0 ? 'debit' : 'credit',
                        'status' => 'posted',
                        'notes' => $row['event_type'] ?? 'Excel Import',
                        'raw_payload' => $row,
                    ];

                } catch (\Exception $e) {
                    $stats['errors'][] = "Satır " . ($index + 2) . ": " . $e->getMessage();
                }
            }

            if (!empty($eventsToSync)) {
                $syncStats = $this->syncService->sync($store, $eventsToSync);
                $stats['created'] += $syncStats['created'];
                $stats['updated'] += $syncStats['updated'];
                $stats['skipped'] += $syncStats['skipped'];
            }

            DB::commit();

            Log::info('Excel Finance Import: Completed', $stats);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Excel Finance Import: Error', ['error' => $e->getMessage()]);
            throw $e;
        }

        return $stats;
    }
}
