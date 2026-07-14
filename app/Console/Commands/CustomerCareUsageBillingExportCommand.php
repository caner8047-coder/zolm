<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MarketplaceStore;
use App\Services\Support\CustomerCareEntitlementService;
use App\Services\Support\TenantContext;

class CustomerCareUsageBillingExportCommand extends Command
{
    protected $signature = 'customer-care:usage-billing-export
        {--store= : Store ID}
        {--month= : Rapor ayı (Format: YYYY-MM)}
        {--output-file= : Raporun kaydedileceği dosya yolu (opsiyonel)}';

    protected $description = 'Belirli bir mağazanın aylık kullanım ve entitlement loglarını faturalama için dışa aktarır';

    public function handle(): int
    {
        $storeId = $this->option('store');
        $month = $this->option('month');
        $outputFile = $this->option('output-file');

        if (!$storeId || !$month) {
            $this->error('--store=ID ve --month=YYYY-MM parametreleri zorunludur.');
            return 1;
        }

        $store = MarketplaceStore::find($storeId);
        if (!$store) {
            $this->error("Mağaza bulunamadı: ID={$storeId}");
            return 1;
        }

        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error("Geçersiz ay formatı. YYYY-MM olmalıdır (Örn: 2026-07).");
            return 1;
        }

        $this->info("Mağaza '{$store->store_name}' için {$month} ayı faturalama verileri derleniyor...");

        $service = app(CustomerCareEntitlementService::class);
        $csv = $service->generateBillingExport((int) $storeId, $month, TenantContext::getSystemActor());

        if ($outputFile) {
            // Dosyaya kaydet
            try {
                file_put_contents($outputFile, $csv);
                $this->info("Rapor başarıyla kaydedildi: {$outputFile}");
            } catch (\Throwable $e) {
                $this->error("Dosyaya kaydetme başarısız: " . $e->getMessage());
                return 1;
            }
        } else {
            // Ekrana yaz
            $this->line($csv);
        }

        return 0;
    }
}
