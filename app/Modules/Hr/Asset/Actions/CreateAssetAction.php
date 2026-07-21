<?php

namespace App\Modules\Hr\Asset\Actions;

use App\Modules\Hr\Asset\Enums\AssetStatus;
use App\Modules\Hr\Asset\Models\HrAsset;
use App\Modules\Hr\Asset\Models\HrAssetCategory;
use App\Modules\Hr\Asset\Models\HrAssetEvent;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\HrIntegrationOutboxService;
use App\Modules\Hr\Core\Services\TenantContext;
use Illuminate\Support\Facades\DB;

class CreateAssetAction
{
    public function __construct(private HrAuditService $audit, private HrIntegrationOutboxService $outbox) {}

    public function execute(HrAssetCategory $category, array $data): HrAsset
    {
        $tenant = app(TenantContext::class)->getId();
        abort_unless(auth()->user()?->hasHrPermission('hr.assets.manage'), 403);
        abort_unless($category->legal_entity_id === $tenant && HrAssetCategory::withoutGlobalScope('tenant')->whereKey($category->id)->where('legal_entity_id', $tenant)->where('is_active', true)->exists(), 422);
        $code = strtoupper(trim((string) ($data['asset_code'] ?? ''))); $name = trim((string) ($data['name'] ?? ''));
        abort_if(blank($code) || blank($name), 422);
        foreach (['asset_code' => $code, 'serial_number' => $data['serial_number'] ?? null, 'barcode' => $data['barcode'] ?? null] as $field => $value) if (filled($value)) abort_if(HrAsset::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant)->where($field, trim($value))->exists(), 422, "{$field} zaten kullanılıyor.");

        return DB::transaction(function () use ($tenant, $category, $data, $code, $name) {
            $asset = HrAsset::create(['legal_entity_id' => $tenant, 'asset_category_id' => $category->id, 'asset_code' => $code, 'name' => $name, 'brand' => $data['brand'] ?? null, 'model' => $data['model'] ?? null, 'serial_number' => filled($data['serial_number'] ?? null) ? trim($data['serial_number']) : null, 'barcode' => filled($data['barcode'] ?? null) ? trim($data['barcode']) : null, 'stock_item_reference' => filled($data['stock_item_reference'] ?? null) ? trim($data['stock_item_reference']) : null, 'purchased_at' => $data['purchased_at'] ?? null, 'purchase_value' => $data['purchase_value'] ?? null, 'currency' => $data['currency'] ?? 'TRY', 'status' => AssetStatus::Available, 'notes' => $data['notes'] ?? null, 'created_by' => auth()->id(), 'updated_by' => auth()->id()]);
            HrAssetEvent::create(['legal_entity_id' => $tenant, 'asset_id' => $asset->id, 'event_type' => 'created', 'to_status' => AssetStatus::Available->value, 'acted_by' => auth()->id(), 'created_at' => now()]);
            if ($asset->stock_item_reference) $this->outbox->enqueue('stock', 'asset_registered', $asset, 'hr-asset-registered-'.$asset->id, ['asset_id' => $asset->id, 'asset_code' => $asset->asset_code, 'stock_item_reference' => $asset->stock_item_reference, 'status' => $asset->status->value]);
            $this->audit->log('asset_created', $asset, null, ['asset_code' => $code]);
            return $asset;
        });
    }
}
