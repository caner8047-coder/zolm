<?php

namespace App\Livewire\CustomerCare;

use App\Models\MarketplaceStore;
use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportAgentAction;
use App\Models\SupportDispatch;
use App\Models\SupportKnowledgeSuggestion;
use App\Models\SupportAiEvalRun;
use App\Services\Support\TenantContext;
use App\Services\Support\CustomerCarePilotReadinessService;
use App\Services\Support\CustomerCarePilotMonitorService;
use App\Services\Support\AI\CustomerCareEvalService;
use Livewire\Component;

class AdminCenter extends Component
{
    public function mount()
    {
        // 1. Feature Flag Protection
        if (!config('customer-care.admin_center_enabled', false)) {
            abort(404, 'Müşteri hizmetleri yönetici kontrol paneli aktif değil.');
        }

        // 2. Admin Role Check
        $user = auth()->user() ?? TenantContext::getSystemActor();
        if ($user->role !== 'admin') {
            abort(403, 'Bu sayfaya erişim yetkiniz bulunmamaktadır.');
        }
    }

    public function exportAuditCsv(int $storeId)
    {
        $user = auth()->user() ?? TenantContext::getSystemActor();
        if ($user->role !== 'admin') {
            abort(403);
        }

        $store = MarketplaceStore::findOrFail($storeId);

        $channelIds = SupportChannel::where('store_id', $storeId)->pluck('id')->toArray();

        $actions = SupportAgentAction::where(function ($query) use ($storeId, $channelIds) {
            $query->whereHas('conversation', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            })->orWhere(function ($q) use ($storeId, $channelIds) {
                $q->whereNull('conversation_id')
                  ->where(function ($sub) use ($storeId, $channelIds) {
                      $sub->where('details_json->store_id', $storeId);
                      if (!empty($channelIds)) {
                          $sub->orWhereIn('details_json->channel_id', $channelIds);
                      }
                  });
            });
        })->latest()->get();

        $filename = "zolm-cc-audit-store-{$storeId}-" . now()->format('YmdHis') . ".csv";

        return response()->streamDownload(function () use ($actions, $store) {
            // UTF-8 BOM
            echo "\xEF\xBB\xBF";

            $handle = fopen('php://output', 'w');

            // Header
            fputcsv($handle, ['Action ID', 'Conversation ID', 'User ID', 'Action', 'Created At', 'Details (PII Redacted)']);

            $redactor = app(\App\Services\Support\Security\PiiRedactor::class);

            foreach ($actions as $action) {
                // XML control characters cleaning helper
                $cleanDetails = '';
                if ($action->details_json) {
                    $jsonStr = json_encode($action->details_json, JSON_UNESCAPED_UNICODE);
                    $masked = $redactor->maskPii($jsonStr);
                    $cleanDetails = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $masked);
                }

                $cleanAction = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $action->action);

                fputcsv($handle, [
                    $action->id,
                    $action->conversation_id ?? 'System/Global',
                    $action->user_id ?? 'AI/System',
                    $cleanAction,
                    $action->created_at->format('Y-m-d H:i:s'),
                    $cleanDetails
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    protected function getStoresSummary()
    {
        $stores = MarketplaceStore::all();
        $readinessService = app(CustomerCarePilotReadinessService::class);
        $monitorService = app(CustomerCarePilotMonitorService::class);
        $evalService = app(CustomerCareEvalService::class);

        $summary = [];

        foreach ($stores as $store) {
            $readiness = $readinessService->checkReadiness($store->id);
            $metrics = $monitorService->getStoreMetrics($store->id);
            $latestEval = $evalService->getLatestGoldenEval($store->id);

            $last24hDrafts = SupportMessage::whereHas('conversation', fn($q) => $q->where('store_id', $store->id))
                ->where('sender_type', 'ai')
                ->where('delivery_status', 'draft')
                ->where('created_at', '>=', now()->subDay())
                ->count();

            $last24hAutoReplies = SupportMessage::whereHas('conversation', fn($q) => $q->where('store_id', $store->id))
                ->where('sender_type', 'ai')
                ->where('direction', 'outbound')
                ->where('delivery_status', 'sent')
                ->where('created_at', '>=', now()->subDay())
                ->count();

            $last24hPolicyBlocks = SupportAgentAction::whereHas('conversation', fn($q) => $q->where('store_id', $store->id))
                ->where('action', 'policy_block')
                ->where('created_at', '>=', now()->subDay())
                ->count();

            $last24hHandoffs = SupportAgentAction::whereHas('conversation', fn($q) => $q->where('store_id', $store->id))
                ->where('action', 'human_handoff')
                ->where('created_at', '>=', now()->subDay())
                ->count();

            $pendingDispatches = SupportDispatch::where('status', 'pending')
                ->whereHas('conversation', fn($q) => $q->where('store_id', $store->id))
                ->count();

            $suggestionBacklog = SupportKnowledgeSuggestion::where('store_id', $store->id)
                ->where('status', 'pending')
                ->count();

            $summary[] = [
                'id' => $store->id,
                'name' => $store->store_name,
                'marketplace' => $store->marketplace,
                'ready' => $readiness['ready'],
                'circuit_breaker' => $metrics['circuit_breaker_status'] ?? 'closed',
                'latest_eval_score' => $latestEval['average_score'] ?? 'N/A',
                'last_24h_drafts' => $last24hDrafts,
                'last_24h_auto_replies' => $last24hAutoReplies,
                'last_24h_policy_blocks' => $last24hPolicyBlocks,
                'last_24h_handoffs' => $last24hHandoffs,
                'pending_dispatches' => $pendingDispatches,
                'suggestion_backlog' => $suggestionBacklog,
            ];
        }

        return $summary;
    }

    public function render()
    {
        return view('livewire.customer-care.admin-center', [
            'storesSummary' => $this->getStoresSummary(),
        ])->layout('layouts.app');
    }
}
