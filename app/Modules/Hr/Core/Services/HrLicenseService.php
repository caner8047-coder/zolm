<?php

namespace App\Modules\Hr\Core\Services;

use App\Models\HrLicense;
use App\Models\LegalEntity;
use Illuminate\Support\Collection;

class HrLicenseService
{
    public function isModuleActive(LegalEntity $tenant, string $moduleKey): bool
    {
        $license = $this->getLicense($tenant, $moduleKey);

        if (!$license) {
            return false;
        }

        return $license->isActiveAndValid();
    }

    public function getLicense(LegalEntity $tenant, string $moduleKey): ?HrLicense
    {
        return $tenant->hrLicenses()
            ->where('module_key', $moduleKey)
            ->first();
    }

    public function getActiveLicenses(LegalEntity $tenant): Collection
    {
        return $tenant->hrLicenses()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();
    }

    public function isEmployeeLimitReached(LegalEntity $tenant, string $moduleKey): bool
    {
        $license = $this->getLicense($tenant, $moduleKey);

        if (!$license || !$license->max_employees) {
            return false;
        }

        $currentCount = $tenant->hrLicenses()
            ->where('module_key', $moduleKey)
            ->where('is_active', true)
            ->count();

        // Bu basit kontrol; gerçek çalışan sayısı employee tablosundan gelmeli
        return false; // Henüz employee modülü yok, Faz 1'de genişletilecek
    }

    public function checkAccess(LegalEntity $tenant, string $moduleKey): LicenseCheckResult
    {
        $license = $this->getLicense($tenant, $moduleKey);

        if (!$license) {
            return LicenseCheckResult::notFound($moduleKey);
        }

        if (!$license->is_active) {
            return LicenseCheckResult::inactive($moduleKey);
        }

        if ($license->expires_at && $license->expires_at->isPast()) {
            return LicenseCheckResult::expired($moduleKey, $license->expires_at);
        }

        return LicenseCheckResult::active($moduleKey);
    }
}

class LicenseCheckResult
{
    private function __construct(
        public readonly string $moduleKey,
        public readonly string $status,
        public readonly ?string $message = null,
        public readonly ?\Carbon\Carbon $expiresAt = null,
    ) {}

    public static function active(string $moduleKey): self
    {
        return new self($moduleKey, 'active');
    }

    public static function notFound(string $moduleKey): self
    {
        return new self($moduleKey, 'not_found', "{$moduleKey} modülü için lisans bulunamadı.");
    }

    public static function inactive(string $moduleKey): self
    {
        return new self($moduleKey, 'inactive', "{$moduleKey} modülü pasif durumda.");
    }

    public static function expired(string $moduleKey, \Carbon\Carbon $expiresAt): self
    {
        return new self($moduleKey, 'expired', "{$moduleKey} modül lisansının süresi dolmuş.", $expiresAt);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
