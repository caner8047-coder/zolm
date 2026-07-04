<?php

namespace App\Console\Commands;

use App\Models\WaOutbox;
use App\Jobs\WhatsApp\SendWaMessageJob;
use Illuminate\Console\Command;

class WhatsAppProcessOutboxCommand extends Command
{
    protected $signature = 'whatsapp:process-outbox
        {--limit=50 : İşlenecek maksimum mesaj sayısı}';

    protected $description = 'Kuyruktaki WhatsApp mesajlarını işler ve gönderir.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $outboxMessages = WaOutbox::queued()
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        if ($outboxMessages->isEmpty()) {
            $this->info('Kuyrukta mesaj yok.');
            return self::SUCCESS;
        }

        $dispatched = 0;

        foreach ($outboxMessages as $outbox) {
            SendWaMessageJob::dispatch($outbox->id);
            $dispatched++;
        }

        $this->info("{$dispatched} mesaj kuyruğa alındı.");

        return self::SUCCESS;
    }
}
