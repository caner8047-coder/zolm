<?php

namespace App\Console\Commands;

use App\Models\MarketplaceStore;
use App\Models\MpPriceEmergencyStop;
use App\Models\MpPricePilotProduct;
use App\Services\Marketplace\MarketplacePricePilotService;
use App\Services\Marketplace\MarketplacePriceEmergencyStopService;
use Illuminate\Console\Command;

class MarketplacePricePilotCommand extends Command
{
    protected $signature = 'marketplace:price-pilot
                            {action : status, enable-shadow, add-product, remove-product, pause, emergency-stop, report, enable-canary}
                            {store_id : The ID of the marketplace store}
                            {barcode? : Product barcode for add/remove product}
                            {--confirm : Required for enable-canary double confirmation}
                            {--hours=24 : Report duration in hours}';

    protected $description = 'ZOLM Trendyol Buybox Shadow Mode, Pilot and Canary control center';

    public function handle(
        MarketplacePricePilotService $pilotService,
        MarketplacePriceEmergencyStopService $emergencyStopService
    ): int {
        $storeId = (int) $this->argument('store_id');
        $store = MarketplaceStore::find($storeId);

        if (!$store) {
            $this->error("Mağaza (ID: {$storeId}) bulunamadı.");
            return Command::FAILURE;
        }

        $action = $this->argument('action');

        switch ($action) {
            case 'status':
                return $this->showStatus($store, $pilotService, $emergencyStopService);
            case 'enable-shadow':
                return $this->enableShadow($store);
            case 'add-product':
                return $this->addProduct($store, $pilotService);
            case 'remove-product':
                return $this->removeProduct($store, $pilotService);
            case 'pause':
                return $this->pausePilot($store);
            case 'emergency-stop':
                return $this->emergencyStop($store, $emergencyStopService);
            case 'enable-canary':
                return $this->enableCanary($store);
            case 'report':
                return $this->generateReport($store);
            default:
                $this->error("Geçersiz aksiyon: {$action}");
                return Command::FAILURE;
        }
    }

    protected function showStatus(
        MarketplaceStore $store,
        MarketplacePricePilotService $pilotService,
        MarketplacePriceEmergencyStopService $emergencyStopService
    ): int {
        $this->info("=== Mağaza Durumu: {$store->name} ===");
        $this->line("Emergency Stop: " . ($emergencyStopService->isEmergencyStopActive($store->id) ? '🔴 AKTİF' : '🟢 PASİF'));
        
        $pilotProducts = MpPricePilotProduct::where('store_id', $store->id)
            ->where('mode', '!=', 'disabled')
            ->get();
            
        $this->info("Pilot Ürün Sayısı: " . $pilotProducts->count() . " / Maks Limit: " . $pilotService->maxPilotProductLimit($store));
        
        $headers = ['Barkod', 'Mod', 'Eklenme Tarihi'];
        $data = $pilotProducts->map(fn ($p) => [$p->barcode, $p->mode, $p->created_at])->toArray();
        $this->table($headers, $data);

        return Command::SUCCESS;
    }

    protected function enableShadow(MarketplaceStore $store): int
    {
        config(['marketplace.trendyol.shadow_mode_enabled' => true]);
        $this->info("Gölge Mod (Shadow Mode) etkinleştirildi. Fiyat push işlemleri simüle edilecektir.");
        return Command::SUCCESS;
    }

    protected function addProduct(MarketplaceStore $store, MarketplacePricePilotService $pilotService): int
    {
        $barcode = $this->argument('barcode');
        if (!$barcode) {
            $this->error("Lütfen eklenecek ürün barkodunu girin.");
            return Command::FAILURE;
        }

        try {
            $pilotService->addProductToPilot($store, $barcode, 'shadow', 'Artisan CLI added');
            $this->info("Ürün ({$barcode}) başarıyla pilot listesine (Shadow Mod) eklendi.");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function removeProduct(MarketplaceStore $store, MarketplacePricePilotService $pilotService): int
    {
        $barcode = $this->argument('barcode');
        if (!$barcode) {
            $this->error("Lütfen çıkarılacak ürün barkodunu girin.");
            return Command::FAILURE;
        }

        $pilotService->removeProductFromPilot($store->id, $barcode);
        $this->info("Ürün ({$barcode}) pilot listesinden çıkarıldı (devre dışı bırakıldı).");
        return Command::SUCCESS;
    }

    protected function pausePilot(MarketplaceStore $store): int
    {
        MpPricePilotProduct::where('store_id', $store->id)->update(['mode' => 'paused']);
        $this->info("Tüm pilot ürünler 'DURAKLATILDI' (paused) durumuna alındı.");
        return Command::SUCCESS;
    }

    protected function emergencyStop(MarketplaceStore $store, MarketplacePriceEmergencyStopService $emergencyStopService): int
    {
        $emergencyStopService->activateEmergencyStop($store->id, 'Artisan CLI emergency stop');
        $this->error("Acil Durdurma (Emergency Stop) {$store->name} mağazası için tetiklendi! Tüm API fiyat push akışları kesildi.");
        return Command::SUCCESS;
    }

    protected function enableCanary(MarketplaceStore $store): int
    {
        if (!$this->option('confirm')) {
            $this->error("Canary otomasyonunu açmak kritik risk taşır! Lütfen komutu '--confirm' bayrağıyla çalıştırın.");
            return Command::FAILURE;
        }

        config([
            'marketplace.trendyol.automatic_price_actions_enabled' => true,
            'marketplace.trendyol.canary_enabled' => true,
        ]);
        
        $this->warn("=== CANARY OTOMASYONU AKTİF EDİLDİ ===");
        $this->line("Pilot ürünler artık kural sınırları dahilinde otomatik fiyatlandırılacaktır.");
        return Command::SUCCESS;
    }

    protected function generateReport(MarketplaceStore $store): int
    {
        $hours = (int) $this->option('hours');
        $this->info("Son {$hours} saat için Pilot/Shadow KPI Raporu:");
        
        $pilotCount = MpPricePilotProduct::where('store_id', $store->id)->where('mode', '!=', 'disabled')->count();
        $this->line("- Pilot Ürün Sayısı: {$pilotCount}");

        return Command::SUCCESS;
    }
}
