<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Crm\CrmProjectionService;
use Illuminate\Console\Command;

class ProjectCrmWorkspaceCommand extends Command
{
    protected $signature = 'crm:project
        {--user-id= : Sadece belirli kullanıcı için CRM projeksiyonu çalıştır}
        {--source=* : Kaynak filtresi: orders, questions, returns, claims, cargo, supply}
        {--since= : Bu tarihten sonraki kayıtları işle (örn. 2026-04-01)}
        {--recent-days= : Son N günü işle}';

    protected $description = 'Pazaryeri, iade, kargo ve tedarik verilerini CRM çalışma alanına projekte eder.';

    public function handle(CrmProjectionService $projectionService): int
    {
        if (!$projectionService->tablesReady()) {
            $this->error('CRM tabloları hazır değil. Önce migration çalıştırın.');

            return self::FAILURE;
        }

        $users = User::query()
            ->when($this->option('user-id'), fn ($query, $userId) => $query->whereKey((int) $userId))
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($users->isEmpty()) {
            $this->warn('Projeksiyon için aktif kullanıcı bulunamadı.');

            return self::SUCCESS;
        }

        foreach ($users as $user) {
            $summary = $projectionService->projectUser($user, [
                'sources' => (array) $this->option('source'),
                'since' => $this->option('since'),
                'recent_days' => $this->option('recent-days'),
            ]);

            $this->line(sprintf(
                '#%d %s: %d kişi, %d olay, %d cari hareket, %d yeni vaka, %d CRM uyarısı işlendi.',
                $user->id,
                $user->name,
                $summary['contacts'],
                $summary['events'],
                $summary['ledger_entries'] ?? 0,
                $summary['cases'],
                $summary['alerts'] ?? 0,
            ));
        }

        return self::SUCCESS;
    }
}
