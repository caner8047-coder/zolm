<?php

namespace App\Modules\Hr\Core\Services;

use App\Modules\Hr\Core\Models\HrIntegrationOutbox;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class HrIntegrationOutboxService
{
    public function enqueue(string $target, string $eventType, Model $source, string $sourceKey, array $payload): HrIntegrationOutbox
    {
        $tenantId = app(TenantContext::class)->getId();
        abort_unless((int) $source->getAttribute('legal_entity_id') === $tenantId, 422, 'Entegrasyon kaynağı başka tüzel kişiliğe ait.');
        abort_unless(in_array($target, ['finance', 'payroll', 'stock', 'crm', 'production'], true), 422, 'Entegrasyon hedefi geçersiz.');
        $payload = $this->sortRecursively($payload);
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

        return DB::transaction(function () use ($tenantId, $target, $eventType, $source, $sourceKey, $payload, $hash) {
            $existing = HrIntegrationOutbox::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('target', $target)->where('source_key', $sourceKey)->lockForUpdate()->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'Entegrasyon kaynak anahtarı farklı içerikle kullanılamaz.');
                return $existing;
            }
            return HrIntegrationOutbox::create(['legal_entity_id' => $tenantId, 'target' => $target, 'event_type' => $eventType, 'source_type' => $source::class, 'source_id' => $source->getKey(), 'source_key' => $sourceKey, 'payload_hash' => $hash, 'payload' => $payload, 'status' => 'pending', 'created_by' => auth()->id()]);
        });
    }

    private function sortRecursively(array $value): array
    {
        foreach ($value as &$item) if (is_array($item)) $item = $this->sortRecursively($item);
        if (!array_is_list($value)) ksort($value);
        return $value;
    }
}
