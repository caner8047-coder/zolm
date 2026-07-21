<?php

namespace App\Modules\Hr\Document\Listeners;

use App\Modules\Hr\Core\Services\HrCacheKey;

class InvalidateDocumentMetricsCache
{
    public function handle(object $event): void
    {
        if (property_exists($event, 'legalEntityId') && $event->legalEntityId) {
            // Tenant bazlı belge metrik cache'ini geçersiz kılar. Event, metrikleri
            // etkileyen işlemi yapan tenant context'inde yayınlandığı için doğru anahtar temizlenir.
            HrCacheKey::forget('document', 'metrics');
        }
    }
}
