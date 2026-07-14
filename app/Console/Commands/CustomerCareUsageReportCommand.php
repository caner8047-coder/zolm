<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MarketplaceStore;
use App\Services\Support\CustomerCareUsageService;

class CustomerCareUsageReportCommand extends Command
{
    protected $signature = 'customer-care:usage-report {--store= : Mağaza ID\'si} {--json : JSON formatında çıktı verir}';
    protected $description = 'Müşteri İletişim Merkezi mağaza bazlı kota kullanım raporunu üretir.';

    public function handle(): int
    {
        $storeId = $this->option('store');

        if (!$storeId) {
            $this->error('Lütfen --store=ID parametresini belirtin.');
            return 1;
        }

        $store = MarketplaceStore::find($storeId);
        if (!$store) {
            $this->error("Mağaza (ID: {$storeId}) bulunamadı.");
            return 1;
        }

        $usageService = app(CustomerCareUsageService::class);

        $metrics = ['ai_drafts', 'auto_replies', 'agent_replies', 'knowledge_suggestions', 'connected_channels'];
        $reportData = [
            'store_id' => (int)$storeId,
            'store_name' => $store->store_name,
            'period' => now()->format('Y-m'),
            'usage' => [],
        ];

        foreach ($metrics as $metric) {
            $limitCheck = $usageService->checkLimit((int)$storeId, $metric);
            $reportData['usage'][$metric] = [
                'current' => $limitCheck['current'],
                'limit' => $limitCheck['limit'],
                'percentage' => $limitCheck['limit'] > 0 ? round(($limitCheck['current'] / $limitCheck['limit']) * 100, 1) : 0,
                'allowed' => $limitCheck['allowed'],
            ];
        }

        if ($this->option('json')) {
            $this->output->write(json_encode($reportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return 0;
        }

        $this->info("=== Müşteri İletişim Merkezi Kullanım Raporu ===");
        $this->info("Mağaza: {$store->store_name} (ID: {$storeId})");
        $this->info("Dönem: " . $reportData['period']);
        $this->info("------------------------------------------------");

        foreach ($reportData['usage'] as $metric => $data) {
            $limitStr = $data['limit'] === PHP_INT_MAX ? 'Sınırsız' : $data['limit'];
            $pctStr = $data['limit'] === PHP_INT_MAX ? '-' : '%' . $data['percentage'];
            $this->line(sprintf(
                "%-22s: %d / %s (%s) - %s",
                ucfirst(str_replace('_', ' ', $metric)),
                $data['current'],
                $limitStr,
                $pctStr,
                $data['allowed'] ? 'Yeterli Kota' : 'KOTA DOLDU!'
            ));
        }

        return 0;
    }
}
