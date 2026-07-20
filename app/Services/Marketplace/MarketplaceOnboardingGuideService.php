<?php

namespace App\Services\Marketplace;

use App\Models\CargoCarrierAccount;
use App\Models\CargoReport;
use App\Models\ChannelListing;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\OrderFinancialEvent;
use App\Models\OrderProfitSnapshot;
use App\Models\ProductMatchIssue;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class MarketplaceOnboardingGuideService
{
    public function __construct(
        protected MarketplaceProfitCenterQueryService $profitCenter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summaryForUser(int $userId): array
    {
        $counts = $this->readCounts($userId);
        $steps = $this->buildSteps($counts);
        $completedSteps = count(array_filter($steps, fn (array $step) => $step['status'] === 'completed'));
        $totalSteps = count($steps);
        $readinessPercent = $totalSteps > 0 ? (int) round(($completedSteps / $totalSteps) * 100) : 0;
        $primaryAction = $this->primaryAction($steps);
        $blockers = array_values(array_filter($steps, fn (array $step) => in_array($step['status'], ['action', 'waiting'], true)));

        return [
            'enabled' => true,
            'status' => $this->summaryStatus($completedSteps, $totalSteps),
            'headline' => $this->headline($completedSteps, $totalSteps, $primaryAction),
            'summary' => $this->summaryText($completedSteps, $totalSteps, $primaryAction),
            'readiness_percent' => $readinessPercent,
            'completed_steps' => $completedSteps,
            'total_steps' => $totalSteps,
            'primary_action' => $primaryAction,
            'steps' => $steps,
            'blockers' => array_slice($blockers, 0, 4),
            'metrics' => $this->metrics($counts),
            'demo_actions' => $this->demoActions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function readCounts(int $userId): array
    {
        $storeScope = fn (Builder $query) => $query->where('user_id', $userId);
        $orderItemQuery = ChannelOrderItem::query()->whereHas('store', $storeScope);
        $productQuery = MpProduct::query()->where('user_id', $userId);
        $costReadiness = $this->profitCenter->costReadiness($userId);

        return [
            'legal_entities' => LegalEntity::query()->where('user_id', $userId)->where('is_active', true)->count(),
            'stores' => MarketplaceStore::query()->where('user_id', $userId)->count(),
            'active_stores' => MarketplaceStore::query()->where('user_id', $userId)->where('is_active', true)->count(),
            'connected_stores' => MarketplaceStore::query()
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->whereHas('connection', fn (Builder $query) => $query->whereIn('status', ['configured', 'verified', 'active', 'connected', 'demo']))
                ->count(),
            'products' => (clone $productQuery)->count(),
            'cost_ready_products' => (clone $productQuery)
                ->where('cogs', '>', 0)
                ->where('packaging_cost', '>', 0)
                ->count(),
            'missing_cost_products' => (clone $productQuery)
                ->where(fn (Builder $query) => $query
                    ->where('cogs', '<=', 0)
                    ->orWhere('packaging_cost', '<=', 0))
                ->count(),
            'logistics_ready_products' => (clone $productQuery)
                ->where('cargo_cost', '>', 0)
                ->where('desi', '>', 0)
                ->count(),
            'missing_logistics_products' => (clone $productQuery)
                ->where(fn (Builder $query) => $query
                    ->where('cargo_cost', '<=', 0)
                    ->orWhere('desi', '<=', 0))
                ->count(),
            'listings' => ChannelListing::query()->whereHas('store', $storeScope)->count(),
            'unmatched_listings' => ChannelListing::query()->whereHas('store', $storeScope)->whereNull('mp_product_id')->count(),
            'open_match_issues' => ProductMatchIssue::query()->whereHas('store', $storeScope)->where('match_status', 'pending')->count(),
            'orders' => ChannelOrder::query()->whereHas('store', $storeScope)->count(),
            'order_items' => (clone $orderItemQuery)->count(),
            'unmatched_order_items' => (clone $orderItemQuery)->whereNull('mp_product_id')->count(),
            'financial_events' => OrderFinancialEvent::query()->whereHas('store', $storeScope)->count(),
            'settled_financial_events' => OrderFinancialEvent::query()
                ->whereHas('store', $storeScope)
                ->where(fn (Builder $query) => $query
                    ->whereIn('status', ['posted', 'completed', 'settled'])
                    ->orWhereNotNull('settlement_date'))
                ->count(),
            'snapshots' => OrderProfitSnapshot::query()
                ->whereNull('channel_order_item_id')
                ->whereHas('store', $storeScope)
                ->count(),
            'confirmed_snapshots' => OrderProfitSnapshot::query()
                ->whereNull('channel_order_item_id')
                ->where('profit_state', 'confirmed')
                ->whereHas('store', $storeScope)
                ->count(),
            'cost_readiness' => $costReadiness,
            'shipments' => $this->shipmentCount($userId),
            'cargo_reports' => $this->cargoReportCount($userId),
            'cargo_accounts' => $this->cargoAccountCount($userId),
        ];
    }

    /**
     * @param  array<string, mixed>  $counts
     * @return array<int, array<string, mixed>>
     */
    protected function buildSteps(array $counts): array
    {
        $hasCompany = (int) $counts['legal_entities'] > 0;
        $hasConnectedStore = (int) $counts['connected_stores'] > 0;
        $hasProducts = ((int) $counts['products'] + (int) $counts['listings']) > 0;
        $matchingIssueCount = (int) $counts['open_match_issues'] + (int) $counts['unmatched_order_items'];
        $hasOrders = (int) $counts['orders'] > 0;
        $hasFinance = ((int) $counts['financial_events'] + (int) $counts['confirmed_snapshots']) > 0;
        $hasCargoProof = ((int) $counts['shipments'] + (int) $counts['cargo_reports'] + (int) $counts['cargo_accounts']) > 0;
        $hasProductLogistics = (int) $counts['logistics_ready_products'] > 0;
        $hasSnapshots = (int) $counts['snapshots'] > 0;

        return [
            $this->step(
                'legal_entity',
                1,
                'Firma',
                $hasCompany ? 'completed' : 'action',
                (int) $counts['legal_entities'].' aktif firma',
                $hasCompany
                    ? 'Hakediş ve raporlar firma altında toplanmaya hazır.'
                    : 'Hakediş, vergi ve mağaza kapsamı için önce firma kaydı açın.',
                'Firma tanımla',
                route('mp.integrations')
            ),
            $this->step(
                'store_connection',
                2,
                'Mağaza',
                $hasConnectedStore ? 'completed' : ($hasCompany ? 'action' : 'waiting'),
                (int) $counts['connected_stores'].'/'.max(1, (int) $counts['active_stores']).' bağlı',
                $hasConnectedStore
                    ? 'En az bir aktif mağaza bağlantısı veri almaya hazır.'
                    : 'Pazaryeri kimlik bilgilerini tamamlayıp bağlantıyı doğrulayın.',
                'Mağaza bağla',
                route('mp.integrations')
            ),
            $this->step(
                'product_sync',
                3,
                'Ürün ve eşleşme',
                $hasProducts && $matchingIssueCount === 0 ? 'completed' : ($hasConnectedStore ? 'action' : 'waiting'),
                ((int) $counts['products'] + (int) $counts['listings']).' kayıt',
                $this->productStepDescription($hasProducts, $matchingIssueCount, $counts),
                $matchingIssueCount > 0 ? 'Eşleşmeleri tamamla' : 'Ürünleri hazırla',
                $matchingIssueCount > 0 ? route('mp.matching', ['statusFilter' => 'pending']) : route('mp.products')
            ),
            $this->step(
                'costs',
                4,
                'Maliyet',
                $this->costStepStatus($counts, $hasProducts),
                $this->costStepMetric($counts),
                $this->costStepDescription($counts, $hasProducts),
                'Maliyetleri yükle',
                route('mp.products')
            ),
            $this->step(
                'orders',
                5,
                'Sipariş',
                $hasOrders ? 'completed' : ($hasConnectedStore ? 'action' : 'waiting'),
                (int) $counts['orders'].' sipariş',
                $hasOrders
                    ? 'Sipariş akışı kâr hesabına veri sağlıyor.'
                    : 'Sipariş senkronu veya Excel içe aktarımı ile satış satırlarını alın.',
                'Siparişleri al',
                route('mp.orders')
            ),
            $this->step(
                'finance',
                6,
                'Finans',
                $hasFinance ? 'completed' : ($hasOrders ? 'action' : 'waiting'),
                (int) $counts['financial_events'].' finans olayı',
                $hasFinance
                    ? 'Hakediş ve kesinti olayları kârı kesinleştirmeye başladı.'
                    : 'Hakediş/settlement verisi olmadan net alacak ve kesin kâr eksik kalır.',
                'Finansı tamamla',
                route('mp.finance', ['financialStateFilter' => 'waiting'])
            ),
            $this->step(
                'cargo',
                7,
                'Kargo',
                ($hasCargoProof || $hasProductLogistics) ? 'completed' : ($hasOrders || $hasProducts ? 'action' : 'waiting'),
                $this->cargoStepMetric($counts),
                ($hasCargoProof || $hasProductLogistics)
                    ? 'Kargo maliyeti veya sevkiyat raporu kâr hesabına bağlanabilir.'
                    : 'Kargo/desi veya kargo raporu eksikse sipariş başı net kâr yanıltıcı olabilir.',
                'Kargoyu kontrol et',
                route('cargo-reports', ['activeTab' => 'shipments'])
            ),
            $this->step(
                'profit_center',
                8,
                'İlk Kâr Kokpiti',
                ($hasSnapshots && $hasFinance) ? 'completed' : ($hasOrders ? 'action' : 'waiting'),
                (int) $counts['snapshots'].' kâr kaydı',
                ($hasSnapshots && $hasFinance)
                    ? 'Kokpit gerçek sipariş, maliyet ve finans verisiyle okunabilir durumda.'
                    : 'Kâr kayıtları oluştuğunda kokpit net alacak, marj ve riskleri gösterecek.',
                'Kâr Kokpitini aç',
                route('mp.profit-center')
            ),
        ];
    }

    protected function step(
        string $key,
        int $number,
        string $title,
        string $status,
        string $metric,
        string $description,
        string $actionLabel,
        string $actionUrl,
    ): array {
        return [
            'key' => $key,
            'number' => $number,
            'title' => $title,
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'tone' => $this->tone($status),
            'metric' => $metric,
            'description' => $description,
            'action_label' => $actionLabel,
            'action_url' => $actionUrl,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @return array<string, mixed>
     */
    protected function primaryAction(array $steps): array
    {
        $step = collect($steps)->first(fn (array $item) => $item['status'] !== 'completed');

        if (! $step) {
            return [
                'key' => 'profit_center',
                'label' => 'Kâr Kokpitini aç',
                'title' => 'Hazırlık tamam',
                'url' => route('mp.profit-center'),
                'tone' => 'success',
            ];
        }

        return [
            'key' => $step['key'],
            'label' => $step['action_label'],
            'title' => $step['title'],
            'url' => $step['action_url'],
            'tone' => $step['tone'],
        ];
    }

    protected function costStepStatus(array $counts, bool $hasProducts): string
    {
        $readiness = $counts['cost_readiness'];
        $gapLines = (int) ($readiness['missing_cost_lines'] ?? 0) + (int) ($readiness['unmatched_lines'] ?? 0);

        if ((int) ($readiness['total_lines'] ?? 0) > 0) {
            return $gapLines === 0 ? 'completed' : 'action';
        }

        if (! $hasProducts) {
            return 'waiting';
        }

        return (int) $counts['missing_cost_products'] === 0 && (int) $counts['cost_ready_products'] > 0
            ? 'completed'
            : 'action';
    }

    protected function costStepMetric(array $counts): string
    {
        $readiness = $counts['cost_readiness'];

        if ((int) ($readiness['total_lines'] ?? 0) > 0) {
            return (float) ($readiness['ready_percent'] ?? 0).'% satır hazır';
        }

        return (int) $counts['cost_ready_products'].'/'.max(1, (int) $counts['products']).' ürün hazır';
    }

    protected function costStepDescription(array $counts, bool $hasProducts): string
    {
        $readiness = $counts['cost_readiness'];
        $gapLines = (int) ($readiness['missing_cost_lines'] ?? 0) + (int) ($readiness['unmatched_lines'] ?? 0);

        if ((int) ($readiness['total_lines'] ?? 0) > 0 && $gapLines > 0) {
            return $gapLines.' sipariş satırı eşleşme veya maliyet eksiği nedeniyle kesin kâra dönemiyor.';
        }

        if (! $hasProducts) {
            return 'Maliyet kontrolü için önce ürün veya listeleme verisi gerekiyor.';
        }

        if ((int) $counts['missing_cost_products'] > 0) {
            return (int) $counts['missing_cost_products'].' üründe alış/üretim veya ambalaj maliyeti eksik.';
        }

        return 'Ürün maliyetleri ilk kâr hesabı için hazır görünüyor.';
    }

    protected function productStepDescription(bool $hasProducts, int $matchingIssueCount, array $counts): string
    {
        if (! $hasProducts) {
            return 'Ürün senkronu veya Excel içe aktarımı ile ürün havuzunu oluşturun.';
        }

        if ($matchingIssueCount > 0) {
            return $matchingIssueCount.' ürün/sipariş satırı eşleşme bekliyor; maliyetler bu yüzden kâra akmıyor.';
        }

        return 'Ürün ve listeleme verisi kâr akışına bağlanmış görünüyor.';
    }

    protected function cargoStepMetric(array $counts): string
    {
        if ((int) $counts['shipments'] > 0) {
            return (int) $counts['shipments'].' sevkiyat';
        }

        if ((int) $counts['cargo_reports'] > 0) {
            return (int) $counts['cargo_reports'].' kargo raporu';
        }

        return (int) $counts['logistics_ready_products'].'/'.max(1, (int) $counts['products']).' ürün lojistik hazır';
    }

    /**
     * @return array<string, mixed>
     */
    protected function metrics(array $counts): array
    {
        $readiness = $counts['cost_readiness'];

        return [
            'stores' => (int) $counts['connected_stores'],
            'products' => (int) $counts['products'],
            'orders' => (int) $counts['orders'],
            'finance_events' => (int) $counts['financial_events'],
            'missing_cost_lines' => (int) ($readiness['missing_cost_lines'] ?? 0),
            'unmatched_lines' => (int) ($readiness['unmatched_lines'] ?? 0),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function demoActions(): array
    {
        $actions = [
            [
                'label' => 'Mağaza/import akışı',
                'description' => 'Firma, mağaza ve senkron ayarlarını aynı ekrandan tamamlayın.',
                'url' => route('mp.integrations'),
            ],
            [
                'label' => 'Ürün Excel hazırlığı',
                'description' => 'Maliyet ve barkod/stok kodu alanlarını ürün merkezinde tamamlayın.',
                'url' => route('mp.products'),
            ],
            [
                'label' => 'Sipariş-finans kontrolü',
                'description' => 'Sipariş, hakediş ve snapshot durumunu ledger üzerinde izleyin.',
                'url' => route('mp.finance'),
            ],
        ];

        if (config('marketplace.features.public_trendyol_profit_tool_enabled', false)) {
            $actions[] = [
                'label' => 'Public kâr hesaplayıcı',
                'description' => 'Tek ürün senaryosunu hızlıca hesaplayıp ZOLM akışına taşıyın.',
                'url' => route('tools.trendyol-profit-calculator'),
            ];
        }

        return $actions;
    }

    protected function summaryStatus(int $completedSteps, int $totalSteps): string
    {
        if ($completedSteps >= $totalSteps) {
            return 'completed';
        }

        return $completedSteps === 0 ? 'not_started' : 'in_progress';
    }

    protected function headline(int $completedSteps, int $totalSteps, array $primaryAction): string
    {
        if ($completedSteps >= $totalSteps) {
            return 'Kâr Kokpiti için veri akışı hazır';
        }

        return 'Sıradaki adım: '.(string) ($primaryAction['title'] ?? 'Veri hazırlığı');
    }

    protected function summaryText(int $completedSteps, int $totalSteps, array $primaryAction): string
    {
        if ($completedSteps >= $totalSteps) {
            return 'Firma, mağaza, ürün, maliyet, sipariş, finans ve kargo verileri ilk kokpit okuması için tamamlandı.';
        }

        return $completedSteps.'/'.$totalSteps.' adım tamam. Kâr görünürlüğünü artırmak için önce '
            .mb_strtolower((string) ($primaryAction['title'] ?? 'veri hazırlığı'), 'UTF-8')
            .' adımını kapatın.';
    }

    protected function tone(string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'action' => 'warning',
            default => 'default',
        };
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            'completed' => 'Tamam',
            'action' => 'Aksiyon',
            default => 'Bekliyor',
        };
    }

    protected function shipmentCount(int $userId): int
    {
        if (! Schema::hasTable('shipments')) {
            return 0;
        }

        return Shipment::query()->where('user_id', $userId)->count();
    }

    protected function cargoReportCount(int $userId): int
    {
        if (! Schema::hasTable('cargo_reports')) {
            return 0;
        }

        return CargoReport::query()->where('user_id', $userId)->where('status', 'completed')->count();
    }

    protected function cargoAccountCount(int $userId): int
    {
        if (! Schema::hasTable('cargo_carrier_accounts')) {
            return 0;
        }

        return CargoCarrierAccount::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->whereIn('status', ['configured', 'verified', 'active'])
            ->count();
    }
}
