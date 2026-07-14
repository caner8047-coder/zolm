<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Support\AI\CustomerCareEvalService;
use App\Services\Support\AI\CustomerCareAiProviderInterface;
use App\Models\MarketplaceStore;
use App\Services\Support\TenantContext;

class RunGoldenEvalCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customer-care:run-golden-eval {--store= : Değerlendirilecek mağaza ID\'si} {--language=tr : Onaylı dataset dili}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Müşteri İletişim Merkezi golden dataset değerlendirmesini çalıştırır ve ledger\'a kaydeder';

    /**
     * Execute the console command.
     */
    public function handle(CustomerCareEvalService $evalService, CustomerCareAiProviderInterface $provider): int
    {
        $storeId = $this->option('store');

        if (!$storeId) {
            $this->error('Lütfen değerlendirilecek mağaza ID\'sini belirtin: --store=ID');
            return 1;
        }

        $store = MarketplaceStore::find($storeId);
        if (!$store) {
            $this->error("Belirtilen ID ({$storeId}) ile eşleşen bir mağaza bulunamadı.");
            return 1;
        }

        $this->info("=== {$store->store_name} ({$store->marketplace}) Golden Dataset Değerlendirmesi Başlatılıyor ===");

        $language = strtolower((string) $this->option('language'));
        $systemActor = TenantContext::getSystemActor();
        $result = $evalService->runGoldenDatasetEval(
            (int) $storeId,
            $provider,
            $systemActor->id,
            "{$language}-local-v1",
            $language,
            $systemActor
        );

        $headers = ['Kategori', 'Soru', 'Skor', 'Durum'];
        $rows = [];

        foreach ($result['details'] as $detail) {
            $rows[] = [
                $detail['category'],
                $detail['question'],
                $detail['score'],
                strtoupper($detail['status'])
            ];
        }

        $this->table($headers, $rows);

        $avgScore = $result['average_score'];
        $passed = $result['passed_eval_gate'];

        if ($passed) {
            $this->info("\n✅ BAŞARILI: Ortalama skor %{$avgScore} (Hedef >= 80).");
        } else {
            $this->error("\n❌ BAŞARISIZ: Ortalama skor %{$avgScore} (Hedef >= 80).");
        }

        return 0;
    }
}
