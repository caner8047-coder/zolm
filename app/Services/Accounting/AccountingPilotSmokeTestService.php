<?php

namespace App\Services\Accounting;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class AccountingPilotSmokeTestService
{
    /**
     * Default list of required routes.
     */
    protected array $requiredRoutes = [
        'accounting.dashboard',
        'accounting.parties',
        'accounting.party-ledger',
        'accounting.journal',
        'accounting.cash-bank',
        'accounting.stock',
        'accounting.sales',
        'accounting.purchases',
        'accounting.collections-payments',
        'accounting.pos',
        'accounting.e-documents',
        'accounting.reports',
        'accounting.assistant',
        'accounting.marketplace-bridge',
        'accounting.pilot-center',
        'accounting.chart-of-accounts',
        'accounting.products',
        'accounting.audit-logs'
    ];

    /**
     * Set/Override the required routes list (for testing).
     */
    public function setRequiredRoutes(array $routes): void
    {
        $this->requiredRoutes = $routes;
    }

    /**
     * Execute the smoke test validation.
     */
    public function run(?int $userId = null): array
    {
        $checks = [];

        // 1. Feature Flag: accounting_enabled
        $accountingEnabled = (bool) config('marketplace.features.accounting_enabled', false);
        $checks['accounting_enabled'] = [
            'title' => 'Muhasebe Modülü Feature Flag',
            'status' => $accountingEnabled ? 'passed' : 'warning',
            'message' => $accountingEnabled ? 'accounting_enabled aktif.' : 'accounting_enabled pasif.',
        ];

        // 2. Feature Flag: party_core_enabled
        $partyCoreEnabled = (bool) config('marketplace.features.party_core_enabled', false);
        $checks['party_core_enabled'] = [
            'title' => 'Cari Modülü Feature Flag',
            'status' => $partyCoreEnabled ? 'passed' : 'warning',
            'message' => $partyCoreEnabled ? 'party_core_enabled aktif.' : 'party_core_enabled pasif.',
        ];

        // 3. User & Admin check
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
                    'message' => $isAdmin ? 'Kullanıcı admin rolüne sahip.' : 'Kullanıcı admin yetkisine sahip değil.',
                ];
            }
        } else {
            $checks['pilot_user'] = [
                'title' => 'Pilot Kullanıcı Tanımı',
                'status' => 'warning',
                'message' => 'Kullanıcı ID girilmedi, kullanıcı spesifik kontroller atlandı.',
            ];
        }

        // 4. Routes exists check
        foreach ($this->requiredRoutes as $routeName) {
            $exists = Route::has($routeName);
            $key = 'route_' . str_replace('.', '_', $routeName);
            $checks[$key] = [
                'title' => "Route: {$routeName}",
                'status' => $exists ? 'passed' : 'failed',
                'message' => $exists ? 'Route mevcut.' : "Route sistemde kayıtlı değil: {$routeName}",
            ];
        }

        // Count errors and warnings automatically
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
