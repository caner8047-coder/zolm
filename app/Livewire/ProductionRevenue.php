<?php

namespace App\Livewire;

use App\Models\ProductionRevenueEntry;
use App\Models\ProductionRevenueImport;
use App\Services\ProductionRevenueImportService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

class ProductionRevenue extends Component
{
    use WithFileUploads;

    public $file;

    public bool $isImporting = false;
    public string $importMessage = '';
    public string $importMessageType = 'info';
    public string $selectedMonth = '';
    public ?string $focusedDate = null;
    public array $lastImportSummary = [];
    protected ?bool $revenueTablesExist = null;

    public function mount(): void
    {
        if (!$this->hasRevenueTables()) {
            $this->selectedMonth = now()->format('Y-m');
            $this->focusedDate = now()->toDateString();
            $this->importMessage = 'Üretim Ciro tabloları henüz oluşmamış. Önce migration çalıştırılmalı.';
            $this->importMessageType = 'error';
            return;
        }

        $latestEntry = ProductionRevenueEntry::query()
            ->orderByDesc('work_date')
            ->first();

        $this->selectedMonth = $latestEntry?->work_date?->format('Y-m') ?? now()->format('Y-m');
        $this->syncFocusedDate();
    }

    public function updatedSelectedMonth(): void
    {
        if (!$this->isValidMonthKey($this->selectedMonth)) {
            $this->selectedMonth = now()->format('Y-m');
        }

        $this->syncFocusedDate();
    }

    public function selectMonth(string $month): void
    {
        if (!$this->isValidMonthKey($month)) {
            return;
        }

        $this->selectedMonth = $month;
        $this->syncFocusedDate();
    }

    public function previousMonth(): void
    {
        $this->selectedMonth = $this->currentMonth->copy()->subMonth()->format('Y-m');
        $this->syncFocusedDate();
    }

    public function nextMonth(): void
    {
        $this->selectedMonth = $this->currentMonth->copy()->addMonth()->format('Y-m');
        $this->syncFocusedDate();
    }

    public function focusDate(string $date): void
    {
        if (!$this->isValidDateKey($date)) {
            return;
        }

        $this->focusedDate = $date;
    }

    public function importWorkbook(ProductionRevenueImportService $service): void
    {
        if (!$this->hasRevenueTables()) {
            $this->importMessage = 'İçe aktarma başlatılamadı. Önce production revenue migration dosyalarını veritabanına uygulayın.';
            $this->importMessageType = 'error';
            return;
        }

        $this->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:20480',
        ]);

        $this->isImporting = true;
        $this->importMessage = '';

        try {
            $summary = $service->importWorkbook($this->file, auth()->user());

            $this->lastImportSummary = [
                'created' => $summary['created'],
                'updated' => $summary['updated'],
                'unchanged' => $summary['unchanged'],
                'skipped' => $summary['skipped'],
                'months' => $summary['months'],
                'filename' => $summary['import']->filename,
                'imported_at' => $summary['import']->imported_at?->toDateTimeString(),
            ];

            $this->selectedMonth = $summary['latest_month'] ?? now()->format('Y-m');
            $this->syncFocusedDate();
            $this->importMessage = 'Excel başarıyla işlendi. Günlük üretim cirosu kayıtları güncellendi.';
            $this->importMessageType = 'success';
        } catch (Throwable $exception) {
            report($exception);

            $this->importMessage = $exception->getMessage() ?: 'Excel yüklenirken beklenmeyen bir hata oluştu.';
            $this->importMessageType = 'error';
        } finally {
            $this->isImporting = false;
            $this->reset('file');
        }
    }

    public function getCurrentMonthProperty(): Carbon
    {
        if (!$this->isValidMonthKey($this->selectedMonth)) {
            return now()->startOfMonth();
        }

        return Carbon::createFromFormat('Y-m', $this->selectedMonth)->startOfMonth();
    }

    public function getHasEntriesProperty(): bool
    {
        if (!$this->hasRevenueTables()) {
            return false;
        }

        return ProductionRevenueEntry::query()->exists();
    }

    public function getAvailableMonthsProperty(): Collection
    {
        if (!$this->hasRevenueTables()) {
            return collect();
        }

        return ProductionRevenueEntry::query()
            ->orderByDesc('work_date')
            ->get(['work_date', 'revenue', 'status'])
            ->groupBy(fn (ProductionRevenueEntry $entry) => $entry->work_date->format('Y-m'))
            ->map(function (Collection $entries, string $monthKey) {
                $anchor = Carbon::createFromFormat('Y-m', $monthKey)->startOfMonth();

                return [
                    'key' => $monthKey,
                    'label' => $this->formatMonthLabel($anchor),
                    'short_label' => $anchor->locale('tr')->translatedFormat('M y'),
                    'total' => $entries->sum(fn (ProductionRevenueEntry $entry) => (float) $entry->revenue),
                    'days' => $entries->count(),
                    'active_days' => $entries->filter(fn (ProductionRevenueEntry $entry) => (float) $entry->revenue > 0)->count(),
                    'alerts' => $entries->filter(fn (ProductionRevenueEntry $entry) => $entry->status !== ProductionRevenueEntry::STATUS_RECORDED)->count(),
                ];
            })
            ->sortByDesc('key')
            ->values();
    }

    public function getMonthEntriesProperty(): Collection
    {
        if (!$this->hasRevenueTables()) {
            return collect();
        }

        $month = $this->currentMonth;

        return ProductionRevenueEntry::query()
            ->with('import:id,filename,imported_at')
            ->whereBetween('work_date', [
                $month->copy()->startOfMonth()->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ])
            ->orderBy('work_date')
            ->get();
    }

    public function getMonthSummaryProperty(): array
    {
        $entries = $this->monthEntries;
        $total = $entries->sum(fn (ProductionRevenueEntry $entry) => (float) $entry->revenue);
        $activeDays = $entries->filter(fn (ProductionRevenueEntry $entry) => (float) $entry->revenue > 0)->count();
        $bestDay = $entries->sortByDesc(fn (ProductionRevenueEntry $entry) => (float) $entry->revenue)->first();
        $latestTouch = $entries->sortByDesc('updated_at')->first();

        return [
            'total' => $total,
            'entry_days' => $entries->count(),
            'active_days' => $activeDays,
            'calendar_days' => $this->currentMonth->daysInMonth,
            'average' => $activeDays > 0 ? $total / $activeDays : 0,
            'best_day' => $bestDay,
            'holiday_days' => $entries->where('status', ProductionRevenueEntry::STATUS_HOLIDAY)->count(),
            'note_days' => $entries->where('status', ProductionRevenueEntry::STATUS_NOTE)->count(),
            'no_output_days' => $entries->where('status', ProductionRevenueEntry::STATUS_NO_OUTPUT)->count(),
            'coverage' => $this->currentMonth->daysInMonth > 0
                ? (int) round(($entries->count() / $this->currentMonth->daysInMonth) * 100)
                : 0,
            'last_update' => $latestTouch?->updated_at,
        ];
    }

    public function getCalendarWeeksProperty(): Collection
    {
        if (!$this->hasRevenueTables()) {
            return $this->buildCalendarSkeleton();
        }

        $month = $this->currentMonth;
        $gridStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $visibleEntries = ProductionRevenueEntry::query()
            ->with('import:id,filename,imported_at')
            ->whereBetween('work_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->get()
            ->keyBy(fn (ProductionRevenueEntry $entry) => $entry->work_date->toDateString());

        $maxRevenue = max(
            1,
            (float) $this->monthEntries->max(fn (ProductionRevenueEntry $entry) => (float) $entry->revenue)
        );

        $days = collect();

        for ($cursor = $gridStart->copy(); $cursor->lte($gridEnd); $cursor->addDay()) {
            $dateKey = $cursor->toDateString();
            $entry = $visibleEntries->get($dateKey);

            $days->push([
                'date' => $cursor->copy(),
                'key' => $dateKey,
                'entry' => $entry,
                'is_current_month' => $cursor->isSameMonth($month),
                'is_today' => $cursor->isToday(),
                'is_weekend' => $cursor->isWeekend(),
                'is_focused' => $this->focusedDate === $dateKey,
                'intensity' => $this->resolveIntensity($entry, $maxRevenue),
            ]);
        }

        return $days->chunk(7);
    }

    public function getFocusedEntryProperty(): ?ProductionRevenueEntry
    {
        if (!$this->focusedDate) {
            return null;
        }

        return $this->monthEntries
            ->first(fn (ProductionRevenueEntry $entry) => $entry->work_date->toDateString() === $this->focusedDate);
    }

    public function getFocusedInsightsProperty(): ?array
    {
        $entry = $this->focusedEntry;

        if (!$entry) {
            return null;
        }

        $monthEntries = $this->monthEntries;
        $previousProductive = $monthEntries
            ->filter(fn (ProductionRevenueEntry $item) => $item->work_date->lt($entry->work_date) && (float) $item->revenue > 0)
            ->last();

        $weekStart = $entry->work_date->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $entry->work_date->copy()->endOfWeek(Carbon::SUNDAY);
        $rankedDays = $monthEntries
            ->filter(fn (ProductionRevenueEntry $item) => (float) $item->revenue > 0)
            ->sortByDesc(fn (ProductionRevenueEntry $item) => (float) $item->revenue)
            ->values();
        $rankIndex = $rankedDays->search(fn (ProductionRevenueEntry $item) => $item->id === $entry->id);

        return [
            'rank' => $rankIndex === false ? null : $rankIndex + 1,
            'week_total' => $monthEntries
                ->filter(fn (ProductionRevenueEntry $item) => $item->work_date->gte($weekStart) && $item->work_date->lte($weekEnd))
                ->sum(fn (ProductionRevenueEntry $item) => (float) $item->revenue),
            'delta_previous' => $previousProductive
                ? (float) $entry->revenue - (float) $previousProductive->revenue
                : null,
            'previous_label' => $previousProductive?->work_date?->locale('tr')->translatedFormat('d M'),
            'delta_average' => (float) $entry->revenue - (float) $this->monthSummary['average'],
        ];
    }

    public function getTopDaysProperty(): Collection
    {
        return $this->monthEntries
            ->filter(fn (ProductionRevenueEntry $entry) => (float) $entry->revenue > 0)
            ->sortByDesc(fn (ProductionRevenueEntry $entry) => (float) $entry->revenue)
            ->take(5)
            ->values();
    }

    public function getRecentImportsProperty(): Collection
    {
        if (!$this->hasRevenueTables()) {
            return collect();
        }

        return ProductionRevenueImport::query()
            ->latest('imported_at')
            ->take(5)
            ->get();
    }

    public function getRevenueTablesReadyProperty(): bool
    {
        return $this->hasRevenueTables();
    }

    public function getYearSummaryProperty(): Collection
    {
        $selectedYear = $this->currentMonth->year;
        $monthMap = $this->availableMonths->keyBy('key');

        return collect(range(1, 12))
            ->map(function (int $monthNumber) use ($selectedYear, $monthMap) {
                $key = sprintf('%04d-%02d', $selectedYear, $monthNumber);
                $anchor = Carbon::create($selectedYear, $monthNumber, 1)->startOfMonth();
                $data = $monthMap->get($key, []);

                return [
                    'key' => $key,
                    'label' => $anchor->locale('tr')->translatedFormat('M'),
                    'full_label' => $this->formatMonthLabel($anchor),
                    'total' => (float) ($data['total'] ?? 0),
                    'is_selected' => $key === $this->selectedMonth,
                ];
            });
    }

    public function getYearSparklinePointsProperty(): string
    {
        $summary = $this->yearSummary;
        $count = max(1, $summary->count() - 1);
        $max = max(1, (float) $summary->max('total'));

        return $summary
            ->values()
            ->map(function (array $item, int $index) use ($count, $max) {
                $x = $count === 0 ? 0 : round(($index / $count) * 100, 2);
                $y = round(44 - (($item['total'] / $max) * 38), 2);

                return $x . ',' . $y;
            })
            ->implode(' ');
    }

    public function formatCurrency(float|int|string|null $amount, int $decimals = 0): string
    {
        return '₺' . number_format((float) $amount, $decimals, ',', '.');
    }

    public function formatMonthLabel(Carbon $month): string
    {
        return $month->locale('tr')->translatedFormat('F Y');
    }

    public function statusBadgeClasses(?string $status): string
    {
        return match ($status) {
            ProductionRevenueEntry::STATUS_HOLIDAY => 'bg-amber-100 text-amber-700 border border-amber-200',
            ProductionRevenueEntry::STATUS_NO_OUTPUT => 'bg-rose-100 text-rose-700 border border-rose-200',
            ProductionRevenueEntry::STATUS_NOTE => 'bg-sky-100 text-sky-700 border border-sky-200',
            default => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
        };
    }

    public function statusLabel(?string $status): string
    {
        return match ($status) {
            ProductionRevenueEntry::STATUS_HOLIDAY => 'Tatil',
            ProductionRevenueEntry::STATUS_NO_OUTPUT => 'Çıkış yok',
            ProductionRevenueEntry::STATUS_NOTE => 'Not var',
            default => 'Kayıtlı',
        };
    }

    public function dayToneClasses(array $day): string
    {
        if (!$day['is_current_month']) {
            return 'border-transparent bg-slate-100/70 text-slate-400';
        }

        if ($day['entry']?->status === ProductionRevenueEntry::STATUS_HOLIDAY) {
            return 'border-amber-200 bg-amber-50 text-amber-900';
        }

        if ($day['entry']?->status === ProductionRevenueEntry::STATUS_NO_OUTPUT) {
            return 'border-rose-200 bg-rose-50 text-rose-900';
        }

        if ($day['intensity'] >= 4) {
            return 'border-emerald-400 bg-emerald-500 text-white shadow-lg shadow-emerald-500/20';
        }

        if ($day['intensity'] === 3) {
            return 'border-emerald-300 bg-emerald-100 text-emerald-900';
        }

        if ($day['intensity'] === 2) {
            return 'border-sky-200 bg-sky-50 text-slate-900';
        }

        if ($day['intensity'] === 1) {
            return 'border-slate-200 bg-white text-slate-900';
        }

        return 'border-slate-200 bg-white/70 text-slate-700';
    }

    public function render()
    {
        return view('livewire.production-revenue')
            ->layout('layouts.app', ['title' => 'Üretim Ciro']);
    }

    private function resolveIntensity(?ProductionRevenueEntry $entry, float $maxRevenue): int
    {
        if (!$entry) {
            return 0;
        }

        if ($entry->status === ProductionRevenueEntry::STATUS_HOLIDAY || $entry->status === ProductionRevenueEntry::STATUS_NO_OUTPUT) {
            return 1;
        }

        $revenue = (float) $entry->revenue;

        if ($revenue <= 0 || $maxRevenue <= 0) {
            return 1;
        }

        $ratio = $revenue / $maxRevenue;

        return match (true) {
            $ratio >= 0.85 => 4,
            $ratio >= 0.55 => 3,
            $ratio >= 0.25 => 2,
            default => 1,
        };
    }

    private function syncFocusedDate(): void
    {
        if ($this->currentMonth->isSameMonth(now())) {
            $this->focusedDate = now()->toDateString();
            return;
        }

        $latestEntry = $this->monthEntries->last();
        $this->focusedDate = $latestEntry?->work_date?->toDateString()
            ?? $this->currentMonth->copy()->startOfMonth()->toDateString();
    }

    private function hasRevenueTables(): bool
    {
        if ($this->revenueTablesExist !== null) {
            return $this->revenueTablesExist;
        }

        $this->revenueTablesExist = Schema::hasTable('production_revenue_entries')
            && Schema::hasTable('production_revenue_imports');

        return $this->revenueTablesExist;
    }

    private function buildCalendarSkeleton(): Collection
    {
        $month = $this->currentMonth;
        $gridStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $days = collect();

        for ($cursor = $gridStart->copy(); $cursor->lte($gridEnd); $cursor->addDay()) {
            $days->push([
                'date' => $cursor->copy(),
                'key' => $cursor->toDateString(),
                'entry' => null,
                'is_current_month' => $cursor->isSameMonth($month),
                'is_today' => $cursor->isToday(),
                'is_weekend' => $cursor->isWeekend(),
                'is_focused' => $this->focusedDate === $cursor->toDateString(),
                'intensity' => 0,
            ]);
        }

        return $days->chunk(7);
    }

    private function isValidMonthKey(string $value): bool
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1;
    }

    private function isValidDateKey(string $value): bool
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $value) === 1;
    }
}
