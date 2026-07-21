<?php

namespace App\Modules\Hr\Asset\Actions;

use App\Modules\Hr\Asset\Enums\AssetAssignmentStatus; use App\Modules\Hr\Asset\Enums\AssetStatus; use App\Modules\Hr\Asset\Models\HrAsset; use App\Modules\Hr\Asset\Models\HrAssetAssignment; use App\Modules\Hr\Asset\Models\HrAssetEvent; use App\Modules\Hr\Core\Services\HrAuditService; use App\Modules\Hr\Core\Services\HrIntegrationOutboxService; use App\Modules\Hr\Core\Services\TenantContext; use Illuminate\Support\Facades\DB;

class ReturnAssetAction
{
    public function __construct(private HrAuditService $audit, private HrIntegrationOutboxService $outbox) {}
    public function execute(HrAssetAssignment $assignment, string $condition = 'good', ?string $note = null, bool $lost = false): HrAssetAssignment
    {
        $tenant = app(TenantContext::class)->getId(); abort_unless(auth()->user()?->hasHrPermission('hr.assets.return'), 403); abort_unless($assignment->legal_entity_id === $tenant, 404);
        return DB::transaction(function () use ($assignment, $condition, $note, $lost, $tenant) {
            $row = HrAssetAssignment::withoutGlobalScope('tenant')->whereKey($assignment->id)->lockForUpdate()->firstOrFail(); abort_unless($row->status === AssetAssignmentStatus::Assigned, 422, 'Bu zimmet daha önce kapatılmış.');
            $asset = HrAsset::withoutGlobalScope('tenant')->whereKey($row->asset_id)->lockForUpdate()->firstOrFail(); $assignmentStatus = $lost ? AssetAssignmentStatus::Lost : AssetAssignmentStatus::Returned; $assetStatus = $lost ? AssetStatus::Lost : AssetStatus::Available;
            $row->update(['status' => $assignmentStatus, 'returned_at' => now(), 'return_note' => $note, 'condition_on_return' => $condition, 'returned_by' => auth()->id()]); $asset->update(['status' => $assetStatus, 'updated_by' => auth()->id()]);
            HrAssetEvent::create(['legal_entity_id' => $tenant, 'asset_id' => $asset->id, 'asset_assignment_id' => $row->id, 'event_type' => $lost ? 'lost' : 'returned', 'from_status' => AssetStatus::Assigned->value, 'to_status' => $assetStatus->value, 'note' => $note, 'metadata' => ['condition' => $condition], 'acted_by' => auth()->id(), 'created_at' => now()]);
            if ($asset->stock_item_reference) $this->outbox->enqueue('stock', $lost ? 'asset_lost' : 'asset_returned', $row, 'hr-asset-close-'.$row->id, ['asset_id' => $asset->id, 'assignment_id' => $row->id, 'stock_item_reference' => $asset->stock_item_reference, 'status' => $assetStatus->value, 'condition' => $condition]);
            $this->audit->log($lost ? 'asset_lost' : 'asset_returned', $asset, null, ['condition' => $condition]); return $row->fresh();
        });
    }
}
