<?php

namespace App\Services\WhatsApp;

use App\Models\WaOutbox;
use App\Models\WaWebhookEvent;
use App\Models\WaMessageDelivery;
use App\Models\WaInboundMessage;
use App\Models\WaConversation;
use App\Models\WaRetentionRun;
use App\Models\WaAiRun;
use App\Models\WaAiToolCall;
use App\Models\WaCampaignEvent;
use App\Models\SupportMessage;
use Illuminate\Support\Facades\DB;

class RetentionCleanupService
{
    /**
     * Retention cleanup'ı çalıştır
     */
    public function run(?int $storeId = null): array
    {
        $tables = [
            'wa_webhook_events' => config('whatsapp.retention.webhook_events_days', 90),
            'wa_inbound_messages' => config('whatsapp.retention.inbound_messages_days', 180),
            'wa_ai_runs' => config('whatsapp.retention.webhook_events_days', 90),
            'wa_ai_tool_calls' => config('whatsapp.retention.webhook_events_days', 90),
            'wa_campaign_events' => config('whatsapp.retention.audit_logs_days', 365),
            'support_messages' => config('whatsapp.retention.inbound_messages_days', 180),
        ];

        // Outbox sadece terminal status'lardan
        $outboxDays = config('whatsapp.retention.outbox_completed_days', 60);

        // Delivery logs
        $deliveryDays = config('whatsapp.retention.delivery_logs_days', 180);

        $results = [];

        foreach ($tables as $table => $days) {
            $results[$table] = $this->cleanupTable($table, $days, $storeId);
        }

        // Outbox cleanup
        $results['wa_outbox'] = $this->cleanupOutbox($outboxDays, $storeId);

        // Delivery logs cleanup
        $results['wa_message_deliveries'] = $this->cleanupDeliveryLogs($deliveryDays, $storeId);

        // Aktif konuşmaları koru
        $this->protectActiveConversations();

        return $results;
    }

    private function cleanupTable(string $table, int $days, ?int $storeId): int
    {
        $cutoff = now()->subDays($days);

        $query = DB::table($table)->where('created_at', '<', $cutoff);

        if ($storeId && in_array($table, ['wa_ai_runs', 'wa_ai_tool_calls', 'wa_campaign_events'])) {
            $query->where('store_id', $storeId);
        }

        $count = $query->count();

        if ($count > 0) {
            WaRetentionRun::create([
                'store_id' => $storeId,
                'target_table' => $table,
                'records_deleted' => $count,
                'started_at' => now(),
                'completed_at' => now(),
            ]);

            $query->delete();
        }

        return $count;
    }

    private function cleanupOutbox(int $days, ?int $storeId): int
    {
        $terminalStatuses = ['sent', 'delivered', 'read', 'failed', 'cancelled'];
        $cutoff = now()->subDays($days);

        $query = WaOutbox::whereIn('status', $terminalStatuses)
            ->where('created_at', '<', $cutoff);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $count = $query->count();

        if ($count > 0) {
            WaRetentionRun::create([
                'store_id' => $storeId,
                'target_table' => 'wa_outbox',
                'records_deleted' => $count,
                'started_at' => now(),
                'completed_at' => now(),
            ]);

            $query->delete();
        }

        return $count;
    }

    private function cleanupDeliveryLogs(int $days, ?int $storeId): int
    {
        $cutoff = now()->subDays($days);

        $query = WaMessageDelivery::where('created_at', '<', $cutoff);

        if ($storeId) {
            $query->whereHas('outbox', fn ($q) => $q->where('store_id', $storeId));
        }

        $count = $query->count();

        if ($count > 0) {
            WaRetentionRun::create([
                'store_id' => $storeId,
                'target_table' => 'wa_message_deliveries',
                'records_deleted' => $count,
                'started_at' => now(),
                'completed_at' => now(),
            ]);

            $query->delete();
        }

        return $count;
    }

    /**
     * Aktif konuşmaları retention'dan koru
     */
    private function protectActiveConversations(): void
    {
        // Açık konuşma ID'lerini topla
        $activeConversationIds = WaConversation::where('status', 'open')
            ->pluck('id')
            ->toArray();

        // İlgili AI run'ları ve inbound mesajları koru
        if (!empty($activeConversationIds)) {
            WaAiRun::whereIn('conversation_id', $activeConversationIds)
                ->where('created_at', '<', now()->subDays(90))
                ->update(['created_at' => now()]);

            WaInboundMessage::whereIn('conversation_id', $activeConversationIds)
                ->where('created_at', '<', now()->subDays(90))
                ->update(['created_at' => now()]);
        }
    }
}
