<?php

namespace App\Livewire;

use App\Services\ExcelService;
use App\Services\Marketplace\MarketplaceRiskSignalService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class MarketplaceRiskCenter extends Component
{
    public static array $allColumnDefs = [
        'priority' => 'Öncelik',
        'signal' => 'Risk',
        'category' => 'Kategori',
        'impact' => 'Etki',
        'status' => 'Durum',
        'source' => 'Kaynak',
        'actions' => 'Aksiyon',
    ];

    public static array $sortableColumns = [
        'priority' => 'priority_score',
        'signal' => 'title',
        'impact' => 'impact',
        'status' => 'last_seen_at',
    ];

    public string $search = '';
    public string $categoryFilter = '';
    public string $severityFilter = '';
    public string $statusFilter = 'active';
    public string $sortField = 'priority_score';
    public string $sortDirection = 'desc';
    public string $focus = '';
    public array $visibleColumns = ['priority', 'signal', 'category', 'impact', 'status', 'source', 'actions'];

    protected $queryString = [
        'search' => ['except' => ''],
        'categoryFilter' => ['except' => '', 'as' => 'category'],
        'severityFilter' => ['except' => '', 'as' => 'severity'],
        'statusFilter' => ['except' => 'active', 'as' => 'status'],
        'sortField' => ['except' => 'priority_score'],
        'sortDirection' => ['except' => 'desc'],
        'focus' => ['except' => ''],
    ];

    public function mount(): void
    {
        if (! array_key_exists($this->categoryFilter, $this->categoryDefinitions()) && $this->categoryFilter !== '') {
            $this->categoryFilter = '';
        }

        if (! array_key_exists($this->severityFilter, $this->severityDefinitions()) && $this->severityFilter !== '') {
            $this->severityFilter = '';
        }

        if (! in_array($this->statusFilter, ['active', 'open', 'snoozed', 'resolved', 'all'], true)) {
            $this->statusFilter = 'active';
        }

        if (! in_array($this->sortField, self::$sortableColumns, true)) {
            $this->sortField = 'priority_score';
        }

        if ($this->focus !== '') {
            $this->statusFilter = 'all';
        }

        $this->visibleColumns = array_values(array_intersect(
            $this->visibleColumns,
            array_keys(self::$allColumnDefs)
        ));
    }

    #[Computed]
    public function dashboard(): array
    {
        $dashboard = $this->riskService()->dashboard($this->userId(), $this->filters());

        if ($this->focus !== '') {
            $dashboard['queue'] = collect($dashboard['queue'])
                ->sortByDesc(fn (array $signal) => $signal['fingerprint'] === $this->focus ? 1 : 0)
                ->values()
                ->all();
        }

        return $dashboard;
    }

    public function updated($property): void
    {
        unset($this->dashboard);
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->categoryFilter = '';
        $this->severityFilter = '';
        $this->statusFilter = 'active';
        $this->sortField = 'priority_score';
        $this->sortDirection = 'desc';
        $this->focus = '';
        unset($this->dashboard);
    }

    public function focusCategory(string $category): void
    {
        if (! array_key_exists($category, $this->categoryDefinitions())) {
            return;
        }

        $this->categoryFilter = $this->categoryFilter === $category ? '' : $category;
        $this->statusFilter = 'active';
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
        } else {
            $this->visibleColumns[] = $column;
            $this->visibleColumns = array_values(array_unique($this->visibleColumns));
        }
    }

    public function resolveRisk(string $fingerprint): void
    {
        $this->riskService()->resolve($this->userId(), $fingerprint);
        session()->flash('success', 'Risk çözüldü olarak işaretlendi.');
        unset($this->dashboard);
    }

    public function executeAction(string $fingerprint, string $actionKey): void
    {
        $this->riskService()->executeAction($this->userId(), $fingerprint, $actionKey);
        session()->flash('success', 'Otomatik aksiyon başarıyla uygulandı.');
        unset($this->dashboard);
    }

    public function snoozeRisk(string $fingerprint, int $days): void
    {
        $this->riskService()->snooze($this->userId(), $fingerprint, $days);
        session()->flash('success', "Risk {$days} gün ertelendi.");
        unset($this->dashboard);
    }

    public function reopenRisk(string $fingerprint): void
    {
        $this->riskService()->reopen($this->userId(), $fingerprint);
        session()->flash('success', 'Risk yeniden açık kuyruğa alındı.');
        unset($this->dashboard);
    }

    public function syncRisks(): void
    {
        $result = $this->riskService()->syncForUser($this->userId());
        session()->flash(
            'success',
            "{$result['signals']} risk sinyali yenilendi, {$result['notifications']} yeni bildirim üretildi."
        );
        unset($this->dashboard);
    }

    public function toggleNotificationPreference(string $token): void
    {
        $allowed = collect([
            'risk_critical',
            'risk_warning',
            ...collect(array_keys($this->categoryDefinitions()))
                ->map(fn (string $category) => 'risk_category:' . $category)
                ->all(),
        ]);

        if (! $allowed->contains($token)) {
            return;
        }

        $muted = collect($this->dashboard['notification_preferences']['muted_types'] ?? []);
        $this->riskService()->setNotificationToken(
            $this->userId(),
            $token,
            $muted->contains($token)
        );
        unset($this->dashboard);
    }

    public function exportRiskReport()
    {
        $dashboard = $this->riskService()->dashboard($this->userId(), $this->filters(), PHP_INT_MAX);
        $fileName = 'zolm-risk-merkezi-' . now()->format('Ymd-His') . '.xlsx';
        $path = storage_path('app/temp/' . $fileName);
        $summary = $dashboard['summary'];

        app(ExcelService::class)->exportToXlsx([
            [
                'name' => 'Risk Ozeti',
                'data' => [
                    ['Metrik' => 'Risk skoru', 'Değer' => $summary['risk_score']],
                    ['Metrik' => 'Skor durumu', 'Değer' => $summary['risk_score_label']],
                    ['Metrik' => 'Açık risk', 'Değer' => $summary['open_count']],
                    ['Metrik' => 'Kritik risk', 'Değer' => $summary['critical_count']],
                    ['Metrik' => 'Uyarı', 'Değer' => $summary['warning_count']],
                    ['Metrik' => 'Ertelenen', 'Değer' => $summary['snoozed_count']],
                    ['Metrik' => 'Çözülen', 'Değer' => $summary['resolved_count']],
                    ['Metrik' => 'Finansal etki', 'Değer' => $summary['impact_total']],
                    ['Metrik' => 'Etkilenen kayıt', 'Değer' => $summary['affected_total']],
                ],
            ],
            [
                'name' => 'Kategori Baskisi',
                'data' => collect($dashboard['category_breakdown'])->map(fn (array $row) => [
                    'Kategori' => $row['label'],
                    'Risk Sayısı' => $row['count'],
                    'Kritik' => $row['critical_count'],
                    'Finansal Etki' => $row['impact'],
                    'Etkilenen Kayıt' => $row['affected'],
                    'En Yüksek Skor' => $row['max_score'],
                ])->all(),
            ],
            [
                'name' => 'Risk Kuyrugu',
                'data' => $this->riskService()->exportRows($this->userId(), $this->filters()),
            ],
            [
                'name' => 'Bildirim Tercihleri',
                'data' => $this->notificationPreferenceExportRows($dashboard['notification_preferences']),
            ],
        ], $path);

        return response()->download($path, $fileName)->deleteFileAfterSend(true);
    }

    public function render()
    {
        return view('livewire.marketplace-risk-center', [
            'categoryDefinitions' => $this->categoryDefinitions(),
            'severityDefinitions' => $this->severityDefinitions(),
            'statusDefinitions' => $this->riskService()->statusDefinitions(),
            'columnDefs' => self::$allColumnDefs,
            'sortableColumns' => self::$sortableColumns,
        ])->layout('layouts.app', ['title' => 'Pazaryeri Günlük Görevler']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(): array
    {
        return [
            'search' => $this->search,
            'category' => $this->categoryFilter,
            'severity' => $this->severityFilter,
            'status' => $this->statusFilter,
            'sort_field' => $this->sortField,
            'sort_direction' => $this->sortDirection,
        ];
    }

    protected function categoryDefinitions(): array
    {
        return $this->riskService()->categoryDefinitions();
    }

    protected function severityDefinitions(): array
    {
        return $this->riskService()->severityDefinitions();
    }

    protected function riskService(): MarketplaceRiskSignalService
    {
        return app(MarketplaceRiskSignalService::class);
    }

    protected function userId(): int
    {
        return (int) (Auth::id() ?? 1);
    }

    /**
     * @param  array{sound_enabled: bool, muted_types: array<int, string>}  $preferences
     * @return array<int, array<string, string>>
     */
    protected function notificationPreferenceExportRows(array $preferences): array
    {
        $muted = collect($preferences['muted_types'] ?? []);
        $rows = [
            [
                'Tercih' => 'Kritik risk bildirimleri',
                'Durum' => $muted->contains('risk_critical') ? 'Kapalı' : 'Açık',
            ],
            [
                'Tercih' => 'Risk uyarıları',
                'Durum' => $muted->contains('risk_warning') ? 'Kapalı' : 'Açık',
            ],
        ];

        foreach ($this->categoryDefinitions() as $key => $definition) {
            $rows[] = [
                'Tercih' => $definition['label'],
                'Durum' => $muted->contains('risk_category:' . $key) ? 'Kapalı' : 'Açık',
            ];
        }

        return $rows;
    }
}
