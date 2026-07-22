<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStore;
use App\Services\NotificationCenterService;
use App\Models\AppNotification;

class MarketplacePricePilotNotificationService
{
    protected NotificationCenterService $notificationCenter;

    public function __construct(NotificationCenterService $notificationCenter)
    {
        $this->notificationCenter = $notificationCenter;
    }

    public function notifyEmergencyStopActivated(int $storeId, string $reason): ?AppNotification
    {
        $store = MarketplaceStore::find($storeId);
        if (!$store) return null;

        $hourKey = now()->format('YmdH');
        return $this->notificationCenter->createForStore($store, [
            'type' => 'risk_critical',
            'severity' => 'danger',
            'title' => 'Fiyat Acil Durdurma Aktif',
            'body' => "{$store->store_name} için acil fiyat durdurma tetiklendi. Gerekçe: {$reason}",
            'event_key' => "pilot_emergency_stop_activated_{$storeId}_{$hourKey}",
        ]);
    }

    public function notifyEmergencyStopDeactivated(int $storeId): ?AppNotification
    {
        $store = MarketplaceStore::find($storeId);
        if (!$store) return null;

        $hourKey = now()->format('YmdH');
        return $this->notificationCenter->createForStore($store, [
            'type' => 'booster_price_rise',
            'severity' => 'info',
            'title' => 'Fiyat Acil Durdurma Kaldırıldı',
            'body' => "{$store->store_name} için acil fiyat durdurma devreden çıkarıldı.",
            'event_key' => "pilot_emergency_stop_deactivated_{$storeId}_{$hourKey}",
        ]);
    }

    public function notifyMinimumPriceViolation(int $storeId, string $barcode, float $price, float $minPrice): ?AppNotification
    {
        $store = MarketplaceStore::find($storeId);
        if (!$store) return null;

        $hourKey = now()->format('YmdH');
        return $this->notificationCenter->createForStore($store, [
            'type' => 'risk_critical',
            'severity' => 'danger',
            'title' => 'Minimum Fiyat İhlali Girişimi',
            'body' => "Barkod: {$barcode} için önerilen fiyat (₺{$price}) minimum güvenli fiyatın (₺{$minPrice}) altındadır. Aksiyon engellendi.",
            'event_key' => "pilot_min_price_violation_{$storeId}_{$barcode}_{$hourKey}",
        ]);
    }

    public function notifyTenantIsolationViolation(int $storeId, int $userId): ?AppNotification
    {
        $store = MarketplaceStore::find($storeId);
        if (!$store) return null;

        $hourKey = now()->format('YmdH');
        return $this->notificationCenter->createForStore($store, [
            'type' => 'risk_critical',
            'severity' => 'danger',
            'title' => 'Yetkisiz Erişim Girişimi (Tenant Isolation)',
            'body' => "Kullanıcı (ID: {$userId}) {$store->store_name} mağazasının verilerine yetkisiz erişim veya işlem yapmaya çalıştı.",
            'event_key' => "pilot_tenant_isolation_{$storeId}_{$userId}_{$hourKey}",
        ]);
    }

    public function notifyShadowAccuracyDrop(int $storeId, float $accuracy): ?AppNotification
    {
        $store = MarketplaceStore::find($storeId);
        if (!$store) return null;

        $hourKey = now()->format('YmdH');
        return $this->notificationCenter->createForStore($store, [
            'type' => 'risk_warning',
            'severity' => 'warning',
            'title' => 'Shadow Mode Doğruluk Oranı Düşük',
            'body' => "Son gölge tahminlerin doğruluk oranı %{$accuracy} seviyesine geriledi.",
            'event_key' => "pilot_shadow_accuracy_{$storeId}_{$hourKey}",
        ]);
    }

    public function notifyStaleBuyboxData(int $storeId, int $hoursStale): ?AppNotification
    {
        $store = MarketplaceStore::find($storeId);
        if (!$store) return null;

        $hourKey = now()->format('YmdH');
        return $this->notificationCenter->createForStore($store, [
            'type' => 'risk_warning',
            'severity' => 'warning',
            'title' => 'Stale Buybox Verisi Uyarısı',
            'body' => "Buybox verisi en son {$hoursStale} saat önce güncellendi. Fiyatlama önerileri durduruldu.",
            'event_key' => "pilot_stale_buybox_{$storeId}_{$hourKey}",
        ]);
    }

    public function notifyApiFailure(int $storeId, string $errorCode, string $message): ?AppNotification
    {
        $store = MarketplaceStore::find($storeId);
        if (!$store) return null;

        $hourKey = now()->format('YmdH');
        return $this->notificationCenter->createForStore($store, [
            'type' => 'integration_failed',
            'severity' => 'danger',
            'title' => 'Trendyol API Hatası',
            'body' => "Fiyat gönderimi başarısız. Hata kodu: {$errorCode}. Mesaj: {$message}",
            'event_key' => "pilot_api_failure_{$storeId}_{$errorCode}_{$hourKey}",
        ]);
    }

    public function notifyQueueDelay(int $storeId, int $delaySeconds): ?AppNotification
    {
        $store = MarketplaceStore::find($storeId);
        if (!$store) return null;

        $hourKey = now()->format('YmdH');
        return $this->notificationCenter->createForStore($store, [
            'type' => 'risk_warning',
            'severity' => 'warning',
            'title' => 'Kuyruk Gecikmesi Alarmı',
            'body' => "Fiyat push kuyruk job'ı {$delaySeconds} saniyedir işlenmeyi bekliyor.",
            'event_key' => "pilot_queue_delay_{$storeId}_{$hourKey}",
        ]);
    }

    public function notifyCanaryAutoPaused(int $storeId, string $reason): ?AppNotification
    {
        $store = MarketplaceStore::find($storeId);
        if (!$store) return null;

        $hourKey = now()->format('YmdH');
        return $this->notificationCenter->createForStore($store, [
            'type' => 'risk_critical',
            'severity' => 'danger',
            'title' => 'Canary Pilot Otomatik Durduruldu',
            'body' => "Canary otomatik olarak askıya alındı. Nedeni: {$reason}",
            'event_key' => "canary_auto_paused_{$storeId}_{$hourKey}",
        ]);
    }
}
