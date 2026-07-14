<?php

namespace App\Console\Commands;

use App\Models\SupportDispatch;
use Illuminate\Console\Command;

class CustomerCareRateLimitReportCommand extends Command
{
    protected $signature = 'customer-care:rate-limit-report {--store= : Store ID}';
    protected $description = 'Kanal limit durumlarını gösterir.';

    public function handle()
    {
        $storeId = $this->option('store');
        $this->info("Rate Limit Raporu Alınıyor... Store: " . ($storeId ?? 'Tümü'));

        $channels = ['whatsapp', 'trendyol', 'hepsiburada', 'n11', 'meta', 'google_reviews', 'web_chat'];
        $channelKeys = [
            'whatsapp' => ['whatsapp'],
            'trendyol' => ['trendyol'],
            'hepsiburada' => ['hepsiburada'],
            'n11' => ['n11'],
            'meta' => ['meta', 'meta_social', 'instagram', 'facebook'],
            'google_reviews' => ['google', 'google_reviews', 'google_business'],
            'web_chat' => ['web_chat', 'chat'],
        ];
        $limits = config('customer-care.rate_limits', [
            'whatsapp' => ['max_attempts' => 100, 'decay_seconds' => 3600],
            'trendyol' => ['max_attempts' => 50, 'decay_seconds' => 3600],
            'meta' => ['max_attempts' => 100, 'decay_seconds' => 3600],
            'google_reviews' => ['max_attempts' => 30, 'decay_seconds' => 3600],
            'web_chat' => ['max_attempts' => 200, 'decay_seconds' => 3600],
        ]);

        foreach ($channels as $chan) {
            $limit = $limits[$chan] ?? ['max_attempts' => 100, 'decay_seconds' => 3600];
            $since = now()->subSeconds($limit['decay_seconds']);

            $query = SupportDispatch::whereHas('channel', function ($q) use ($chan, $channelKeys) {
                $q->where(function ($keyQuery) use ($chan, $channelKeys): void {
                    foreach ($channelKeys[$chan] as $index => $key) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $keyQuery->{$method}(function ($candidate) use ($key): void {
                            $candidate->where('key', $key)
                                ->orWhere('key', 'like', $key . '_%');
                        });
                    }
                });
            })->where('created_at', '>=', $since);

            if ($storeId) {
                $query->whereHas('conversation', function ($q) use ($storeId) {
                    $q->where('store_id', $storeId);
                });
            }

            $count = $query->count();
            $maxAttempts = max(0, (int) $limit['max_attempts']);
            $percentage = $maxAttempts > 0 ? ($count / $maxAttempts) * 100 : 0;

            $this->line("Kanal: " . str_pad($chan, 15) . " Gönderim: {$count}/{$maxAttempts} (%" . number_format($percentage, 1) . ")");
        }

        return 0;
    }
}
