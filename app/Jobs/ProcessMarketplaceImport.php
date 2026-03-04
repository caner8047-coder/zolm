<?php

namespace App\Jobs;

use App\Models\MpPeriod;
use App\Services\MarketplaceImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Pazaryeri Excel dosyasını arka planda işle.
 *
 * Büyük dosyalar (3+ MB, on binlerce satır) için:
 * - PHP bellek / zaman aşımına takılmaz
 * - Cache ile kullanıcıya durum bildirir (polling)
 * - Hata durumunda loglama yapar
 */
class ProcessMarketplaceImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;  // 5 dakika
    public int $tries   = 1;   // Tek deneme (finansal veri — dup risk)

    protected int    $periodId;
    protected int    $userId;
    protected string $importType;
    protected string $tempFilePath;
    protected string $originalName;

    /**
     * Cache key formatı: mp_import_{userId}_{periodId}
     */
    public function __construct(
        int    $periodId,
        int    $userId,
        string $importType,
        string $tempFilePath,
        string $originalName
    ) {
        $this->periodId     = $periodId;
        $this->userId       = $userId;
        $this->importType   = $importType;
        $this->tempFilePath = $tempFilePath;
        $this->originalName = $originalName;
    }

    public function handle(): void
    {
        $cacheKey = $this->cacheKey();

        try {
            // Başladığını bildir
            Cache::put($cacheKey, [
                'status'  => 'processing',
                'message' => 'Dosya işleniyor...',
                'type'    => $this->importType,
            ], 600); // 10 dk TTL

            $period  = MpPeriod::findOrFail($this->periodId);
            $service = new MarketplaceImportService();

            // Get correct path from Storage facade
            $fullPath = Storage::disk('local')->path($this->tempFilePath);

            if (!file_exists($fullPath)) {
                throw new \Exception("Geçici dosya bulunamadı: {$fullPath}");
            }

            $fakeFile = new \Illuminate\Http\UploadedFile(
                $fullPath,
                $this->originalName,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true // test mode = skip validation
            );

            $period->update(['status' => 'processing']);

            $stats = match ($this->importType) {
                'orders'       => $service->importOrders($fakeFile, $period),
                'transactions' => $service->importTransactions($fakeFile, $period),
                'stopaj'       => $service->importWithholdingTax($fakeFile, $period),
                'invoices'     => $service->importInvoices($fakeFile, $period),
                'settlements'  => $service->importSettlements($fakeFile, $period),
                default        => throw new \Exception("Bilinmeyen import tipi: {$this->importType}"),
            };

            // Import bilgisini dönem'e kaydet
            $files = $period->import_files ?? [];
            $files[$this->importType] = [
                'name'  => $this->originalName,
                'date'  => now()->format('d.m.Y H:i'),
                'stats' => $stats,
            ];
            $period->update([
                'import_files' => $files,
                'status'       => 'completed',
            ]);

            $imported = $stats['imported'] ?? $stats['matched'] ?? 0;
            $updated  = $stats['updated'] ?? 0;
            $errors   = $stats['errors'] ?? [];

            Cache::put($cacheKey, [
                'status'   => 'completed',
                'message'  => "✅ Başarılı! {$imported} kayıt eklendi, {$updated} güncellendi.",
                'type'     => $this->importType,
                'stats'    => $stats,
                'errors'   => $errors,
            ], 600);

            Log::info('MP Import Job tamamlandı', [
                'period_id' => $this->periodId,
                'type'      => $this->importType,
                'imported'  => $imported,
                'updated'   => $updated,
            ]);

        } catch (\Exception $e) {
            Log::error('MP Import Job hatası', [
                'period_id' => $this->periodId,
                'type'      => $this->importType,
                'error'     => $e->getMessage(),
            ]);

            Cache::put($cacheKey, [
                'status'  => 'failed',
                'message' => "❌ Hata: " . $e->getMessage(),
                'type'    => $this->importType,
            ], 600);

            // Dönem durumunu güncelle
            MpPeriod::where('id', $this->periodId)->update(['status' => 'error']);
        } finally {
            // Temp dosyayı temizle
            Storage::disk('local')->delete($this->tempFilePath);
        }
    }

    protected function cacheKey(): string
    {
        return "mp_import_{$this->userId}_{$this->periodId}";
    }
}
