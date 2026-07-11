<?php

namespace App\Services\Accounting;

use App\Models\User;
use App\Models\LegalEntity;
use App\Models\Warehouse;
use App\Models\Account;
use App\Models\CashAccount;
use App\Models\BankAccount;
use App\Models\Party;
use App\Models\MpProduct;
use App\Models\MarketplaceFinanceBridgeRun;
use App\Models\AccountingPilotFeedback;
use App\Models\AccountingPilotHealthSnapshot;
use Illuminate\Support\Str;

class AccountingPilotReadinessService
{
    /**
     * Run health check and record a new snapshot.
     */
    public function runHealthCheck(int $userId): AccountingPilotHealthSnapshot
    {
        if (auth()->check() && auth()->id() !== $userId) {
            abort(403, 'Yetkisiz sağlık taraması isteği.');
        }

        $user = User::find($userId);
        
        $checks = [];
        $failedCount = 0;
        $warningCount = 0;
        $score = 100;

        // 1. Feature Flag
        $flagEnabled = (bool) config('marketplace.features.accounting_enabled', false);
        $checks['accounting_enabled'] = [
            'title' => 'Muhasebe Modülü Feature Flag',
            'status' => $flagEnabled ? 'passed' : 'failed',
            'message' => $flagEnabled ? 'Muhasebe modülü aktif.' : 'Ön Muhasebe / ERP feature flag kapalı.',
        ];
        if (!$flagEnabled) {
            $failedCount++;
            $score = 0;
        }

        // 2. Admin Yetkisi
        $isAdmin = $user && $user->roleSlug() === 'admin';
        $checks['admin_role'] = [
            'title' => 'Admin Yetki Kontrolü',
            'status' => $isAdmin ? 'passed' : 'failed',
            'message' => $isAdmin ? 'Kullanıcı admin rolüne sahip.' : 'Kullanıcı admin rolüne sahip değil.',
        ];
        if (!$isAdmin) {
            $failedCount++;
            $score = 0;
        }

        // 3. Legal Entity
        $hasLegalEntity = LegalEntity::where('user_id', $userId)->exists();
        $checks['legal_entity'] = [
            'title' => 'Şirket/Tüzel Kişilik Tanımı',
            'status' => $hasLegalEntity ? 'passed' : 'failed',
            'message' => $hasLegalEntity ? 'En az bir şirket tanımı mevcut.' : 'Hiçbir şirket/tüzel kişilik tanımı bulunamadı.',
        ];
        if (!$hasLegalEntity) {
            $failedCount++;
            $score -= 15;
        }

        // 4. Warehouse
        $hasWarehouse = Warehouse::where('user_id', $userId)->exists();
        $checks['warehouse'] = [
            'title' => 'Depo Tanımı',
            'status' => $hasWarehouse ? 'passed' : 'failed',
            'message' => $hasWarehouse ? 'En az bir depo tanımlı.' : 'Hiçbir depo tanımı bulunamadı.',
        ];
        if (!$hasWarehouse) {
            $failedCount++;
            $score -= 15;
        }

        // 5. Cash/Bank Account
        $hasCashBank = CashAccount::where('user_id', $userId)->exists() || BankAccount::where('user_id', $userId)->exists();
        $checks['cash_bank'] = [
            'title' => 'Kasa/Banka Hesabı',
            'status' => $hasCashBank ? 'passed' : 'failed',
            'message' => $hasCashBank ? 'En az bir kasa veya banka hesabı mevcut.' : 'Hiçbir kasa veya banka hesabı bulunamadı.',
        ];
        if (!$hasCashBank) {
            $failedCount++;
            $score -= 15;
        }

        // 6. Chart of Accounts (COA) Temel Hesaplar
        $requiredCodes = ['120', '320', '600', '153', '191', '391'];
        $existingCodesCount = Account::where('user_id', $userId)->whereIn('code', $requiredCodes)->count();
        $hasCashGl = Account::where('user_id', $userId)->where('code', 'like', '100%')->exists();
        $hasBankGl = Account::where('user_id', $userId)->where('code', 'like', '102%')->exists();
        
        $hasAllCOA = ($existingCodesCount === count($requiredCodes)) && $hasCashGl && $hasBankGl;
        $totalFound = $existingCodesCount + ($hasCashGl ? 1 : 0) + ($hasBankGl ? 1 : 0);

        $checks['chart_of_accounts'] = [
            'title' => 'Temel Yevmiye Hesap Kodları',
            'status' => $hasAllCOA ? 'passed' : 'failed',
            'message' => $hasAllCOA ? 'Tüm temel hesap kodları (100, 102, 120, 320, 600, 153, 191, 391) mevcut.' : "Eksik hesap kodları var. Mevcut olanlar: {$totalFound}/8.",
        ];
        if (!$hasAllCOA) {
            $failedCount++;
            $score -= 15;
        }

        // 7.1. Customer Party
        $hasCustomer = \App\Models\PartyRole::where('user_id', $userId)->where('role', 'customer')->exists();
        $checks['customer_party'] = [
            'title' => 'Müşteri Cari Kaydı',
            'status' => $hasCustomer ? 'passed' : 'failed',
            'message' => $hasCustomer ? 'En az bir müşteri cari kartı mevcut.' : 'Hiçbir müşteri cari kartı kaydı bulunamadı.',
        ];
        if (!$hasCustomer) {
            $failedCount++;
            $score -= 15;
        }

        // 7.2. Supplier Party
        $hasSupplier = \App\Models\PartyRole::where('user_id', $userId)->where('role', 'supplier')->exists();
        $checks['supplier_party'] = [
            'title' => 'Tedarikçi Cari Kaydı',
            'status' => $hasSupplier ? 'passed' : 'failed',
            'message' => $hasSupplier ? 'En az bir tedarikçi cari kartı mevcut.' : 'Hiçbir tedarikçi cari kartı kaydı bulunamadı.',
        ];
        if (!$hasSupplier) {
            $failedCount++;
            $score -= 15;
        }

        // 8. Product / Stock
        $hasProduct = MpProduct::where('user_id', $userId)->exists();
        $checks['products'] = [
            'title' => 'Ürün/Stok Kartları',
            'status' => $hasProduct ? 'passed' : 'failed',
            'message' => $hasProduct ? 'En az bir ürün/stok kartı tanımlı.' : 'Hiçbir ürün veya stok kartı bulunamadı.',
        ];
        if (!$hasProduct) {
            $failedCount++;
            $score -= 15;
        }

        // 9. Failed Marketplace Bridge Run (Warning)
        $hasFailedBridge = MarketplaceFinanceBridgeRun::where('user_id', $userId)->where('status', 'failed')->exists();
        $checks['failed_bridge_runs'] = [
            'title' => 'Başarısız Pazaryeri Entegrasyon Runları',
            'status' => $hasFailedBridge ? 'warning' : 'passed',
            'message' => $hasFailedBridge ? 'Başarısız pazaryeri entegrasyon hareketleri var.' : 'Başarısız entegrasyon hareketi bulunmuyor.',
        ];
        if ($hasFailedBridge) {
            $warningCount++;
            $score -= 5;
        }

        // 10. Açık Geri Bildirim Sayısı (Warning)
        $openFeedbacksCount = AccountingPilotFeedback::where('user_id', $userId)->where('status', 'open')->count();
        $checks['open_feedbacks'] = [
            'title' => 'Açık Geri Bildirimler',
            'status' => $openFeedbacksCount > 0 ? 'warning' : 'passed',
            'message' => $openFeedbacksCount > 0 ? "Sistemde henüz çözülmemiş {$openFeedbacksCount} açık geri bildirim var." : 'Açık geri bildirim bulunmuyor.',
        ];
        if ($openFeedbacksCount > 0) {
            $warningCount++;
            $score -= 5;
        }

        // 11. e-Fatura Entegratör Durumu (MVP Warning)
        $checks['e_document_integrator'] = [
            'title' => 'e-Fatura Gerçek Entegratör Bağlantısı (MVP)',
            'status' => 'warning',
            'message' => 'Simüle provider aktif; canlı GİB/Özel entegratör bağlantısı yok.',
        ];
        $warningCount++;

        // 12. POS Donanım Durumu (MVP Warning)
        $checks['pos_hardware'] = [
            'title' => 'POS Donanım Bağlantısı (MVP)',
            'status' => 'warning',
            'message' => 'Web POS aktif; donanım, fiş yazıcı ve ödeme terminali bağlantısı yok.',
        ];
        $warningCount++;

        // Score Limits
        if ($score < 0) {
            $score = 0;
        }

        // Overall Status
        $overallStatus = 'passed';
        if ($failedCount > 0) {
            $overallStatus = 'failed';
        } elseif ($warningCount > 0) {
            $overallStatus = 'warning';
        }

        return AccountingPilotHealthSnapshot::create([
            'user_id' => $userId,
            'run_uuid' => (string) Str::uuid(),
            'status' => $overallStatus,
            'score' => $score,
            'failed_count' => $failedCount,
            'warning_count' => $warningCount,
            'checks_json' => $checks,
            'meta_json' => [],
        ]);
    }

    /**
     * Get the latest health snapshot for a user.
     */
    public function getLatestSnapshot(int $userId): ?AccountingPilotHealthSnapshot
    {
        if (auth()->check() && auth()->id() !== $userId) {
            abort(403, 'Yetkisiz sağlık raporu erişimi.');
        }

        return AccountingPilotHealthSnapshot::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Create a new feedback ticket.
     */
    public function createFeedback(int $userId, int $actorUserId, array $data): AccountingPilotFeedback
    {
        if (auth()->check() && (auth()->id() !== $userId || auth()->id() !== $actorUserId)) {
            abort(403, 'Yetkisiz geri bildirim kaydı.');
        }

        return AccountingPilotFeedback::create(array_merge($data, [
            'user_id' => $userId,
            'actor_user_id' => $actorUserId,
            'status' => 'open',
        ]));
    }

    /**
     * Resolve an existing feedback ticket.
     */
    public function resolveFeedback(AccountingPilotFeedback $feedback, int $actorUserId): AccountingPilotFeedback
    {
        // Tenant security check
        if ($feedback->user_id !== $actorUserId || (auth()->check() && auth()->id() !== $actorUserId)) {
            abort(403, 'Bu geri bildirimi çözme yetkiniz yok.');
        }

        $feedback->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'actor_user_id' => $actorUserId,
        ]);

        return $feedback;
    }

    /**
     * Return feedback metrics summary.
     */
    public function feedbackSummary(int $userId): array
    {
        if (auth()->check() && auth()->id() !== $userId) {
            abort(403, 'Yetkisiz geri bildirim özeti erişimi.');
        }

        $open = AccountingPilotFeedback::where('user_id', $userId)->where('status', 'open')->count();
        $resolved = AccountingPilotFeedback::where('user_id', $userId)->where('status', 'resolved')->count();
        
        $criticalCount = AccountingPilotFeedback::where('user_id', $userId)
            ->where('status', 'open')
            ->whereIn('severity', ['high', 'critical'])
            ->count();

        return [
            'open' => $open,
            'resolved' => $resolved,
            'total' => $open + $resolved,
            'critical' => $criticalCount,
        ];
    }
}
