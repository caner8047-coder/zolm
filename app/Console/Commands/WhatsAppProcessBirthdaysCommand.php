<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\BirthdayService;
use Illuminate\Console\Command;

class WhatsAppProcessBirthdaysCommand extends Command
{
    protected $signature = 'whatsapp:process-birthdays';

    protected $description = 'Bugünün doğum günü müşterilerine mesaj gönderir.';

    public function handle(BirthdayService $service): int
    {
        $sent = $service->processBirthdayMessages();

        $this->info("{$sent} doğum günü mesajı gönderildi.");

        return self::SUCCESS;
    }
}
