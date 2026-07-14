<?php

namespace App\Console\Commands;

use App\Models\MarketplaceStore;
use App\Services\Support\CustomerCareChannelProvisioningService;
use App\Services\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CustomerCareProvisionChannelsCommand extends Command
{
    protected $signature = 'customer-care:provision-channels
        {--store= : Sadece belirtilen mağaza ID için çalıştır}
        {--all : Aktif tüm mağazalar için çalıştır}
        {--execute : Gerçekten kanal oluştur; verilmezse dry-run çalışır}';

    protected $description = 'Mevcut mağaza kayıtlarından AI Müşteri Merkezi destek kanallarını güvenli şekilde oluşturur.';

    public function handle(CustomerCareChannelProvisioningService $provisioningService): int
    {
        $storeOption = $this->option('store');
        $runAll = (bool) $this->option('all');
        $execute = (bool) $this->option('execute');

        if (!$storeOption && !$runAll) {
            $this->error('Lütfen --store=ID veya --all seçeneklerinden birini belirtin.');
            return self::FAILURE;
        }

        if ($storeOption && $runAll) {
            $this->error('--store ve --all aynı anda kullanılamaz.');
            return self::FAILURE;
        }

        try {
            $actor = TenantContext::getSystemActor();
        } catch (AuthorizationException $exception) {
            $this->error('System Actor hazır değil: ' . $exception->getMessage());
            return self::FAILURE;
        }

        $stores = $this->resolveStores($storeOption, $runAll);

        if ($stores->isEmpty()) {
            $this->warn('İşlenecek aktif mağaza bulunamadı.');
            return self::SUCCESS;
        }

        $mode = $execute ? 'EXECUTE' : 'DRY-RUN';
        $this->info("AI Müşteri Merkezi kanal provizyonu başlıyor. Mod: {$mode}");

        $rows = [];
        $totalCreated = 0;
        $totalExisting = 0;
        $totalCandidates = 0;

        foreach ($stores as $store) {
            try {
                if (!$execute) {
                    $available = $provisioningService->availableToProvision($store->id, $actor);
                    $totalCandidates += $available->count();

                    $rows[] = [
                        $store->id,
                        $store->store_name,
                        $store->marketplace ?: '-',
                        $this->formatCandidateNames($available),
                        $available->isEmpty() ? 'Hazır' : 'Oluşturulabilir',
                    ];

                    continue;
                }

                $result = $provisioningService->provisionForStore($store->id, $actor);
                $created = collect($result['created']);
                $existing = collect($result['existing']);
                $skipped = collect($result['skipped']);

                $totalCreated += $created->count();
                $totalExisting += $existing->count();

                $rows[] = [
                    $store->id,
                    $store->store_name,
                    $store->marketplace ?: '-',
                    $created->pluck('name')->implode(', ') ?: '-',
                    $this->formatExecuteStatus($created, $existing, $skipped),
                ];
            } catch (AuthorizationException $exception) {
                $rows[] = [
                    $store->id,
                    $store->store_name,
                    $store->marketplace ?: '-',
                    '-',
                    'Yetki reddedildi',
                ];
            }
        }

        $this->table(
            ['Store ID', 'Mağaza', 'Pazaryeri', $execute ? 'Oluşturulan' : 'Aday Kanallar', 'Durum'],
            $rows
        );

        if (!$execute) {
            $this->line("Dry-run tamamlandı. Oluşturulabilir kanal sayısı: {$totalCandidates}");
            $this->warn('Veritabanına yazılmadı. Gerçek işlem için --execute ekleyin.');
            return self::SUCCESS;
        }

        $this->info("Execute tamamlandı. Yeni: {$totalCreated}, zaten var: {$totalExisting}");
        $this->warn('Oluşturulan kanallar güvenli varsayılanlarla gelir: is_enabled=false, ai_mode=manual, auto_reply=false.');

        return self::SUCCESS;
    }

    private function resolveStores(?string $storeOption, bool $runAll): Collection
    {
        if ($runAll) {
            return MarketplaceStore::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->get();
        }

        $store = MarketplaceStore::query()->find($storeOption);

        return $store ? collect([$store]) : collect();
    }

    private function formatCandidateNames(Collection $available): string
    {
        return $available
            ->map(fn (array $channel) => $channel['name'] ?? $channel['key'] ?? '-')
            ->filter()
            ->implode(', ') ?: '-';
    }

    private function formatExecuteStatus(Collection $created, Collection $existing, Collection $skipped): string
    {
        $parts = [];

        if ($created->isNotEmpty()) {
            $parts[] = 'Yeni: ' . $created->count();
        }

        if ($existing->isNotEmpty()) {
            $parts[] = 'Zaten var: ' . $existing->count();
        }

        if ($skipped->isNotEmpty()) {
            $parts[] = 'Atlandı: ' . $skipped->count();
        }

        return implode(' / ', $parts) ?: 'Değişiklik yok';
    }
}
