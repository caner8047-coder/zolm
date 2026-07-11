<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Accounting\AccountingPilotBacklogService;

class AccountingPilotBacklogCommand extends Command
{
    protected $signature = 'accounting:pilot-backlog {--user= : Pilot kullanıcı ID} {--json : JSON çıktı}';
    protected $description = 'Pilot geri bildirimleri triage edip fix sprint backlog\'u üretir';

    public function handle(): int
    {
        $userId  = $this->option('user') ? (int) $this->option('user') : null;
        $service = app(AccountingPilotBacklogService::class);

        $summary = $service->summary($userId);
        $items   = $service->build($userId);

        if ($this->option('json')) {
            $this->output->write(json_encode(
                compact('summary', 'items'),
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            ));
            return $summary['fix_now_count'] > 0 ? 1 : 0;
        }

        // --- CLI çıktısı ---
        $this->info('==================================================');
        $this->info('    ZOLM ERP PILOT FIX SPRINT BACKLOG REPORT      ');
        $this->info('==================================================');
        $this->line('');

        $this->comment('--- BACKLOG ÖZETİ ---');
        $this->table(
            ['Metrik', 'Değer'],
            [
                ['Toplam Açık', $summary['total_open']],
                ['Fix Now', $summary['fix_now_count']],
                ['Fix Next', $summary['fix_next_count']],
                ['Watch', $summary['watch_count']],
                ['Document', $summary['document_count']],
                ['En Yüksek Puan', $summary['top_priority_score']],
                ['Bloklanan Modüller', implode(', ', $summary['blocked_modules']) ?: '—'],
            ]
        );

        if (!empty($items)) {
            $this->line('');
            $this->comment('--- TOP 10 BACKLOG MADDESİ ---');
            $top = array_slice($items, 0, 10);
            $this->table(
                ['ID', 'Modül', 'Başlık', 'Severity', 'Score', 'Aksiyon', 'Faz'],
                array_map(fn ($i) => [
                    $i['id'],
                    $i['module'],
                    mb_substr($i['title'], 0, 35),
                    $i['severity'],
                    $i['priority_score'],
                    $i['recommended_action'],
                    $i['target_phase'],
                ], $top)
            );
        } else {
            $this->line('Açık geri bildirim bulunamadı.');
        }

        $this->line('');

        if ($summary['fix_now_count'] > 0) {
            $this->error("DİKKAT: {$summary['fix_now_count']} adet FIX NOW maddesi var — P23 hotfix gerekli!");
            return 1;
        }

        $this->info('Fix Now maddesi yok — pilot devam edebilir.');
        return 0;
    }
}
