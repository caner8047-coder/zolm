<?php

namespace App\Livewire\Cargo;

use App\Models\CargoCarrierAccount;
use App\Models\CargoReportLine;
use App\Services\Cargo\SuratReportArchiveService;
use App\Services\ExcelService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SuratReportArchive extends Component
{
    use WithPagination;

    public string $startDate = '';
    public string $endDate = '';
    public string $selectedDate = '';
    public string $summaryPreset = 'last_14';
    public string $summaryStartDate = '';
    public string $summaryEndDate = '';
    public string $search = '';
    public string $message = '';
    public string $messageTone = 'info';
    public bool $lastFetchCompleted = false;
    public array $lastTotals = [
        'row_count' => 0,
        'pieces' => 0,
        'desi' => 0.0,
        'amount' => 0.0,
        'measurement_amount' => 0.0,
        'total_amount' => 0.0,
    ];

    public function mount(): void
    {
        $this->startDate = now()->subDay()->toDateString();
        $this->endDate = now()->subDay()->toDateString();
        $this->summaryStartDate = now()->subDays(13)->toDateString();
        $this->summaryEndDate = now()->toDateString();
        $this->selectedDate = $this->latestReportDate() ?? '';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedDate(): void
    {
        $this->resetPage();
    }

    public function updatedSummaryPreset(): void
    {
        if ($this->summaryPreset !== 'custom') {
            $this->resetSummaryCustomRange();
        }
    }

    #[Computed]
    public function tableReady(): bool
    {
        return Schema::hasTable('cargo_report_lines') && Schema::hasTable('cargo_report_runs');
    }

    #[Computed]
    public function activeAccount(): ?CargoCarrierAccount
    {
        if (!Schema::hasTable('cargo_carrier_accounts')) {
            return null;
        }

        return CargoCarrierAccount::query()
            ->where('user_id', auth()->id())
            ->where('carrier_code', 'surat')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->latest('id')
            ->first();
    }

    #[Computed]
    public function summaries()
    {
        if (!$this->tableReady) {
            return collect();
        }

        [$start, $end] = $this->summaryDateRange();

        return CargoReportLine::query()
            ->where('user_id', auth()->id())
            ->where('carrier_code', 'surat')
            ->when($start && $end, fn (Builder $query) => $query->whereBetween('report_date', [$start->toDateString(), $end->toDateString()]))
            ->selectRaw('report_date, COUNT(*) as row_count, SUM(pieces) as pieces, SUM(desi) as desi, SUM(amount) as amount, SUM(measurement_amount) as measurement_amount, SUM(total_amount) as total_amount')
            ->groupBy('report_date')
            ->orderByDesc('report_date')
            ->limit($this->summaryPreset === 'custom' ? 62 : 31)
            ->get();
    }

    #[Computed]
    public function summaryTotals(): array
    {
        if (!$this->tableReady) {
            return [
                'row_count' => 0,
                'pieces' => 0,
                'desi' => 0.0,
                'total_amount' => 0.0,
            ];
        }

        [$start, $end] = $this->summaryDateRange();

        $totals = CargoReportLine::query()
            ->where('user_id', auth()->id())
            ->where('carrier_code', 'surat')
            ->when($start && $end, fn (Builder $query) => $query->whereBetween('report_date', [$start->toDateString(), $end->toDateString()]))
            ->selectRaw('COUNT(*) as row_count, SUM(pieces) as pieces, SUM(desi) as desi, SUM(total_amount) as total_amount')
            ->first();

        return [
            'row_count' => (int) ($totals->row_count ?? 0),
            'pieces' => (int) ($totals->pieces ?? 0),
            'desi' => (float) ($totals->desi ?? 0),
            'total_amount' => (float) ($totals->total_amount ?? 0),
        ];
    }

    #[Computed]
    public function summaryRangeLabel(): string
    {
        [$start, $end] = $this->summaryDateRange();

        if (!$start || !$end) {
            return 'Tüm kayıtlar';
        }

        return $start->format('d.m.Y') . ' - ' . $end->format('d.m.Y');
    }

    #[Computed]
    public function selectedSummary(): ?object
    {
        if (!$this->tableReady || !$this->selectedDate) {
            return null;
        }

        return CargoReportLine::query()
            ->where('user_id', auth()->id())
            ->where('carrier_code', 'surat')
            ->whereDate('report_date', Carbon::parse($this->selectedDate)->toDateString())
            ->selectRaw('COUNT(*) as row_count, SUM(pieces) as pieces, SUM(desi) as desi, SUM(amount) as amount, SUM(measurement_amount) as measurement_amount, SUM(total_amount) as total_amount')
            ->first();
    }

    #[Computed]
    public function selectedAmountSourceStats()
    {
        if (!$this->tableReady || !$this->selectedDate) {
            return collect();
        }

        return CargoReportLine::query()
            ->where('user_id', auth()->id())
            ->where('carrier_code', 'surat')
            ->whereDate('report_date', Carbon::parse($this->selectedDate)->toDateString())
            ->selectRaw('amount_source, COUNT(*) as row_count, SUM(total_amount) as total_amount')
            ->groupBy('amount_source')
            ->get()
            ->keyBy('amount_source');
    }

    #[Computed]
    public function lines(): LengthAwarePaginator
    {
        if (!$this->tableReady || !$this->selectedDate) {
            return new LengthAwarePaginator([], 0, 25);
        }

        return $this->selectedLinesQuery()
            ->orderBy('customer_name')
            ->orderBy('tracking_number')
            ->paginate(25);
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = Carbon::parse($date)->toDateString();
        $this->resetPage();
    }

    public function fetchDateRangeReport(): void
    {
        $this->validate([
            'startDate' => 'required|date',
            'endDate' => 'required|date|after_or_equal:startDate',
        ], [
            'startDate.required' => 'Başlangıç tarihi gerekli.',
            'endDate.after_or_equal' => 'Bitiş tarihi başlangıçtan önce olamaz.',
        ]);

        $start = Carbon::parse($this->startDate)->startOfDay();
        $end = Carbon::parse($this->endDate)->startOfDay();

        if ($start->diffInDays($end) > 31) {
            $this->showMessage('Sürat tarih aralığı raporu en fazla 31 gün için çalıştırılabilir.', 'warning');
            return;
        }

        $account = $this->activeAccount;
        if (!$account) {
            $this->showMessage('Rapor çekmek için önce aktif Sürat hesabı tanımlayın.', 'warning');
            return;
        }

        try {
            $result = app(SuratReportArchiveService::class)->fetchAndArchive($account, $this->startDate, $this->endDate);

            $this->lastFetchCompleted = true;
            $this->lastTotals = array_merge($this->lastTotals, $result['totals'] ?? []);
            $this->selectedDate = $this->latestReportDateInRange($this->startDate, $this->endDate)
                ?? Carbon::parse($this->endDate)->toDateString();
            $this->summaryPreset = 'custom';
            $this->summaryStartDate = Carbon::parse($this->startDate)->toDateString();
            $this->summaryEndDate = Carbon::parse($this->endDate)->toDateString();
            $this->resetPage();

            $warningText = filled($result['warnings'] ?? [])
                ? ' Not: ' . implode(' ', array_slice($result['warnings'], 0, 1))
                : '';

            $this->showMessage('Sürat raporu çekildi ve günlük arşive kaydedildi: ' . (int) ($this->lastTotals['row_count'] ?? 0) . ' gönderi.' . $warningText, filled($warningText) ? 'info' : 'success');
        } catch (\Throwable $exception) {
            Log::error('Sürat rapor arşivi: Rapor çekme hatası', [
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
                'error' => $exception->getMessage(),
            ]);

            $this->lastFetchCompleted = true;
            $this->showMessage($exception->getMessage(), 'warning');
        }
    }

    public function sendSelectedDateToCheck(): void
    {
        if (!$this->selectedDate) {
            $this->showMessage('Check modülüne göndermek için önce rapor günü seçin.', 'warning');
            return;
        }

        $this->dispatch('cargo-check-from-surat-report', reportDate: Carbon::parse($this->selectedDate)->toDateString());
    }

    public function exportSelectedDate()
    {
        if (!$this->selectedDate) {
            $this->showMessage('Dışarı aktarmak için önce rapor günü seçin.', 'warning');
            return null;
        }

        $lines = $this->selectedLinesQuery()
            ->orderBy('customer_name')
            ->orderBy('tracking_number')
            ->get();

        if ($lines->isEmpty()) {
            $this->showMessage('Seçili gün için dışarı aktarılacak Sürat raporu yok.', 'warning');
            return null;
        }

        $fileDate = Carbon::parse($this->selectedDate)->format('Y-m-d');

        return $this->downloadLines(
            $lines,
            'surat_kargo_gunluk_rapor_' . $fileDate . '_' . now()->format('H-i') . '.xlsx',
            'SÜRAT KARGO GÜNLÜK RAPORU',
            Carbon::parse($this->selectedDate)->format('d.m.Y')
        );
    }

    public function exportSelectedWeek()
    {
        if (!$this->selectedDate) {
            $this->showMessage('Haftalık rapor için önce bir gün seçin.', 'warning');
            return null;
        }

        $date = Carbon::parse($this->selectedDate);
        $start = $date->copy()->startOfWeek(Carbon::MONDAY);
        $end = $date->copy()->endOfWeek(Carbon::SUNDAY);

        return $this->exportDateRange($start, $end, 'haftalik', 'SÜRAT KARGO HAFTALIK RAPORU');
    }

    public function exportSelectedMonth()
    {
        if (!$this->selectedDate) {
            $this->showMessage('Aylık rapor için önce bir gün seçin.', 'warning');
            return null;
        }

        $date = Carbon::parse($this->selectedDate);

        return $this->exportDateRange(
            $date->copy()->startOfMonth(),
            $date->copy()->endOfMonth(),
            'aylik',
            'SÜRAT KARGO AYLIK RAPORU'
        );
    }

    public function exportSummaryRange()
    {
        [$start, $end] = $this->summaryDateRange();

        if (!$start || !$end) {
            $this->showMessage('Filtre aralığı indirilemedi. Lütfen geçerli bir tarih filtresi seçin.', 'warning');
            return null;
        }

        return $this->exportDateRange($start, $end, 'filtre_araligi', 'SÜRAT KARGO FİLTRE RAPORU');
    }

    protected function selectedLinesQuery(): Builder
    {
        $query = CargoReportLine::query()
            ->where('user_id', auth()->id())
            ->where('carrier_code', 'surat')
            ->whereDate('report_date', Carbon::parse($this->selectedDate)->toDateString());

        if ($this->search !== '') {
            $search = trim($this->search);
            $query->where(function (Builder $subQuery) use ($search) {
                $subQuery
                    ->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhere('web_order_code', 'like', "%{$search}%")
                    ->orWhere('sales_code', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    protected function dateRangeLinesQuery(Carbon $start, Carbon $end): Builder
    {
        return CargoReportLine::query()
            ->where('user_id', auth()->id())
            ->where('carrier_code', 'surat')
            ->whereBetween('report_date', [$start->toDateString(), $end->toDateString()]);
    }

    protected function exportDateRange(Carbon $start, Carbon $end, string $filePrefix, string $title)
    {
        $lines = $this->dateRangeLinesQuery($start, $end)
            ->orderBy('report_date')
            ->orderBy('customer_name')
            ->orderBy('tracking_number')
            ->get();

        if ($lines->isEmpty()) {
            $this->showMessage($start->format('d.m.Y') . ' - ' . $end->format('d.m.Y') . ' aralığında dışarı aktarılacak Sürat raporu yok.', 'warning');
            return null;
        }

        $fileName = 'surat_kargo_' . $filePrefix . '_' . $start->format('Y-m-d') . '_' . $end->format('Y-m-d') . '_' . now()->format('H-i') . '.xlsx';

        return $this->downloadLines(
            $lines,
            $fileName,
            $title,
            $start->format('d.m.Y') . ' - ' . $end->format('d.m.Y')
        );
    }

    protected function downloadLines($lines, string $fileName, string $title, string $rangeLabel)
    {
        try {
            $spreadsheet = new Spreadsheet();
            $this->createSummarySheet($spreadsheet, $lines, $title, $rangeLabel);
            $this->createDailySummarySheet($spreadsheet, $lines);
            $this->createDetailSheet($spreadsheet, $lines);

            $tempPath = storage_path('app/temp/' . $fileName);

            if (!is_dir(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }

            (new Xlsx($spreadsheet))->save($tempPath);

            return response()->download($tempPath, $fileName)->deleteFileAfterSend();
        } catch (\Throwable $exception) {
            Log::error('Sürat rapor arşivi: Export hatası', [
                'file_name' => $fileName,
                'error' => $exception->getMessage(),
            ]);
            $this->showMessage('Export hatası: ' . $exception->getMessage(), 'warning');
            return null;
        }
    }

    protected function createSummarySheet(Spreadsheet $spreadsheet, $lines, string $title, string $rangeLabel): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sanitizeSheetName('Özet'));

        $this->writeCell($sheet, 'A1', $title);
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

        $summary = [
            ['Rapor aralığı', $rangeLabel],
            ['Gönderi', $lines->count()],
            ['Parça', $lines->sum('pieces')],
            ['Desi', $lines->sum('desi')],
            ['Tutar', $lines->sum('amount')],
            ['Ölçüm farkı', $lines->sum('measurement_amount')],
            ['Toplam tutar', $lines->sum('total_amount')],
        ];

        $row = 3;
        foreach ($summary as [$label, $value]) {
            $this->writeCell($sheet, 'A' . $row, $label);
            $this->writeCell($sheet, 'B' . $row, $value, is_numeric($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(24);
        $sheet->getColumnDimension('B')->setWidth(22);
    }

    protected function createDailySummarySheet(Spreadsheet $spreadsheet, $lines): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($this->sanitizeSheetName('Gün Özeti'));

        $headers = ['Tarih', 'Gönderi', 'Parça', 'Desi', 'Tutar', 'Ölçüm Tutarı', 'Toplam Tutar'];

        foreach ($headers as $index => $header) {
            $this->writeCell($sheet, $this->columnLetter($index + 1) . '1', $header);
        }

        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->getStyle('A1:G1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('334155');
        $sheet->getStyle('A1:G1')->getFont()->getColor()->setRGB('FFFFFF');

        $row = 2;
        $dailyGroups = $lines
            ->groupBy(fn (CargoReportLine $line) => $line->report_date?->toDateString() ?: 'tarihsiz')
            ->sortKeys();

        foreach ($dailyGroups as $date => $dayLines) {
            $this->writeCell($sheet, 'A' . $row, $date === 'tarihsiz' ? 'Tarihsiz' : Carbon::parse($date)->format('d.m.Y'));
            $this->writeCell($sheet, 'B' . $row, $dayLines->count(), DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, 'C' . $row, $dayLines->sum('pieces'), DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, 'D' . $row, $dayLines->sum('desi'), DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, 'E' . $row, $dayLines->sum('amount'), DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, 'F' . $row, $dayLines->sum('measurement_amount'), DataType::TYPE_NUMERIC);
            $this->writeCell($sheet, 'G' . $row, $dayLines->sum('total_amount'), DataType::TYPE_NUMERIC);
            $row++;
        }

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    protected function createDetailSheet(Spreadsheet $spreadsheet, $lines): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($this->sanitizeSheetName('Gönderiler'));

        $headers = [
            'Rapor Tarihi', 'Takip No', 'Web Sipariş Kodu', 'Satış Kodu', 'Müşteri',
            'Gönderen', 'Durum', 'Parça', 'Desi', 'Tutar', 'Ölçüm Tutarı',
            'Toplam Tutar', 'KDV', 'Tutar Kaynağı', 'İl', 'İlçe', 'Evrak Tarihi',
            'Son Hareket', 'Teslim Tarihi', 'Teslim Alan',
        ];

        foreach ($headers as $index => $header) {
            $this->writeCell($sheet, $this->columnLetter($index + 1) . '1', $header);
        }

        $sheet->getStyle('A1:T1')->getFont()->setBold(true);
        $sheet->getStyle('A1:T1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1F2937');
        $sheet->getStyle('A1:T1')->getFont()->getColor()->setRGB('FFFFFF');

        $row = 2;
        foreach ($lines as $line) {
            $values = [
                $line->report_date?->format('d.m.Y'),
                $line->tracking_number,
                $line->web_order_code,
                $line->sales_code,
                $line->customer_name,
                $line->sender_name,
                $line->status,
                (int) $line->pieces,
                (float) $line->desi,
                (float) $line->amount,
                (float) $line->measurement_amount,
                (float) $line->total_amount,
                (float) $line->vat_amount,
                $line->amount_source,
                $line->destination_city,
                $line->destination_district,
                $line->document_date?->format('d.m.Y H:i'),
                $line->last_event_at?->format('d.m.Y H:i'),
                $line->delivered_at?->format('d.m.Y H:i'),
                $line->delivered_to,
            ];

            foreach ($values as $index => $value) {
                $type = in_array($index, [7, 8, 9, 10, 11, 12], true) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING;
                $this->writeCell($sheet, $this->columnLetter($index + 1) . $row, $value, $type);
            }

            $row++;
        }

        foreach (range('A', 'T') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    protected function writeCell(Worksheet $sheet, string $cell, mixed $value, string $type = DataType::TYPE_STRING): void
    {
        if ($type === DataType::TYPE_NUMERIC) {
            $sheet->setCellValueExplicit($cell, (float) ($value ?? 0), DataType::TYPE_NUMERIC);
            return;
        }

        $sheet->setCellValueExplicit(
            $cell,
            (string) app(ExcelService::class)->cleanString($value ?? ''),
            DataType::TYPE_STRING
        );
    }

    protected function sanitizeSheetName(string $name): string
    {
        $name = (string) app(ExcelService::class)->cleanString($name);
        $name = str_replace([':', '\\', '/', '?', '*', '[', ']'], '', $name);

        return mb_strlen($name) > 31 ? mb_substr($name, 0, 31) : ($name ?: 'Sheet');
    }

    protected function columnLetter(int $column): string
    {
        $letter = '';

        while ($column > 0) {
            $modulo = ($column - 1) % 26;
            $letter = chr(65 + $modulo) . $letter;
            $column = intdiv($column - $modulo, 26);
        }

        return $letter;
    }

    protected function latestReportDate(): ?string
    {
        if (!Schema::hasTable('cargo_report_lines')) {
            return null;
        }

        $date = CargoReportLine::query()
            ->where('user_id', auth()->id())
            ->where('carrier_code', 'surat')
            ->max('report_date');

        return $date ? Carbon::parse($date)->toDateString() : null;
    }

    protected function latestReportDateInRange(string $startDate, string $endDate): ?string
    {
        if (!Schema::hasTable('cargo_report_lines')) {
            return null;
        }

        $date = CargoReportLine::query()
            ->where('user_id', auth()->id())
            ->where('carrier_code', 'surat')
            ->whereBetween('report_date', [
                Carbon::parse($startDate)->toDateString(),
                Carbon::parse($endDate)->toDateString(),
            ])
            ->max('report_date');

        return $date ? Carbon::parse($date)->toDateString() : null;
    }

    protected function summaryDateRange(): array
    {
        [$start, $end] = match ($this->summaryPreset) {
            'last_7' => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
            'last_30' => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
            'this_week' => [now()->startOfWeek(Carbon::MONDAY), now()->endOfWeek(Carbon::SUNDAY)],
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'custom' => [
                $this->summaryStartDate ? Carbon::parse($this->summaryStartDate)->startOfDay() : null,
                $this->summaryEndDate ? Carbon::parse($this->summaryEndDate)->endOfDay() : null,
            ],
            default => [now()->subDays(13)->startOfDay(), now()->endOfDay()],
        };

        if ($start && $end && $start->gt($end)) {
            return [$end, $start];
        }

        return [$start, $end];
    }

    protected function resetSummaryCustomRange(): void
    {
        [$start, $end] = $this->summaryDateRange();

        if ($start && $end) {
            $this->summaryStartDate = $start->toDateString();
            $this->summaryEndDate = $end->toDateString();
        }
    }

    protected function showMessage(string $message, string $tone = 'info'): void
    {
        $this->message = $message;
        $this->messageTone = $tone;
    }

    public function render()
    {
        return view('livewire.cargo.surat-report-archive');
    }
}
