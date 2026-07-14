<?php

namespace App\Services\Support;

use App\Models\SupportSecurityAuditRun;
use App\Models\SupportSecurityFinding;
use App\Models\SupportSecurityEvidenceItem;
use App\Models\MarketplaceStore;
use App\Models\User;
use App\Services\Support\TenantContext;
use App\Services\Support\Security\SupportRbacService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class CustomerCareSecurityService
{
    /**
     * Güvenlik denetimi çalıştırır.
     * Dry-run: kayıt oluşturur ama mutasyon yapmaz.
     */
    public function runAudit(int $storeId, bool $dryRun = true, ?User $user = null): SupportSecurityAuditRun
    {
        $user = $user ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $user);
        if (!$dryRun) {
            app(SupportRbacService::class)->enforcePermission($user, $storeId, 'run_compliance');
        }

        $runData = [
            'store_id'     => $storeId,
            'status'       => 'running',
            'is_dry_run'   => $dryRun,
            'triggered_by' => $user->id,
            'started_at'   => now(),
        ];

        $run = $dryRun ? new SupportSecurityAuditRun($runData) : SupportSecurityAuditRun::create($runData);

        $findingsList = [];
        $evidenceList = [];

        // --- Kontrol 1: Route Feature Flag Coverage ---
        $protectedRoutes = [
            'customer-care.home', 'customer-care.inbox', 'customer-care.admin',
            'customer-care.quality', 'customer-care.ops',
            'customer-care.governance', 'customer-care.compliance',
            'customer-care.launch', 'customer-care.reconciliation',
            'customer-care.releases', 'customer-care.reliability',
            'customer-care.success', 'customer-care.experiments',
            'customer-care.security',
        ];

        $registeredRouteNames = collect(Route::getRoutes())
            ->map(fn($r) => $r->getName())
            ->filter()
            ->values()
            ->toArray();

        foreach ($protectedRoutes as $routeName) {
            if (!in_array($routeName, $registeredRouteNames)) {
                $findingsList[] = [
                    'category'    => 'route_flag',
                    'severity'    => 'medium',
                    'title'       => "Beklenen rota kayıtlı değil: {$routeName}",
                    'description' => 'Feature flag koruması yapılamıyor.',
                ];
            }
        }

        $missingRoutes = array_values(array_diff($protectedRoutes, $registeredRouteNames));
        $routeEvData = [
            'expected_count'   => count($protectedRoutes),
            'registered_count' => count(array_intersect($protectedRoutes, $registeredRouteNames)),
            'missing_routes' => $missingRoutes,
        ];
        $routeResult = empty($missingRoutes) ? 'pass' : 'fail';
        if ($dryRun) {
            $evidenceList[] = new SupportSecurityEvidenceItem([
                'control_name'            => 'route_coverage',
                'result'                  => $routeResult,
                'evidence_data_encrypted' => Crypt::encryptString(json_encode($routeEvData, JSON_UNESCAPED_UNICODE)),
            ]);
        } else {
            SupportSecurityEvidenceItem::createEncrypted($run->id, 'route_coverage', $routeResult, $routeEvData);
        }

        // --- Kontrol 2: Secret Şifreleme ---
        $secretIssueFound = false;
        $store = MarketplaceStore::find($storeId);
        if ($store) {
            $outboundChannels = DB::table('support_channels')
                ->where('store_id', $storeId)
                ->where('key', 'webhook_outbound')
                ->get();
            foreach ($outboundChannels as $channel) {
                $config = json_decode((string) ($channel->config_json ?? ''), true) ?: [];
                $secret = (string) ($config['webhook_secret'] ?? '');
                $requiresSecret = (bool) $channel->is_enabled || !empty($config['webhook_url']);
                if ($requiresSecret && $secret === '') {
                    $secretIssueFound = true;
                    $findingsList[] = [
                        'category' => 'secret_encryption',
                        'severity' => 'critical',
                        'title' => "Outbound webhook kanalı ID={$channel->id} için imzalama anahtarı eksik.",
                        'description' => 'Etkin webhook çıkışı secretsız çalıştırılamaz.',
                    ];
                    continue;
                }
                if ($secret !== '') {
                    try {
                        Crypt::decryptString($secret);
                    } catch (\Throwable) {
                        $secretIssueFound = true;
                        $findingsList[] = [
                            'category'    => 'secret_encryption',
                            'severity'    => 'critical',
                            'title'       => "Outbound webhook kanalı ID={$channel->id} için secret şifrelenmemiş görünüyor.",
                            'description' => 'Plaintext secret canlı sistemde güvenlik riski oluşturur.',
                        ];
                    }
                }
            }

            $connections = DB::table('integration_connections')->where('store_id', $storeId)->get();
            foreach ($connections as $connection) {
                foreach (['credentials_encrypted', 'webhook_secret'] as $column) {
                    $encryptedValue = (string) ($connection->{$column} ?? '');
                    if ($encryptedValue === '') {
                        continue;
                    }
                    try {
                        Crypt::decryptString($encryptedValue);
                    } catch (\Throwable) {
                        $secretIssueFound = true;
                        $findingsList[] = [
                            'category' => 'secret_encryption',
                            'severity' => 'critical',
                            'title' => "Integration connection ID={$connection->id} için {$column} şifreli değil.",
                            'description' => 'Credential alanları uygulama şifrelemesiyle saklanmalıdır.',
                        ];
                    }
                }
            }
        }

        $secretEvData = [
            'control' => 'webhook_secret_encryption_check',
            'issues_found' => $secretIssueFound,
            'note'    => 'Ham secret bu kanıta eklenmemiştir.',
        ];
        $secretResult = $secretIssueFound ? 'fail' : 'pass';
        if ($dryRun) {
            $evidenceList[] = new SupportSecurityEvidenceItem([
                'control_name'            => 'secret_encryption',
                'result'                  => $secretResult,
                'evidence_data_encrypted' => Crypt::encryptString(json_encode($secretEvData, JSON_UNESCAPED_UNICODE)),
            ]);
        } else {
            SupportSecurityEvidenceItem::createEncrypted($run->id, 'secret_encryption', $secretResult, $secretEvData);
        }

        // --- Kontrol 3: Provider Fail-Closed ---
        $provider = mb_strtolower((string) config('customer-care.default_ai_provider', 'gemini'));
        $aiKey = match ($provider) {
            'gemini', 'geminiprovider' => config('services.gemini.api_key'),
            'groq', 'groqprovider' => config('services.groq.api_key'),
            default => null,
        };
        if (empty($aiKey)) {
            $findingsList[] = [
                'category'    => 'provider_fail_closed',
                'severity'    => 'high',
                'title'       => 'AI provider API anahtarı yapılandırılmamış.',
                'description' => 'Yapılandırılmamış provider ile otomatik yanıt çalışmamalıdır.',
            ];
        }

        $providerEvData = [
            'provider' => $provider,
            'api_key_configured' => !empty($aiKey),
        ];
        $providerResult = empty($aiKey) ? 'fail' : 'pass';
        if ($dryRun) {
            $evidenceList[] = new SupportSecurityEvidenceItem([
                'control_name'            => 'provider_fail_closed',
                'result'                  => $providerResult,
                'evidence_data_encrypted' => Crypt::encryptString(json_encode($providerEvData, JSON_UNESCAPED_UNICODE)),
            ]);
        } else {
            SupportSecurityEvidenceItem::createEncrypted($run->id, 'provider_fail_closed', $providerResult, $providerEvData);
        }

        // --- Bulgular veritabanına yaz veya bellekte model oluştur ---
        $findingsModels = [];
        foreach ($findingsList as $finding) {
            $fdata = array_merge($finding, [
                'run_id'   => $run->id ?? null,
                'store_id' => $storeId,
                'status'   => 'open',
            ]);
            if ($dryRun) {
                $findingsModels[] = new SupportSecurityFinding($fdata);
            } else {
                SupportSecurityFinding::create($fdata);
            }
        }

        $criticalCount = collect($findingsList)->where('severity', 'critical')->count();
        $overallSeverity = match (true) {
            $criticalCount > 0              => 'critical',
            collect($findingsList)->where('severity', 'high')->count() > 0 => 'high',
            count($findingsList) > 0            => 'medium',
            default                         => 'clean',
        };

        if ($dryRun) {
            $run->status = 'completed';
            $run->overall_severity = $overallSeverity;
            $run->findings_count = count($findingsList);
            $run->completed_at = now();
            $run->setRelation('findings', collect($findingsModels));
            $run->setRelation('evidenceItems', collect($evidenceList));
        } else {
            $run->update([
                'status'           => 'completed',
                'overall_severity' => $overallSeverity,
                'findings_count'   => count($findingsList),
                'completed_at'     => now(),
            ]);
            $run = $run->fresh();
        }

        return $run;
    }

    /**
     * Denetçiye sunulacak redacted evidence pack üretir.
     * Ham secret, müşteri mesajı veya PII içermez.
     */
    public function generateEvidencePack(int $storeId, User $user): string
    {
        TenantContext::enforceStoreAccess($storeId, $user);

        $lastRun = SupportSecurityAuditRun::where('store_id', $storeId)
            ->orderByDesc('created_at')
            ->first();

        if (!$lastRun) {
            return "# Güvenlik Kanıt Paketi\n\n**Henüz denetim çalıştırılmamıştır.**\n";
        }

        $lines = [];
        $lines[] = "# ZOLM AI Müşteri İletişim Merkezi — Güvenlik Kanıt Paketi";
        $lines[] = "**Mağaza ID:** [MASKELENDİ]";
        $lines[] = "**Denetim ID:** {$lastRun->id}";
        $lines[] = "**Tamamlanma:** " . ($lastRun->completed_at?->toDateTimeString() ?? 'bilinmiyor');
        $lines[] = "**Genel Seviye:** " . strtoupper($lastRun->overall_severity ?? 'unknown');
        $lines[] = "**Bulgu Sayısı:** {$lastRun->findings_count}";
        $lines[] = "";
        $lines[] = "## Bulgular";
        $lines[] = "| Kategori | Seviye | Başlık |";
        $lines[] = "|---|---|---|";

        foreach ($lastRun->findings as $finding) {
            $title = str_replace(['|', "\n"], ['-', ' '], $finding->title);
            $lines[] = "| {$finding->category} | {$finding->severity} | {$title} |";
        }

        $lines[] = "";
        $lines[] = "## Kanıt Kontrolleri";
        $lines[] = "| Kontrol | Sonuç |";
        $lines[] = "|---|---|";

        foreach ($lastRun->evidenceItems as $item) {
            $lines[] = "| {$item->control_name} | " . strtoupper($item->result) . " |";
        }

        $lines[] = "";
        $lines[] = "> Bu rapor otomatik üretilmiştir. Ham müşteri verisi, secret veya token içermemektedir.";

        return implode("\n", $lines);
    }
}
