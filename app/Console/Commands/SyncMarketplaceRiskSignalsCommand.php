<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Marketplace\MarketplaceRiskSignalService;
use Illuminate\Console\Command;

class SyncMarketplaceRiskSignalsCommand extends Command
{
    protected $signature = 'marketplace:sync-risk-signals
        {--user= : Yalnızca belirtilen kullanıcıyı çalıştır}
        {--limit=100 : Tek çalışmada işlenecek azami kullanıcı}';

    protected $description = 'Pazaryeri risk sinyallerini yeniler ve bildirim tercihleri doğrultusunda uyarı üretir';

    public function handle(MarketplaceRiskSignalService $riskSignals): int
    {
        if (! config('marketplace.features.risk_center_enabled', false)) {
            $this->components->warn('Risk Merkezi feature flag kapalı.');

            return self::SUCCESS;
        }

        $userId = (int) $this->option('user');
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $query = User::query()
            ->where('is_active', true)
            ->whereHas('marketplaceStores')
            ->orderBy('id');

        if ($userId > 0) {
            $query->whereKey($userId);
        }

        $users = $query->limit($limit)->get(['id', 'email']);
        $signalCount = 0;
        $notificationCount = 0;

        foreach ($users as $user) {
            $result = $riskSignals->syncForUser((int) $user->id);
            $signalCount += $result['signals'];
            $notificationCount += $result['notifications'];
            $this->line("{$user->email}: {$result['signals']} sinyal, {$result['notifications']} yeni bildirim");
        }

        $this->newLine();
        $this->components->info(
            "{$users->count()} kullanıcı işlendi; {$signalCount} sinyal ve {$notificationCount} yeni bildirim."
        );

        return self::SUCCESS;
    }
}
