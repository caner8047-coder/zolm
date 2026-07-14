<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MarketplaceStore;
use App\Models\SupportChannel;
use App\Models\SupportMessage;
use App\Models\SupportAgentAction;
use App\Models\SupportDispatch;
use App\Services\Support\CustomerCarePilotReadinessService;
use App\Services\Support\CustomerCarePilotMonitorService;
use App\Services\Support\CustomerCareUsageService;
use App\Services\Support\TenantContext;
use Illuminate\Support\Facades\File;

class CustomerCarePilotLaunchReportCommand extends Command
{
    protected $signature = 'customer-care:pilot-launch-report {--store= : Mağaza ID\'si}';
    protected $description = 'Pilot Mağaza lansman raporunu üretir ve Markdown olarak belgeler.';

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

        try {
            $actor = TenantContext::getSystemActor();
            TenantContext::enforceStoreAccess((int) $storeId, $actor);
        } catch (\Throwable $exception) {
            $this->error('Lansman raporu oluşturulamadı: ' . $exception->getMessage());

            return 1;
        }

        $this->info("Mağaza {$store->store_name} için lansman raporu oluşturuluyor...");

        $readinessService = app(CustomerCarePilotReadinessService::class);
        $monitorService = app(CustomerCarePilotMonitorService::class);
        $usageService = app(CustomerCareUsageService::class);

        $readiness = $readinessService->checkReadiness((int) $storeId, $actor);
        $metrics = $monitorService->getStoreMetrics((int) $storeId, $actor);

        $md = "# Pilot Lansman Raporu — Mağaza: {$store->store_name} (ID: {$storeId})\n\n";
        $md .= "**Oluşturulma Tarihi:** " . now()->format('Y-m-d H:i:s') . "\n";
        $md .= "**Genel Durum:** " . ($readiness['ready'] ? "🚀 PİLOT LAUNCH HAZIR" : "⚠️ KANARYA GEÇİT ENGELLERİ MEVCUT") . "\n\n";

        $md .= "## 1. Hazırlık Durumu (Readiness Checks)\n\n";
        $md .= "| Kriter | Durum | Detay |\n";
        $md .= "|---|---|---|\n";
        foreach ($readiness['checks'] as $key => $check) {
            $statusStr = $check['status'] === 'passed' ? "✅ PASS" : ($check['status'] === 'warning' ? "⚠️ WARN" : "❌ FAIL");
            $sanitizedDetail = $this->sanitizeText($check['detail']);
            $md .= "| {$check['label']} | {$statusStr} | {$sanitizedDetail} |\n";
        }
        $md .= "\n";

        $md .= "## 2. Devre Kesici (Circuit Breaker)\n\n";
        $statusCB = ($metrics['circuit_breaker_status'] ?? 'closed') === 'open' ? "🔴 OPEN (Tetiklendi)" : "🟢 CLOSED (Normal)";
        $md .= "- **CB Durumu:** {$statusCB}\n";
        $md .= "- **Son 15 Dakikadaki Hatalar:** " . ($metrics['dispatch_failures_15m'] ?? 0) . "\n";
        $md .= "- **Son 15 Dakikadaki Politika Blokajları:** " . ($metrics['policy_blocks_15m'] ?? 0) . "\n";
        $md .= "- **Blokaj Sebebi:** " . $this->sanitizeText($metrics['trip_reason'] ?? 'Yok') . "\n\n";

        $md .= "## 3. Kota ve Kullanım Sınırları (Quotas)\n\n";
        $md .= "| Metrik | Harcanan | Limit | Durum |\n";
        $md .= "|---|---|---|---|\n";
        $quotaMetrics = ['ai_drafts', 'auto_replies', 'connected_channels', 'knowledge_suggestions'];
        foreach ($quotaMetrics as $metric) {
            $limitCheck = $usageService->checkLimit($storeId, $metric);
            $statusStr = $limitCheck['allowed'] ? "✅ Limit Altı" : "❌ KOTA AŞILDI";
            $limitVal = $limitCheck['limit'] === PHP_INT_MAX ? 'Sınırsız' : $limitCheck['limit'];
            $md .= "| " . ucfirst(str_replace('_', ' ', $metric)) . " | {$limitCheck['current']} | {$limitVal} | {$statusStr} |\n";
        }
        $md .= "\n";

        $md .= "## 4. Son 24 Saatlik Operasyon Metrikleri\n\n";
        $last24hDrafts = SupportMessage::whereHas('conversation', fn($q) => $q->where('store_id', $storeId))
            ->where('sender_type', 'ai')
            ->where('delivery_status', 'draft')
            ->where('created_at', '>=', now()->subDay())
            ->count();
        $last24hAutoReplies = SupportMessage::whereHas('conversation', fn($q) => $q->where('store_id', $storeId))
            ->where('sender_type', 'ai')
            ->where('direction', 'outbound')
            ->where('delivery_status', 'sent')
            ->where('created_at', '>=', now()->subDay())
            ->count();
        $last24hPolicyBlocks = SupportAgentAction::whereHas('conversation', fn($q) => $q->where('store_id', $storeId))
            ->where('action', 'policy_block')
            ->where('created_at', '>=', now()->subDay())
            ->count();
        $last24hHandoffs = SupportAgentAction::whereHas('conversation', fn($q) => $q->where('store_id', $storeId))
            ->where('action', 'human_handoff')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $md .= "- **AI Taslak Sayısı:** {$last24hDrafts}\n";
        $md .= "- **Otomatik Cevap Sayısı:** {$last24hAutoReplies}\n";
        $md .= "- **Politika Blokajı:** {$last24hPolicyBlocks}\n";
        $md .= "- **Temsilciye Aktarma (Handoff):** {$last24hHandoffs}\n\n";

        $md .= "## 5. Hata Detayları\n\n";
        $latestErrors = SupportDispatch::where('status', 'failed')
            ->whereHas('conversation', fn($q) => $q->where('store_id', $storeId))
            ->latest()
            ->limit(5)
            ->pluck('last_error')
            ->filter()
            ->toArray();

        if (empty($latestErrors)) {
            $md .= "Son dönemde herhangi bir outbox gönderim hatası kaydedilmedi.\n";
        } else {
            foreach ($latestErrors as $idx => $err) {
                // Hataları 150 karaktere sığdırıp maskeliyoruz
                $shortError = mb_substr($err, 0, 150);
                if (mb_strlen($err) > 150) {
                    $shortError .= '...';
                }
                $sanitizedError = $this->sanitizeText($shortError);
                $md .= ($idx + 1) . ". {$sanitizedError}\n";
            }
        }
        $md .= "\n";

        // Route & Command Inventory (P2-1)
        $md .= "## 6. Route & Command Inventory\n\n";
        $md .= "### Aktif Rotalar (Routes)\n";
        $md .= "- **customer-care.onboarding:** `/customer-care/onboarding` (Guided Setup Wizard)\n";
        $md .= "- **customer-care.admin:** `/customer-care/admin` (Yönetici Kontrol Merkezi)\n";
        $md .= "- **customer-care.inbox:** `/customer-care/inbox` (Temsilci Çalışma Ekranı)\n";
        $md .= "- **customer-care.analytics:** `/customer-care/analytics` (Metrik ve Analizler)\n";
        $md .= "- **customer-care.settings:** `/customer-care/settings` (Modül Ayarları)\n\n";

        $md .= "### Konsol Komutları (Artisan Commands)\n";
        $md .= "- `customer-care:pilot-launch-report`: Pilot Mağaza lansman raporunu üretir.\n";
        $md .= "- `customer-care:usage-report`: Mağaza bazlı kota kullanım raporunu üretir.\n";
        $md .= "- `customer-care:circuit-breaker`: Manuel CB Override kontrolü sağlar.\n";
        $md .= "- `customer-care:generate-knowledge-suggestions`: Bilgi bankası önerilerini analiz eder.\n";
        $md .= "- `customer-care:run-golden-eval`: Golden dataset değerlendirmesini çalıştırır.\n";
        $md .= "- `customer-care:anonymize`: KVKK PII maskeleme ve temizliğini tetikler.\n\n";

        // Dedicated Golden Evaluation Summary (P2-1)
        $md .= "## 7. Golden Evaluation Summary\n\n";
        $evalService = app(\App\Services\Support\AI\CustomerCareEvalService::class);
        $lastEval = $evalService->getLatestGoldenEval($storeId);

        if ($lastEval) {
            $score = (int)($lastEval['average_score'] ?? 0);
            $passed = (bool)($lastEval['passed_eval_gate'] ?? false);
            $runAtStr = $lastEval['run_at'] ?? 'Bilinmiyor';

            $isStale = true;
            $maxAgeDays = (int) config('customer-care.golden_eval_max_age_days', 7);
            if ($lastEval['run_at']) {
                $runAt = \Carbon\Carbon::parse($lastEval['run_at']);
                $isStale = $runAt->lt(now()->subDays($maxAgeDays));
            }

            $md .= "- **Son Değerlendirme Skoru:** %{$score}\n";
            $md .= "- **Değerlendirme Tarihi:** {$runAtStr}\n";
            $md .= "- **Kalite Kapısı Barajı (>= 80):** " . ($passed ? "✅ GEÇTİ" : "❌ KALDI") . "\n";
            $md .= "- **Süre Aşımı (Stale - Max {$maxAgeDays} Gün):** " . ($isStale ? "❌ SÜRESİ AŞILMIŞ (Yeniden Çalıştırılmalı)" : "✅ GÜNCEL") . "\n";
        } else {
            $md .= "Bu mağaza için henüz yapılmış bir Golden Dataset değerlendirme kaydı bulunmamaktadır.\n";
        }

        $dirPath = rtrim(
            (string) config('customer-care.report_directory', base_path('docs/customer-care')),
            DIRECTORY_SEPARATOR
        );

        if ($dirPath === '') {
            $this->error('Lansman raporu dizini yapılandırılmamış.');

            return 1;
        }

        if (!File::exists($dirPath)) {
            File::makeDirectory($dirPath, 0755, true);
        }

        $filePath = "{$dirPath}/pilot-launch-report-store-{$storeId}.md";
        File::put($filePath, $md);

        $this->info("Rapor oluşturuldu: {$filePath}");

        return 0;
    }

    /**
     * Serbest metinleri PII maskeler, XML temizler ve markdown tablosu koruması yapar.
     */
    protected function sanitizeText(string $text): string
    {
        $redactor = app(\App\Services\Support\Security\PiiRedactor::class);
        
        // 1. Mask PII
        $masked = $redactor->maskPii($text);
        
        // 2. Clean XML control characters
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $masked);
        
        // 3. Normalize pipe and newlines to protect markdown tables/formatting
        $normalized = str_replace(['|', "\r", "\n"], ['/', ' ', ' '], $clean);
        
        return trim(preg_replace('/\s+/', ' ', $normalized));
    }
}
