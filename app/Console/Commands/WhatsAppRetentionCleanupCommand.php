<?php

namespace App\Console\Commands;

use App\Models\WaWebhookEvent;
use App\Models\WaOutbox;
use App\Models\WaMessageDelivery;
use App\Models\WaInboundMessage;
use App\Models\WaAuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WhatsAppRetentionCleanupCommand extends Command
{
    protected $signature = 'whatsapp:retention-cleanup
        {--dry-run : Sadece sayıları göster, silme}';

    protected $description = 'WhatsApp retention policy kapsamında eski kayıtları temizler.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $anonymize = (bool) config('whatsapp.retention.anonymize_on_delete', true);

        $retentionConfig = [
            'webhook_events' => (int) config('whatsapp.retention.webhook_events_days', 90),
            'inbound_messages' => (int) config('whatsapp.retention.inbound_messages_days', 180),
            'audit_logs' => (int) config('whatsapp.retention.audit_logs_days', 365),
            'outbox_completed' => (int) config('whatsapp.retention.outbox_completed_days', 60),
            'delivery_logs' => (int) config('whatsapp.retention.delivery_logs_days', 180),
        ];

        $counts = [];

        // Webhook Events
        $cutoff = now()->subDays($retentionConfig['webhook_events']);
        $counts['webhook_events'] = WaWebhookEvent::where('created_at', '<', $cutoff)->count();
        if (!$dryRun && $counts['webhook_events'] > 0) {
            WaWebhookEvent::where('created_at', '<', $cutoff)->delete();
        }

        // Inbound Messages
        $cutoff = now()->subDays($retentionConfig['inbound_messages']);
        $counts['inbound_messages'] = WaInboundMessage::where('created_at', '<', $cutoff)->count();
        if (!$dryRun && $counts['inbound_messages'] > 0) {
            WaInboundMessage::where('created_at', '<', $cutoff)->delete();
        }

        // Audit Logs
        $cutoff = now()->subDays($retentionConfig['audit_logs']);
        $counts['audit_logs'] = WaAuditLog::where('created_at', '<', $cutoff)->count();
        if (!$dryRun && $counts['audit_logs'] > 0) {
            WaAuditLog::where('created_at', '<', $cutoff)->delete();
        }

        // Outbox (sadece terminal status)
        $cutoff = now()->subDays($retentionConfig['outbox_completed']);
        $terminalStatuses = ['sent', 'delivered', 'read', 'failed', 'cancelled'];
        $counts['outbox_completed'] = WaOutbox::whereIn('status', $terminalStatuses)
            ->where('created_at', '<', $cutoff)
            ->count();
        if (!$dryRun && $counts['outbox_completed'] > 0) {
            WaOutbox::whereIn('status', $terminalStatuses)
                ->where('created_at', '<', $cutoff)
                ->delete();
        }

        // Delivery Logs
        $cutoff = now()->subDays($retentionConfig['delivery_logs']);
        $counts['delivery_logs'] = WaMessageDelivery::where('created_at', '<', $cutoff)->count();
        if (!$dryRun && $counts['delivery_logs'] > 0) {
            WaMessageDelivery::where('created_at', '<', $cutoff)->delete();
        }

        $total = array_sum($counts);

        if ($dryRun) {
            $this->info('DRY RUN — Silinecek kayıt sayıları:');
        } else {
            $this->info('Temizlenen kayıt sayıları:');
        }

        foreach ($counts as $table => $count) {
            $this->line("  {$table}: {$count}");
        }

        $this->info("Toplam: {$total}");

        return self::SUCCESS;
    }
}
