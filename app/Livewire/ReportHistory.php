<?php

namespace App\Livewire;

use App\Models\Report;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class ReportHistory extends Component
{
    public string $reportType = 'all';
    public string $period = 'daily';
    public ?string $startDate = null;
    public ?string $endDate = null;
    public array $expandedDates = [];

    public function mount()
    {
        $this->startDate = now()->subDays(7)->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
    }

    public function toggleDate($date)
    {
        if (in_array($date, $this->expandedDates)) {
            $this->expandedDates = array_diff($this->expandedDates, [$date]);
        } else {
            $this->expandedDates[] = $date;
        }
    }

    public function downloadFile($fileId)
    {
        $file = \App\Models\ReportFile::find($fileId);
        if ($file && $file->exists()) {
            return Storage::disk('local')->download($file->file_path, $file->filename);
        }
    }

    public function downloadAllForDate($date)
    {
        // ZIP oluşturma işlemi
    }

    public function getReportsProperty()
    {
        $query = Report::with(['files', 'profile', 'user'])
            ->where('status', 'success')
            ->orderBy('created_at', 'desc');

        if ($this->reportType === 'production') {
            $query->whereHas('profile', fn($q) => $q->where('type', 'production'));
        } elseif ($this->reportType === 'operation') {
            $query->whereHas('profile', fn($q) => $q->where('type', 'operation'));
        }

        if ($this->startDate) {
            $query->whereDate('created_at', '>=', $this->startDate);
        }
        if ($this->endDate) {
            $query->whereDate('created_at', '<=', $this->endDate);
        }

        return $query->get()->groupBy(fn($report) => $report->created_at->format('Y-m-d'));
    }

    public function render()
    {
        return view('livewire.report-history')
            ->layout('layouts.app');
    }
}
