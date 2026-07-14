<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MarketplaceStore;
use App\Models\SupportAiRun;
use App\Models\SupportAiCostEvent;
use App\Services\Support\CustomerCareAiProviderHealthService;

class CustomerCareRecomputeAiCostsCommand extends Command
{
    protected $signature = 'customer-care:recompute-ai-costs {--store= : Store ID} {--execute : Persist recomputed costs instead of dry-run}';

    protected $description = 'Yapay zeka çalıştırma maliyetlerini token sayılarına göre yeniden hesaplar';

    public function handle(CustomerCareAiProviderHealthService $healthService): int
    {
        $storeId = $this->option('store');
        $execute = $this->option('execute');

        if (!$storeId) {
            $this->error('Lütfen mağaza ID belirtin: --store=ID');
            return 1;
        }

        $store = MarketplaceStore::find($storeId);
        if (!$store) {
            $this->error("Belirtilen ID ({$storeId}) ile eşleşen bir mağaza bulunamadı.");
            return 1;
        }

        $this->info("=== AI Maliyetlerini Yeniden Hesaplama: {$store->store_name} ===");
        if (!$execute) {
            $this->comment("MOD: DRY-RUN (Değişiklik yapılmayacak)");
        } else {
            $this->warn("MOD: CANLI (Veritabanı güncellenecek)");
        }

        // Fetch AI runs for the store
        $runs = SupportAiRun::where('store_id', $storeId)->get();
        $totalCalculated = 0;
        $totalSum = 0.0;

        foreach ($runs as $run) {
            $model = $run->model ?? 'gemini-1.5-flash';
            $inputTokens = $run->token_in ?? 0;
            $outputTokens = $run->token_out ?? 0;

            if ($inputTokens === 0 && $outputTokens === 0) {
                // Estimate tokens from text length if zero
                $inputTokens = (int) (mb_strlen($run->prompt_raw ?? '') / 4);
                $outputTokens = (int) (mb_strlen($run->response_raw ?? '') / 4);
            }

            $cost = $healthService->calculateCostEstimate($model, $inputTokens, $outputTokens);

            if ($cost !== null) {
                $totalCalculated++;
                $totalSum += $cost;

                if ($execute) {
                    // Update run tokens
                    $run->update([
                        'token_in' => $inputTokens,
                        'token_out' => $outputTokens,
                    ]);

                    // Update or create cost event deterministically via support_ai_run_id (P1-3)
                    SupportAiCostEvent::updateOrCreate([
                        'support_ai_run_id' => $run->id,
                    ], [
                        'store_id' => $storeId,
                        'model' => $model,
                        'provider' => 'Gemini',
                        'input_tokens' => $inputTokens,
                        'output_tokens' => $outputTokens,
                        'cost_estimate' => $cost,
                        'created_at' => $run->created_at,
                    ]);
                }
            }
        }

        $this->info("Toplam Hesaplanan Çalıştırma: {$totalCalculated}");
        $this->info("Toplam Tahmini Maliyet: $" . number_format($totalSum, 6));

        return 0;
    }
}
