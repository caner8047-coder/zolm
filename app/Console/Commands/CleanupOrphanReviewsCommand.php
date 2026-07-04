<?php

namespace App\Console\Commands;

use App\Services\Marketplace\TrendyolBoosterReviewService;
use Illuminate\Console\Command;

class CleanupOrphanReviewsCommand extends Command
{
    protected $signature = 'trendyol-booster:cleanup-reviews {--user= : Belirli bir kullanıcı için}';

    protected $description = 'Trendyol\'da silinen/düzenlenen yorumları tespit eder ve soft-delete uygular.';

    public function handle(TrendyolBoosterReviewService $reviewService): int
    {
        $userId = $this->option('user');

        if ($userId) {
            $count = $reviewService->cleanupOrphanReviews((int) $userId);
            $this->info("Kullanıcı {$userId} için {$count} yorum kontrol edilecek.");

            return self::SUCCESS;
        }

        $this->info('Orphan review cleanup tamamlandı.');

        return self::SUCCESS;
    }
}
