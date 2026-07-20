<?php

namespace App\Services\Demo;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Demo kullanıcı silinmeden önce nullOnDelete / restrictOnDelete köklerini
 * hedef mağaza ID'leriyle temizler. Geniş tablo temizliği veya truncate yapmaz.
 */
class ZolmDemoTenantResetter
{
    public function reset(User $user): void
    {
        $storeIds = DB::table('marketplace_stores')
            ->where('user_id', $user->id)
            ->pluck('id');

        if ($storeIds->isNotEmpty()) {
            $this->deleteSupportRoots($storeIds);
            $this->deleteWhatsAppRoots($storeIds);

            foreach ([
                ['integration_webhook_events', 'store_id'],
                ['mp_product_change_logs', 'store_id'],
                ['support_api_access_logs', 'store_id'],
                ['wa_retention_runs', 'store_id'],
                ['wa_automation_configs', 'store_id'],
            ] as [$table, $column]) {
                $this->deleteWhereIn($table, $column, $storeIds);
            }
        }

        $user->delete();
    }

    /** @param Collection<int, int> $storeIds */
    private function deleteSupportRoots(Collection $storeIds): void
    {
        if (! Schema::hasTable('support_channels')) {
            return;
        }

        $channelIds = DB::table('support_channels')
            ->whereIn('store_id', $storeIds)
            ->pluck('id');

        $conversationIds = Schema::hasTable('support_conversations')
            ? DB::table('support_conversations')->whereIn('support_channel_id', $channelIds)->pluck('id')
            : collect();

        if (Schema::hasTable('support_dispatches') && $channelIds->isNotEmpty()) {
            $dispatchIds = DB::table('support_dispatches')
                ->whereIn('support_channel_id', $channelIds)
                ->pluck('id');

            $this->deleteWhereIn('support_dispatch_attempts', 'support_dispatch_id', $dispatchIds);
            $this->deleteWhereIn('support_dispatches', 'id', $dispatchIds);
        }

        $this->deleteWhereIn('support_ai_runs', 'store_id', $storeIds);
        $this->deleteWhereIn('support_knowledge_suggestions', 'store_id', $storeIds);
        $this->deleteWhereIn('support_ai_eval_runs', 'store_id', $storeIds);

        if ($conversationIds->isNotEmpty()) {
            $this->deleteWhereIn('support_ai_runs', 'conversation_id', $conversationIds);
        }

        $this->deleteWhereIn('support_channels', 'id', $channelIds);
    }

    /** @param Collection<int, int> $storeIds */
    private function deleteWhatsAppRoots(Collection $storeIds): void
    {
        $this->deleteWhereIn('wa_knowledge_articles', 'store_id', $storeIds);
        $this->deleteWhereIn('wa_accounts', 'store_id', $storeIds);
        $this->deleteWhereIn('wa_settings', 'store_id', $storeIds);
    }

    /** @param Collection<int, int> $ids */
    private function deleteWhereIn(string $table, string $column, Collection $ids): void
    {
        if ($ids->isEmpty() || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->whereIn($column, $ids)->delete();
    }
}
