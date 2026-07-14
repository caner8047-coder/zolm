<?php

namespace App\Services\Support;

use App\Models\SupportReleasePackage;
use App\Models\SupportReleaseEvent;
use App\Models\SupportArtifactVersion;
use App\Models\SupportChannel;
use App\Models\User;
use App\Services\Support\TenantContext;
use App\Services\Support\Security\SupportRbacService;
use App\Services\Support\Policy\SupportPolicyEngine;
use App\Services\Support\AI\CustomerCareGoldenEvalGateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CustomerCareReleaseService
{
    public function preflightCheck(SupportReleasePackage $package, ?User $actor = null): array
    {
        $actor = $actor ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($package->store_id, $actor);

        $checks = [];
        $allPassed = true;

        $allowedArtifactTypes = ['knowledge_article', 'brand_voice', 'policy_rule', 'prompt_template', 'answer_template'];
        $structureErrors = [];
        $artifactKeys = [];
        foreach ($package->items as $item) {
            if (!in_array($item->artifact_type, $allowedArtifactTypes, true)) {
                $structureErrors[] = "Paket kalemi #{$item->id}: desteklenmeyen artifact tipi.";
            }
            if (!in_array($item->action, ['create', 'update'], true)) {
                $structureErrors[] = "Paket kalemi #{$item->id}: delete işlemi güvenli tombstone akışı tanımlanana kadar desteklenmiyor.";
            }
            if (!is_array($item->new_content_json) || $item->new_content_json === []) {
                $structureErrors[] = "Paket kalemi #{$item->id}: yayınlanabilir içerik boş.";
            }
            $artifactKey = $item->artifact_type . ':' . ($item->artifact_id ?? 'new');
            if (in_array($artifactKey, $artifactKeys, true)) {
                $structureErrors[] = "Aynı artifact paket içinde birden fazla kez değiştirilemez: {$artifactKey}.";
            }
            $artifactKeys[] = $artifactKey;
        }
        if ($package->items->isEmpty()) {
            $structureErrors[] = 'Paket en az bir artifact içermelidir.';
        }
        $checks['package_structure'] = [
            'status' => $structureErrors === [] ? 'passed' : 'failed',
            'label' => 'Paket Yapısı Kontrolü',
            'detail' => $structureErrors === [] ? 'Paket yapısı ve artifact kimlikleri geçerli.' : implode(' | ', $structureErrors),
        ];
        if ($structureErrors !== []) $allPassed = false;

        // 1. PII Redaction Check
        $piiFound = false;
        $piiDetail = 'PII sızıntısı tespit edilmedi.';

        $emailRegex = '/[\w\.\-]+@[\w\.\-]+\.[a-zA-Z]{2,}/';
        $phoneRegex = '/\+?[0-9\s\-()]{10,}/';
        $tcknRegex = '/\b[1-9]\d{10}\b/';

        foreach ($package->items as $item) {
            $contentStr = json_encode($item->new_content_json, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (preg_match($emailRegex, $contentStr) || preg_match($tcknRegex, $contentStr)) {
                $piiFound = true;
                $piiDetail = 'PII sızıntısı (E-posta veya TC Kimlik Numarası) tespit edildi!';
                break;
            }
            // Match phone number with length validation
            if (preg_match_all($phoneRegex, $contentStr, $matches)) {
                foreach ($matches[0] as $match) {
                    $digits = preg_replace('/\D/', '', $match);
                    if (strlen($digits) >= 10 && strlen($digits) <= 15) {
                        $piiFound = true;
                        $piiDetail = 'PII sızıntısı (Telefon Numarası) tespit edildi.';
                        break 2;
                    }
                }
            }
        }

        $checks['pii_redaction'] = [
            'status' => !$piiFound ? 'passed' : 'failed',
            'label' => 'Kişisel Veri (PII) Kontrolü',
            'detail' => $piiDetail,
        ];
        if ($piiFound) $allPassed = false;

        // 2. Prompt Injection Check
        $injectionFound = false;
        $injectionDetail = 'Zararlı prompt enjeksiyon kalıbı bulunmadı.';
        $forbiddenKeywords = ['ignore previous', 'forget all rules', 'sistem talimatlarını yoksay', 'bütün kuralları unut'];

        foreach ($package->items as $item) {
            if ($item->artifact_type === 'prompt_template') {
                $contentStr = mb_strtolower(json_encode($item->new_content_json, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                foreach ($forbiddenKeywords as $kw) {
                    if (str_contains($contentStr, $kw)) {
                        $injectionFound = true;
                        $injectionDetail = "Potansiyel Prompt Injection tespit edildi! Yasaklı kelime: '{$kw}'";
                        break 2;
                    }
                }
            }
        }

        $checks['prompt_injection'] = [
            'status' => !$injectionFound ? 'passed' : 'failed',
            'label' => 'Prompt Injection Güvenlik Taraması',
            'detail' => $injectionDetail,
        ];
        if ($injectionFound) $allPassed = false;

        // 3. Golden Eval Smoke check
        $evalEvidence = app(CustomerCareGoldenEvalGateService::class)->evaluate($package->store_id, 'tr');
        $checks['golden_eval_smoke'] = [
            'status' => $evalEvidence['passed'] ? 'passed' : 'failed',
            'label' => 'Golden Dataset Smoke Testi',
            'detail' => $evalEvidence['detail'],
        ];
        if (!$evalEvidence['passed']) $allPassed = false;

        // 4. Policy Conflict check — yalnız doğrudan müşteriye gidebilen cevap şablonlarında uygulanır.
        $policyEngine = app(SupportPolicyEngine::class);
        $activeChannelKeys = SupportChannel::where('store_id', $package->store_id)
            ->where('is_enabled', true)
            ->pluck('key')
            ->filter()
            ->unique()
            ->values();
        $policyConflicts = [];
        $policyChecksPerformed = 0;
        foreach ($package->items as $item) {
            if ($item->artifact_type !== 'answer_template') {
                continue;
            }

            $content = $item->new_content_json ?? [];
            $message = (string) ($content['message'] ?? $content['text'] ?? $content['template'] ?? '');
            $targetChannel = (string) ($content['channel_key'] ?? '');
            $channels = $targetChannel !== '' ? collect([$targetChannel]) : $activeChannelKeys;
            if ($message === '' || $channels->isEmpty()) {
                $policyConflicts[] = "Paket kalemi #{$item->id}: cevap metni veya hedef kanal eksik.";
                continue;
            }

            foreach ($channels as $channelKey) {
                $policyChecksPerformed++;
                $result = $policyEngine->validate($message, $this->normalizePolicyChannelKey((string) $channelKey));
                if (!($result['allowed'] ?? false)) {
                    $policyConflicts[] = "Paket kalemi #{$item->id} / {$channelKey}: " . ($result['reason'] ?? 'Politika ihlali.');
                }
            }
        }

        $policyStatus = $policyConflicts !== [] ? 'failed' : ($policyChecksPerformed > 0 ? 'passed' : 'not_applicable');
        $checks['policy_conflict'] = [
            'status' => $policyStatus,
            'label' => 'Uyuşmazlık (Policy Conflict) Taraması',
            'detail' => $policyConflicts !== []
                ? implode(' | ', $policyConflicts)
                : ($policyChecksPerformed > 0
                    ? "{$policyChecksPerformed} kanal/şablon politika kontrolü gerçek validator ile geçti."
                    : 'Doğrudan müşteri cevap şablonu bulunmadığı için uygulanabilir değil.'),
        ];
        if ($policyConflicts !== []) $allPassed = false;

        return [
            'allowed' => $allPassed,
            'checks' => $checks,
        ];
    }

    public function createVersion(
        int $storeId,
        string $type,
        ?int $artifactId,
        array $content,
        ?User $actor = null
    ): SupportArtifactVersion
    {
        if (!in_array($type, ['knowledge_article', 'brand_voice', 'policy_rule', 'prompt_template', 'answer_template'], true)
            || $content === []) {
            throw new \InvalidArgumentException('Artifact tipi veya içeriği yayınlanabilir değil.');
        }

        $actor = $actor ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $actor);
        $rbac = app(SupportRbacService::class);
        $rbac->enforcePermission($actor, $storeId, 'knowledge_publish');
        $rbac->enforceApproval($actor, $storeId, 'create_artifact_version', [
            'artifact_id' => $artifactId,
            'artifact_type' => $type,
            'content_hash' => hash('sha256', json_encode($content, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
        ]);

        return $this->createVersionRecord($storeId, $type, $artifactId, $content, null);
    }

    private function createVersionRecord(
        int $storeId,
        string $type,
        ?int $artifactId,
        array $content,
        ?int $releasePackageId
    ): SupportArtifactVersion {
        return DB::transaction(function () use ($storeId, $type, $artifactId, $content, $releasePackageId) {
            SupportArtifactVersion::where('store_id', $storeId)
                ->where('artifact_type', $type)
                ->where('artifact_id', $artifactId)
                ->lockForUpdate()
                ->get();

            // Set all previous versions to is_current = false
            SupportArtifactVersion::where('store_id', $storeId)
                ->where('artifact_type', $type)
                ->where('artifact_id', $artifactId)
                ->update(['is_current' => false]);

            $latestVersion = SupportArtifactVersion::where('store_id', $storeId)
                ->where('artifact_type', $type)
                ->where('artifact_id', $artifactId)
                ->max('version_number') ?? 0;

            return SupportArtifactVersion::create([
                'store_id' => $storeId,
                'artifact_type' => $type,
                'artifact_id' => $artifactId,
                'version_number' => $latestVersion + 1,
                'content_json' => $content,
                'is_current' => true,
                'release_package_id' => $releasePackageId,
            ]);
        });
    }

    private function normalizePolicyChannelKey(string $channelKey): string
    {
        $key = mb_strtolower($channelKey);
        foreach (['instagram_comment', 'facebook_comment', 'google_business', 'hepsiburada', 'trendyol', 'whatsapp', 'instagram', 'facebook', 'web_chat', 'n11'] as $canonical) {
            if ($key === $canonical || str_starts_with($key, $canonical . '_')) {
                return $canonical;
            }
        }

        return $key;
    }

    public function publishPackage(SupportReleasePackage $package, ?User $actor = null): void
    {
        $actor = $actor ?? Auth::user() ?? TenantContext::getSystemActor();
        $storeId = $package->store_id;

        // Tenant access check
        TenantContext::enforceStoreAccess($storeId, $actor);

        if ($package->status === 'published') {
            return;
        }
        if (!in_array($package->status, ['review', 'approved'], true)) {
            throw new \RuntimeException('Yalnız inceleme veya onay durumundaki paket yayınlanabilir.');
        }
        if ($package->items()->count() === 0) {
            throw new \RuntimeException('Boş release paketi yayınlanamaz.');
        }

        // Run preflight checks before publish
        $preflight = $this->preflightCheck($package, $actor);
        if (!$preflight['allowed']) {
            $package->update(['status' => 'rejected']);
            throw new \RuntimeException('Preflight denetim testleri başarısız oldu (Fail-Closed). Paket durumu rejected yapıldı.');
        }

        // Governance checks
        $rbac = app(SupportRbacService::class);
        $rbac->enforcePermission($actor, $storeId, 'force_circuit_breaker');

        DB::transaction(function () use ($package, $actor, $rbac, $storeId) {
            $lockedPackage = SupportReleasePackage::whereKey($package->id)->lockForUpdate()->firstOrFail();
            if (!in_array($lockedPackage->status, ['review', 'approved'], true)) {
                throw new \RuntimeException('Paket durumu eşzamanlı olarak değişti; yayın durduruldu.');
            }
            $approval = $rbac->enforceApproval($actor, $storeId, 'publish_release_package_' . $lockedPackage->id, [
                'package_id' => $lockedPackage->id,
            ]);

            $lockedPackage->update([
                'status' => 'published',
                'approved_by' => $approval?->approved_by,
                'published_at' => now(),
            ]);

            // Version each item as current
            foreach ($lockedPackage->items as $item) {
                $this->createVersionRecord(
                    $storeId,
                    $item->artifact_type,
                    $item->artifact_id,
                    $item->new_content_json,
                    $lockedPackage->id
                );
            }

            // Write Release Event
            SupportReleaseEvent::create([
                'package_id' => $lockedPackage->id,
                'event_type' => 'package_published',
                'details_json' => [
                    'actor_id' => $actor->id,
                    'approved_by' => $approval?->approved_by,
                    'approval_request_id' => $approval?->id,
                ],
            ]);
        });
    }

    public function rollbackPackage(SupportReleasePackage $package, ?User $actor = null): void
    {
        $actor = $actor ?? Auth::user() ?? TenantContext::getSystemActor();
        $storeId = $package->store_id;

        // Tenant IDOR check
        TenantContext::enforceStoreAccess($storeId, $actor);

        if ($package->status === 'rolled_back') {
            return;
        }
        if ($package->status !== 'published') {
            throw new \RuntimeException('Yalnız yayınlanmış paket geri alınabilir.');
        }

        // Governance checks
        $rbac = app(SupportRbacService::class);
        $rbac->enforcePermission($actor, $storeId, 'force_circuit_breaker');

        DB::transaction(function () use ($package, $actor, $rbac, $storeId) {
            $lockedPackage = SupportReleasePackage::whereKey($package->id)->lockForUpdate()->firstOrFail();
            if ($lockedPackage->status !== 'published') {
                throw new \RuntimeException('Paket durumu eşzamanlı olarak değişti; rollback durduruldu.');
            }
            $approval = $rbac->enforceApproval($actor, $storeId, 'rollback_release_package_' . $lockedPackage->id, [
                'package_id' => $lockedPackage->id,
            ]);

            foreach ($lockedPackage->items as $item) {
                $currentVersion = SupportArtifactVersion::where('store_id', $storeId)
                    ->where('artifact_type', $item->artifact_type)
                    ->where('artifact_id', $item->artifact_id)
                    ->where('release_package_id', $lockedPackage->id)
                    ->where('is_current', true)
                    ->lockForUpdate()
                    ->first();

                if (!$currentVersion) {
                    throw new \RuntimeException(
                        "Paket #{$lockedPackage->id} artık aktif sürümü temsil etmiyor; daha yeni yayın korunmak için rollback durduruldu."
                    );
                }

                $currentVersion->update(['is_current' => false]);
                $previousVersion = SupportArtifactVersion::where('store_id', $storeId)
                    ->where('artifact_type', $item->artifact_type)
                    ->where('artifact_id', $item->artifact_id)
                    ->where('version_number', '<', $currentVersion->version_number)
                    ->orderBy('version_number', 'desc')
                    ->first();

                if ($previousVersion) {
                    $previousVersion->update(['is_current' => true]);
                }
            }

            $lockedPackage->update(['status' => 'rolled_back']);

            // Write event
            SupportReleaseEvent::create([
                'package_id' => $lockedPackage->id,
                'event_type' => 'package_rolled_back',
                'details_json' => [
                    'actor_id' => $actor->id,
                    'approved_by' => $approval?->approved_by,
                    'approval_request_id' => $approval?->id,
                ],
            ]);
        });
    }
}
