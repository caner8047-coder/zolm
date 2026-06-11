<?php

namespace App\Console\Commands;

use App\Services\NotificationCenterService;
use Illuminate\Console\Command;

class PruneExpiredNotificationsCommand extends Command
{
    protected $signature = 'notifications:prune-expired
        {--read-hours= : Okunmuş bildirimler için saat bazlı saklama süresi}
        {--unread-days= : Okunmamış bildirimler için gün bazlı saklama süresi}';

    protected $description = 'Saklama süresi dolan uygulama bildirimlerini temizler.';

    public function handle(NotificationCenterService $notificationCenter): int
    {
        $result = $notificationCenter->pruneExpiredNotifications(
            $this->positiveIntegerOption('read-hours'),
            $this->positiveIntegerOption('unread-days'),
        );

        $this->components->info(sprintf(
            'Bildirim temizliği tamamlandı. Okunmuş: %d, okunmamış: %d, toplam: %d.',
            $result['read_deleted'],
            $result['unread_deleted'],
            $result['total_deleted'],
        ));

        return self::SUCCESS;
    }

    protected function positiveIntegerOption(string $name): ?int
    {
        $value = $this->option($name);

        if ($value === null || $value === '') {
            return null;
        }

        return max(1, (int) $value);
    }
}
