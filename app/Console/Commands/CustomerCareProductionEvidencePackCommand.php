<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SupportChannel;
use App\Models\SupportConnectorCertificationRun;
use App\Models\SupportSecurityFinding;
use App\Services\Support\CustomerCareProductionReadinessService;
use App\Services\Support\TenantContext;

class CustomerCareProductionEvidencePackCommand extends Command
{
    protected $signature = 'customer-care:production-evidence-pack {--store= : Mağaza ID}';
    protected $description = 'Üretim canlıya geçiş öncesi güvenlik, sertifikasyon ve hazırlık kanıt paketini (Evidence Pack) üretir';

    public function handle(): int
    {
        $storeId = $this->option('store');

        if (!$storeId) {
            $this->error("Mağaza ID belirtmek zorunludur. Örn: --store=1");
            return 1;
        }

        $this->info("Canlıya Geçiş Hazırlık Kanıt Paketi Hazırlanıyor...");
        
        try {
            $actor = TenantContext::getSystemActor();
            $run = app(CustomerCareProductionReadinessService::class)
                ->checkReadiness((int) $storeId, $actor);
        } catch (\Throwable $exception) {
            $this->error('Kanıt paketi üretilemedi: ' . $exception->getMessage());

            return 1;
        }

        $channels = SupportChannel::where('store_id', $storeId)->get();
        $certificationRuns = SupportConnectorCertificationRun::where('store_id', $storeId)->get();
        $unresolvedCriticalFindings = SupportSecurityFinding::where('store_id', $storeId)
            ->where('severity', 'critical')
            ->whereIn('status', ['open', 'acknowledged'])
            ->count();

        $this->line("==================================================");
        $this->line("# ÜRETİM CANLIYA GEÇİŞ KANIT RAPORU");
        $this->line("Mağaza ID: {$storeId}");
        $this->line("Denetim Zamanı: " . now()->toIso8601String());
        $this->line("Hazırlık Skoru: {$run->readiness_score}/100");
        $this->line("Durum: " . strtoupper($run->status));
        $this->line("--------------------------------------------------");
        $this->line("## 1. AKTİF KANALLAR VE SERTİFİKASYON");
        
        if ($channels->isEmpty()) {
            $this->line("Tanımlı kanal bulunamadı.");
        } else {
            foreach ($channels as $channel) {
                $cert = $certificationRuns->firstWhere('channel_key', $channel->key);
                $certStatus = $cert ? strtoupper($cert->status) : 'YOK';
                $this->line("- Kanal: {$channel->key} | Etkin: " . ($channel->is_enabled ? 'Evet' : 'Hayır') . " | Sertifikasyon: {$certStatus}");
            }
        }

        $this->line("--------------------------------------------------");
        $this->line("## 2. GÜVENLİK VE UYUMLULUK");
        $this->line("- Çözülmemiş Kritik Güvenlik Bulguları: {$unresolvedCriticalFindings}");
        $this->line("- Uyum Merkezi Bayrağı: " . (config('customer-care.compliance_enabled', false) ? 'AKTİF' : 'KAPALI'));
        $this->line("- İki Aşamalı Onay Bayrağı: " . (config('customer-care.governance_enabled', false) ? 'AKTİF' : 'KAPALI'));
        $failedChecks = $run->failed_checks_json ?? [];
        $this->line("- Başarısız Hazırlık Kontrolleri: " . (empty($failedChecks) ? 'Yok' : implode(', ', $failedChecks)));
        $this->line("==================================================");

        $this->info("Kanıt paketi üretildi.");

        return $run->status === 'ready' ? 0 : 2;
    }
}
