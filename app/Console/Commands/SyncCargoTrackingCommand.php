<?php

namespace App\Console\Commands;

use App\Models\Shipment;
use App\Services\Cargo\CargoCarrierManager;
use App\Services\Cargo\CargoShipmentService;
use App\Services\Cargo\WooCommerceSuratTrackingSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SyncCargoTrackingCommand extends Command
{
    protected $signature = 'cargo:sync-tracking
        {--limit=100 : Tek çalıştırmada yenilenecek maksimum gönderi}
        {--stale-minutes=120 : Son sorgudan sonra geçmesi gereken dakika}
        {--woocommerce-lookback-days=14 : WooCommerce paketlerini Sürat raporunda geriye dönük kaç gün arayacağı}
        {--skip-woocommerce-match : WooCommerce paketlerini Sürat raporundan otomatik eşleştirmeyi atla}';

    protected $description = 'Sürücüsü etkin kargo gönderilerini ve WooCommerce Sürat otomatik takip eşleşmelerini yeniler.';

    public function handle(
        CargoShipmentService $shipmentService,
        CargoCarrierManager $carrierManager,
        WooCommerceSuratTrackingSyncService $wooCommerceSuratTrackingSyncService,
    ): int {
        if (! Schema::hasTable('shipments')) {
            $this->warn('shipments tablosu bulunamadı. Önce kargo operasyon migrationlarını çalıştırın.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $staleMinutes = max(5, (int) $this->option('stale-minutes'));

        $shipments = Shipment::query()
            ->active()
            ->whereIn('carrier_code', $carrierManager->connectorCodes())
            ->where(function ($query) {
                $query->whereNotNull('tracking_number')
                    ->orWhereNotNull('barcode');
            })
            ->where(function ($query) use ($staleMinutes) {
                $query->whereNull('last_tracked_at')
                    ->orWhere('last_tracked_at', '<=', now()->subMinutes($staleMinutes));
            })
            ->orderByRaw('last_tracked_at IS NULL DESC')
            ->oldest('last_tracked_at')
            ->limit($limit)
            ->get();

        $updated = 0;
        $failed = 0;

        foreach ($shipments as $shipment) {
            try {
                $shipmentService->refreshTracking($shipment);
                $updated++;
            } catch (Throwable $exception) {
                $failed++;
                $shipment->forceFill([
                    'last_error' => $exception->getMessage(),
                    'last_tracked_at' => now(),
                ])->save();

                $this->warn("Gönderi #{$shipment->id} yenilenemedi: {$exception->getMessage()}");
            }
        }

        $this->info("Kargo takip senkronu tamamlandı. Güncellenen: {$updated}, Hata: {$failed}");

        if (! $this->option('skip-woocommerce-match')) {
            $wooSummary = $wooCommerceSuratTrackingSyncService->sync([
                'limit' => $limit,
                'lookback_days' => (int) $this->option('woocommerce-lookback-days'),
                'stale_minutes' => $staleMinutes,
                'archive_report' => false,
            ]);

            $failed += (int) ($wooSummary['failed'] ?? 0);

            $this->info(sprintf(
                'WooCommerce Sürat eşleştirme tamamlandı. Taranan: %d, Eşleşen: %d, Güncellenen: %d, Belirsiz: %d, Bulunamayan: %d, Hata: %d',
                (int) ($wooSummary['scanned'] ?? 0),
                (int) ($wooSummary['matched'] ?? 0),
                (int) ($wooSummary['updated'] ?? 0),
                (int) ($wooSummary['ambiguous'] ?? 0),
                (int) ($wooSummary['unmatched'] ?? 0),
                (int) ($wooSummary['failed'] ?? 0),
            ));
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
