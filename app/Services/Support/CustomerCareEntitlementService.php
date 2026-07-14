<?php

namespace App\Services\Support;

use App\Models\SupportCommercialPlan;
use App\Models\SupportCommercialSubscription;
use App\Models\SupportEntitlementEvent;
use App\Models\User;
use App\Services\Support\TenantContext;
use App\Services\Support\CustomerCareUsageService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\Access\AuthorizationException;

class CustomerCareEntitlementService
{
    public function hasEntitlement(int $storeId, string $feature, ?User $user = null): bool
    {
        $user = $user ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $user);

        // 1. Kritik operasyonel özellikler (manual agent reply) her zaman serbesttir
        if ($feature === 'manual_agent_reply') {
            return true;
        }

        // Feature flag kontrolü
        if (!config('customer-care.commercial_center_enabled', false)) {
            return true; // flag kapalıyken commercial kısıtlaması yok
        }

        $subscription = SupportCommercialSubscription::where('store_id', $storeId)
            ->where('status', 'active')
            ->first();

        // Aktif subscription yoksa starter/trial varsaymıyoruz, fail-closed
        if (!$subscription) {
            $this->logEntitlementEvent($storeId, $feature, 'blocked', ['reason' => 'Aktif abonelik bulunamadı.']);
            return false;
        }

        $plan = $subscription->plan;
        if (!$plan) {
            $this->logEntitlementEvent($storeId, $feature, 'blocked', ['reason' => 'Abonelik planı bulunamadı.']);
            return false;
        }

        $entitlements = $plan->entitlements ?? [];

        // Tanımlanmayan/bilinmeyen entitlement'lar fail-closed
        if (!isset($entitlements[$feature])) {
            $this->logEntitlementEvent($storeId, $feature, 'blocked', ['reason' => 'Bilinmeyen/tanımsız özellik hak sınırlaması.']);
            return false;
        }

        $allow = (bool) $entitlements[$feature];

        if (!$allow) {
            $this->logEntitlementEvent($storeId, $feature, 'blocked', ['reason' => 'Bu özellik abonelik planınızda bulunmamaktadır.']);
            return false;
        }

        // 2. Limit kontrolü (Usage Metering entegrasyonu)
        $usageService = app(CustomerCareUsageService::class);
        if ($feature === 'auto_reply' || $feature === 'ai_draft') {
            $limitCheck = $usageService->checkLimit($storeId, $feature);
            if ($limitCheck['exceeded'] ?? false) {
                $this->logEntitlementEvent($storeId, $feature, 'blocked', ['reason' => 'Aylık kullanım limiti aşıldı.']);
                return false;
            }
        }

        $this->logEntitlementEvent($storeId, $feature, 'allowed');
        return true;
    }

    /**
     * Entitlement block/allow durumunu günlüğe yazar.
     */
    private function logEntitlementEvent(int $storeId, string $feature, string $status, ?array $context = null): void
    {
        SupportEntitlementEvent::create([
            'store_id' => $storeId,
            'feature'  => $feature,
            'status'   => $status,
            'context'  => $context,
        ]);
    }

    /**
     * Plan değişikliği talebini kaydeder.
     */
    public function requestPlanChange(int $storeId, int $newPlanId, ?User $user = null): SupportCommercialSubscription
    {
        $user = $user ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $user);
        app(\App\Services\Support\Security\SupportRbacService::class)
            ->enforcePermission($user, $storeId, 'manage_roles');

        $plan = SupportCommercialPlan::findOrFail($newPlanId);

        // Kurumsal plan geçişi merkezi admin rolüne ek olarak mağaza RBAC yetkisi gerektirir.
        if ($plan->slug === 'enterprise' && $user->role !== 'admin') {
            throw new AuthorizationException('Kurumsal (Enterprise) plan geçişi için yönetici onayı gereklidir.');
        }

        return DB::transaction(function () use ($storeId, $plan): SupportCommercialSubscription {
            $active = SupportCommercialSubscription::where('store_id', $storeId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();
            $samePlan = $active->firstWhere('plan_id', $plan->id);
            if ($samePlan) {
                return $samePlan;
            }

            SupportCommercialSubscription::where('store_id', $storeId)
                ->where('status', 'active')
                ->update(['status' => 'expired']);

            return SupportCommercialSubscription::create([
                'store_id'  => $storeId,
                'plan_id'   => $plan->id,
                'status'    => 'active',
                'starts_at' => now(),
                'ends_at'   => now()->addMonths(1),
            ]);
        });
    }

    /**
     * Billing readiness usage export (ZOLM Excel/CSV standardına uygun).
     */
    public function generateBillingExport(int $storeId, string $month, ?User $user = null): string
    {
        $user = $user ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $user);

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw new \InvalidArgumentException('Faturalama ayı YYYY-MM formatında olmalıdır.');
        }
        $periodStart = \Carbon\Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $events = SupportEntitlementEvent::where('store_id', $storeId)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->get();

        $redactor = app(\App\Services\Support\Security\PiiRedactor::class);

        $lines = [];
        $lines[] = "\xEF\xBB\xBF" . "ID;Feature;Status;Reason;Date"; // UTF-8 BOM ve CSV header

        foreach ($events as $event) {
            $reason = $event->context['reason'] ?? '';

            // PII Redaction
            $reason = $redactor->maskPii($reason);

            // XML/CSV sanitization
            $reason = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $reason);
            $reason = str_replace(["\r", "\n"], ' ', $reason);
            $reason = str_replace(';', ',', $reason);
            if (preg_match('/^[=+\-@]/', ltrim($reason))) {
                $reason = "'" . $reason;
            }

            $lines[] = "{$event->id};{$event->feature};{$event->status};{$reason};{$event->created_at->toIso8601String()}";
        }

        return implode("\n", $lines);
    }
}
