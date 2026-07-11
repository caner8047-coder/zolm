<?php

namespace App\Services\Accounting;

use App\Models\User;
use App\Models\AccountingPilotFeedback;
use App\Models\AccountingPilotHealthSnapshot;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class AccountingPilotReleaseCheckService
{
    /**
     * Required documentation file list.
     */
    protected array $requiredDocs = [
        'docs/accounting-pilot-runbook.md',
        'docs/accounting-pilot-risk-register.md',
        'docs/accounting-user-acceptance-scenarios.md',
        'docs/accounting-release-checklist.md',
        'docs/parola-esdegerlik-ve-urunlesme-checklist.md'
    ];

    /**
     * Override required documents list for testing purposes.
     */
    public function setRequiredDocs(array $docs): void
    {
        $this->requiredDocs = $docs;
    }

    /**
     * Run all release readiness checks.
     */
    public function run(?int $userId = null): array
    {
        $checks = [];

        // 1. Config: accounting_enabled
        $accountingEnabled = (bool) config('marketplace.features.accounting_enabled', false);
        $checks['accounting_enabled'] = [
            'title' => 'Muhasebe Modülü Feature Flag',
            'status' => $accountingEnabled ? 'passed' : 'warning',
            'message' => $accountingEnabled ? 'ACCOUNTING_ENABLED aktif.' : 'ACCOUNTING_ENABLED kapalı.',
        ];

        // 2. Config: party_core_enabled
        $partyCoreEnabled = (bool) config('marketplace.features.party_core_enabled', false);
        $checks['party_core_enabled'] = [
            'title' => 'Cari Modülü Feature Flag',
            'status' => $partyCoreEnabled ? 'passed' : 'warning',
            'message' => $partyCoreEnabled ? 'PARTY_CORE_ENABLED aktif.' : 'PARTY_CORE_ENABLED kapalı.',
        ];

        // 3. User check if provided
        if ($userId !== null) {
            $user = User::find($userId);
            if (!$user) {
                $checks['pilot_user'] = [
                    'title' => 'Pilot Kullanıcı Tanımı',
                    'status' => 'failed',
                    'message' => "Kullanıcı (ID: {$userId}) bulunamadı.",
                ];
            } else {
                $isAdmin = $user->roleSlug() === 'admin';
                $checks['pilot_user'] = [
                    'title' => 'Pilot Kullanıcı Yetkisi',
                    'status' => $isAdmin ? 'passed' : 'failed',
                    'message' => $isAdmin ? 'Pilot kullanıcı admin rolünde.' : 'Pilot kullanıcı admin rolünde değil.',
                ];
            }
        } else {
            $checks['pilot_user'] = [
                'title' => 'Pilot Kullanıcı Tanımı',
                'status' => 'warning',
                'message' => 'Kullanıcı ID girilmedi, kullanıcı spesifik kontroller atlandı.',
            ];
        }

        // 4. Route check
        $hasDashboardRoute = Route::has('accounting.dashboard');
        $hasPilotRoute = Route::has('accounting.pilot-center');
        $hasRoutes = $hasDashboardRoute && $hasPilotRoute;
        $checks['routes_exist'] = [
            'title' => 'ERP / Pilot Center Route Kontrolü',
            'status' => $hasRoutes ? 'passed' : 'failed',
            'message' => $hasRoutes ? 'Dashboard ve Pilot Center route tanımları mevcut.' : 'Eksik route tanımları var.',
        ];

        // 5. Health check snapshots (Database check with schema validation)
        if ($userId !== null) {
            if (!Schema::hasTable('accounting_pilot_health_snapshots')) {
                $checks['latest_health_snapshot'] = [
                    'title' => 'Son Sağlık Taraması',
                    'status' => 'failed',
                    'message' => 'Sağlık taraması tablosu bulunamadı. Lütfen migration\'ları çalıştırın.',
                ];
            } else {
                $latestSnapshot = AccountingPilotHealthSnapshot::where('user_id', $userId)
                    ->orderBy('created_at', 'desc')
                    ->first();

                if (!$latestSnapshot) {
                    $checks['latest_health_snapshot'] = [
                        'title' => 'Sağlık Taraması Geçmişi',
                        'status' => 'warning',
                        'message' => 'Kullanıcı için henüz bir sağlık taraması yapılmamış.',
                    ];
                } else {
                    $hasFail = $latestSnapshot->failed_count > 0;
                    $checks['latest_health_snapshot'] = [
                        'title' => 'Son Sağlık Taraması',
                        'status' => $hasFail ? 'failed' : 'passed',
                        'message' => $hasFail ? "Son taramada {$latestSnapshot->failed_count} hata tespit edildi." : 'Son tarama başarılı.',
                    ];
                }
            }
        }

        // 6. Feedbacks check (Database check with schema validation)
        if ($userId !== null) {
            if (!Schema::hasTable('accounting_pilot_feedbacks')) {
                $checks['critical_feedbacks'] = [
                    'title' => 'Açık Kritik Geri Bildirimler',
                    'status' => 'failed',
                    'message' => 'Geri bildirim tablosu bulunamadı. Lütfen migration\'ları çalıştırın.',
                ];
            } else {
                $criticalFeedbacksCount = AccountingPilotFeedback::where('user_id', $userId)
                    ->where('status', 'open')
                    ->whereIn('severity', ['high', 'critical'])
                    ->count();
                
                $hasCriticalFeedbacks = $criticalFeedbacksCount > 0;
                $checks['critical_feedbacks'] = [
                    'title' => 'Açık Kritik Geri Bildirimler',
                    'status' => $hasCriticalFeedbacks ? 'warning' : 'passed',
                    'message' => $hasCriticalFeedbacks ? "Sistemde {$criticalFeedbacksCount} açık kritik/yüksek geri bildirim var." : 'Açık kritik geri bildirim bulunmuyor.',
                ];
            }
        }

        // 7. Required Docs check
        $missingDocs = [];
        foreach ($this->requiredDocs as $doc) {
            if (!file_exists(base_path($doc))) {
                $missingDocs[] = basename($doc);
            }
        }
        $hasAllDocs = empty($missingDocs);
        $checks['required_documents'] = [
            'title' => 'Release Doküman Uyumluluğu',
            'status' => $hasAllDocs ? 'passed' : 'failed',
            'message' => $hasAllDocs ? 'Tüm gerekli kılavuz ve dokümanlar mevcut.' : 'Eksik dokümanlar: ' . implode(', ', $missingDocs),
        ];

        // 8. Seeder Production Guard
        $seederFilePath = app_path('Console/Commands/SeedAccountingDemoCommand.php');
        $hasGuard = false;
        if (file_exists($seederFilePath)) {
            $seederContent = file_get_contents($seederFilePath);
            if (str_contains($seederContent, 'production') && (str_contains($seederContent, '--force') || str_contains($seederContent, 'force'))) {
                $hasGuard = true;
            }
        }
        $checks['seeder_production_guard'] = [
            'title' => 'Seeder Production Güvenlik Guardı',
            'status' => $hasGuard ? 'passed' : 'failed',
            'message' => $hasGuard ? 'Seeder production guard ve force kontrolü aktif.' : 'Seeder production guard kısıtlaması bulunamadı.',
        ];

        // 9. MarketplaceReportDigestTest known issue check
        $riskFilePath = base_path('docs/accounting-pilot-risk-register.md');
        $hasKnownIssue = false;
        if (file_exists($riskFilePath)) {
            $riskContent = file_get_contents($riskFilePath);
            if (str_contains($riskContent, 'MarketplaceReportDigestTest')) {
                $hasKnownIssue = true;
            }
        }
        $checks['known_issue_documented'] = [
            'title' => 'Known Issue / Bilinen Hata Kaydı',
            'status' => $hasKnownIssue ? 'passed' : 'failed',
            'message' => $hasKnownIssue ? 'MarketplaceReportDigestTest bilinen hatası dokümante edilmiş.' : 'Pazaryeri digest testi bilinen hatası risk sicilinde bulunamadı.',
        ];

        // Count errors and warnings automatically to avoid mismatches
        $failedCount = 0;
        $warningCount = 0;
        foreach ($checks as $check) {
            if ($check['status'] === 'failed') {
                $failedCount++;
            } elseif ($check['status'] === 'warning') {
                $warningCount++;
            }
        }

        // Overall status
        $status = 'passed';
        if ($failedCount > 0) {
            $status = 'failed';
        } elseif ($warningCount > 0) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'failed_count' => $failedCount,
            'warning_count' => $warningCount,
            'checks' => $checks,
        ];
    }
}
