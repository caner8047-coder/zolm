<?php

namespace App\Modules\Hr\Asset\Actions;

use App\Modules\Hr\Asset\Enums\AssetAssignmentStatus;
use App\Modules\Hr\Asset\Models\HrAssetAssignment;
use App\Modules\Hr\Asset\Models\HrAssetEvent;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use Illuminate\Support\Facades\DB;

class AcceptAssetAssignmentAction
{
    public function __construct(private HrAuditService $audit) {}

    public function execute(HrAssetAssignment $assignment, bool $accepted): HrAssetAssignment
    {
        abort_unless($accepted, 422, 'Zimmet teslim beyanı onaylanmalıdır.');
        abort_unless($assignment->legal_entity_id === app(TenantContext::class)->getId(), 404);
        abort_unless($assignment->employee()->where('user_id', auth()->id())->exists(), 403);
        return DB::transaction(function () use ($assignment) {
            $row = HrAssetAssignment::withoutGlobalScope('tenant')->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            abort_unless($row->status === AssetAssignmentStatus::Assigned, 422, 'Aktif olmayan zimmet kabul edilemez.');
            if ($row->accepted_at) return $row;
            $row->update(['accepted_at' => now(), 'accepted_by' => auth()->id(), 'acceptance_ip' => request()->ip(), 'acceptance_statement_version' => 'v1']);
            HrAssetEvent::create(['legal_entity_id' => $row->legal_entity_id, 'asset_id' => $row->asset_id, 'asset_assignment_id' => $row->id, 'event_type' => 'accepted', 'from_status' => 'assigned', 'to_status' => 'assigned', 'note' => 'Çalışan dijital teslim beyanını onayladı.', 'metadata' => ['statement_version' => 'v1'], 'acted_by' => auth()->id(), 'created_at' => now()]);
            $this->audit->log('asset_assignment_accepted', $row, null, ['statement_version' => 'v1']);
            return $row->fresh();
        });
    }
}
