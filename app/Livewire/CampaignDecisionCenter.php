<?php

namespace App\Livewire;

use App\Services\CampaignDecisionCenterQueryService;
use App\Services\ExcelService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CampaignDecisionCenter extends Component
{
    public static array $allColumnDefs = [
        'product' => 'Ürün',
        'campaign' => 'Kampanya',
        'decision' => 'Karar',
        'current' => 'Mevcut',
        'suggested' => 'Önerilen',
        'impact' => 'Etki',
        'cost' => 'Maliyet',
        'action' => 'Aksiyon',
    ];

    public static array $sortableColumns = [
        'product' => 'product_name',
        'campaign' => 'campaign_label',
        'decision' => 'decision_score',
        'current' => 'current_profit',
        'suggested' => 'suggested_profit',
        'impact' => 'extra_profit',
        'cost' => 'total_cost',
    ];

    public string $search = '';
    public string $campaignTypeFilter = '';
    public string $decisionFilter = '';
    public string $sortField = 'decision_score';
    public string $sortDirection = 'desc';
    public array $visibleColumns = ['product', 'campaign', 'decision', 'current', 'suggested', 'impact', 'cost', 'action'];

    protected $queryString = [
        'search' => ['except' => ''],
        'campaignTypeFilter' => ['except' => '', 'as' => 'type'],
        'decisionFilter' => ['except' => '', 'as' => 'decision'],
        'sortField' => ['except' => 'decision_score'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function mount(): void
    {
        if (! in_array($this->sortField, self::$sortableColumns, true)) {
            $this->sortField = 'decision_score';
        }

        if ($this->campaignTypeFilter !== '' && ! array_key_exists($this->campaignTypeFilter, $this->typeDefinitions())) {
            $this->campaignTypeFilter = '';
        }

        if ($this->decisionFilter !== '' && ! array_key_exists($this->decisionFilter, $this->decisionDefinitions())) {
            $this->decisionFilter = '';
        }
    }

    #[Computed]
    public function dashboard(): array
    {
        $dashboard = $this->decisionService()->dashboard($this->userId(), $this->filters());
        $queue = collect($dashboard['queue']);
        $field = $this->sortField;

        $dashboard['queue'] = ($this->sortDirection === 'asc'
            ? $queue->sortBy(fn (array $row) => $row[$field] ?? null, SORT_NATURAL | SORT_FLAG_CASE)
            : $queue->sortByDesc(fn (array $row) => $row[$field] ?? null, SORT_NATURAL | SORT_FLAG_CASE))
            ->values()
            ->all();

        return $dashboard;
    }

    public function updated($property): void
    {
        unset($this->dashboard);
    }

    public function focusCampaignType(string $type): void
    {
        if (! array_key_exists($type, $this->typeDefinitions())) {
            return;
        }

        $this->campaignTypeFilter = $this->campaignTypeFilter === $type ? '' : $type;
        unset($this->dashboard);
    }

    public function focusDecision(string $decision): void
    {
        if (! array_key_exists($decision, $this->decisionDefinitions())) {
            return;
        }

        $this->decisionFilter = $this->decisionFilter === $decision ? '' : $decision;
        unset($this->dashboard);
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->campaignTypeFilter = '';
        $this->decisionFilter = '';
        $this->sortField = 'decision_score';
        $this->sortDirection = 'desc';
        unset($this->dashboard);
    }

    public function sortTable(string $column): void
    {
        if (! isset(self::$sortableColumns[$column])) {
            return;
        }

        $field = self::$sortableColumns[$column];
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'desc';
        }

        unset($this->dashboard);
    }

    public function toggleColumn(string $column): void
    {
        if (! array_key_exists($column, self::$allColumnDefs)) {
            return;
        }

        if (in_array($column, $this->visibleColumns, true)) {
            if (count($this->visibleColumns) > 2) {
                $this->visibleColumns = array_values(array_diff($this->visibleColumns, [$column]));
            }

            return;
        }

        $this->visibleColumns[] = $column;
        $this->visibleColumns = array_values(array_unique($this->visibleColumns));
    }

    public function exportDecisionReport()
    {
        $dashboard = $this->dashboard;
        $summary = $dashboard['summary'];
        $fileName = 'kampanya-karar-merkezi-' . now()->format('Ymd-His') . '.xlsx';
        $path = storage_path('app/temp/' . $fileName);

        app(ExcelService::class)->exportToXlsx([
            [
                'name' => 'Karar Ozeti',
                'data' => [
                    ['Metrik' => 'Son rapor sayısı', 'Değer' => $summary['report_count']],
                    ['Metrik' => 'Kampanya türü', 'Değer' => $summary['campaign_type_count']],
                    ['Metrik' => 'Analiz edilen ürün', 'Değer' => $summary['product_count']],
                    ['Metrik' => 'Mevcut kâr tabanı', 'Değer' => $summary['current_profit']],
                    ['Metrik' => 'Onaylanabilir ek kâr', 'Değer' => $summary['potential_profit']],
                    ['Metrik' => 'Karar sonrası kâr', 'Değer' => $summary['decision_profit']],
                    ['Metrik' => 'Risk altındaki kâr', 'Değer' => $summary['risk_exposure']],
                    ['Metrik' => 'Onaylanabilir ürün', 'Değer' => $summary['approve_count']],
                    ['Metrik' => 'İncelenecek ürün', 'Değer' => $summary['risk_count']],
                    ['Metrik' => 'Mevcut durumu koru', 'Değer' => $summary['keep_count']],
                    ['Metrik' => 'Maliyet kapsamı', 'Değer' => $summary['cost_coverage']],
                    ['Metrik' => 'Karar skoru', 'Değer' => $summary['decision_score']],
                ],
            ],
            [
                'name' => 'Modul Karsilastirma',
                'data' => collect($dashboard['modules'])->map(fn (array $module) => [
                    'Kampanya' => $module['label'],
                    'Son Rapor' => $module['report_name'] ?? 'Rapor yok',
                    'Rapor Tarihi' => $module['report_date'] ?? '',
                    'Ürün' => $module['product_count'],
                    'Onaylanabilir' => $module['approve_count'],
                    'İncelenmeli' => $module['risk_count'],
                    'Koru' => $module['keep_count'],
                    'Seçili' => $module['selected_count'],
                    'Ek Kâr' => $module['potential_profit'],
                    'Risk Tutarı' => $module['risk_exposure'],
                    'Maliyet Kapsamı' => $module['cost_coverage'],
                ])->all(),
            ],
            [
                'name' => 'Karar Kuyrugu',
                'data' => $this->decisionService()->exportRows($this->userId(), $this->filters()),
            ],
            [
                'name' => 'Son Raporlar',
                'data' => collect($dashboard['recent_reports'])->map(fn (array $report) => [
                    'Rapor ID' => $report['id'],
                    'Rapor' => $report['name'],
                    'Kampanya' => $report['campaign_label'],
                    'Tarih' => $report['created_at'],
                    'Ürün' => $report['total_products'],
                    'Kaynak Fırsat' => $report['opportunity_count'],
                    'Kaynak Ek Kâr' => $report['total_extra_profit'],
                    'Durum' => $report['status'],
                ])->all(),
            ],
        ], $path);

        return response()->download($path, $fileName)->deleteFileAfterSend(true);
    }

    public function moduleUrl(array $module): string
    {
        $parameters = [];
        if (! empty($module['report_id'])) {
            $parameters['report'] = (int) $module['report_id'];
        }

        return route((string) $module['route'], $parameters);
    }

    public function rowUrl(array $row): string
    {
        return route((string) $row['route'], ['report' => (int) $row['report_id']]);
    }

    public function reportUrl(array $report): string
    {
        return route((string) $report['route'], ['report' => (int) $report['id']]);
    }

    public function render()
    {
        return view('livewire.campaign-decision-center', [
            'dashboard' => $this->dashboard,
            'typeDefinitions' => $this->typeDefinitions(),
            'decisionDefinitions' => $this->decisionDefinitions(),
            'columnDefs' => self::$allColumnDefs,
            'sortableColumns' => self::$sortableColumns,
        ])->layout('layouts.app', ['title' => 'Kampanya Karar Merkezi']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(): array
    {
        return [
            'search' => $this->search,
            'campaign_type' => $this->campaignTypeFilter,
            'decision' => $this->decisionFilter,
        ];
    }

    protected function typeDefinitions(): array
    {
        return $this->decisionService()->typeDefinitions();
    }

    protected function decisionDefinitions(): array
    {
        return $this->decisionService()->decisionDefinitions();
    }

    protected function decisionService(): CampaignDecisionCenterQueryService
    {
        return app(CampaignDecisionCenterQueryService::class);
    }

    protected function userId(): int
    {
        return Auth::id() ?? 1;
    }
}
