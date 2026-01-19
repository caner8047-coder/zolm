<?php

namespace App\Livewire;

use App\Models\Report;
use App\Models\ReportFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithPagination;

class ReportHistory extends Component
{
    use WithPagination;

    public string $reportType = 'all';
    public string $period = 'daily';
    public ?string $startDate = null;
    public ?string $endDate = null;
    public array $expandedDates = [];
    
    // Silme işlemleri için
    public array $selectedReports = [];
    public bool $selectAll = false;
    public bool $showDeleteModal = false;
    public ?int $deletingReportId = null;

    // Pagination
    public int $perPage = 50;

    protected $listeners = ['refreshReports' => '$refresh'];

    public function mount()
    {
        $this->startDate = now()->subDays(7)->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedReports = $this->reports->flatten()->pluck('id')->toArray();
        } else {
            $this->selectedReports = [];
        }
    }

    public function toggleDate($date)
    {
        if (in_array($date, $this->expandedDates)) {
            $this->expandedDates = array_diff($this->expandedDates, [$date]);
        } else {
            $this->expandedDates[] = $date;
        }
    }

    // === SİLME İŞLEMLERİ ===

    /**
     * Tek rapor silme onay modalı
     */
    public function confirmDelete($reportId)
    {
        $this->deletingReportId = $reportId;
        $this->showDeleteModal = true;
    }

    /**
     * Tek raporu sil
     */
    public function deleteReport()
    {
        if (!$this->deletingReportId) return;

        $report = Report::find($this->deletingReportId);
        if ($report) {
            $this->deleteReportWithFiles($report);
        }

        $this->showDeleteModal = false;
        $this->deletingReportId = null;
        
        session()->flash('success', 'Rapor başarıyla silindi.');
    }

    /**
     * Seçili raporları toplu sil
     */
    public function deleteSelected()
    {
        if (empty($this->selectedReports)) {
            session()->flash('error', 'Silmek için rapor seçin.');
            return;
        }

        $count = 0;
        foreach ($this->selectedReports as $reportId) {
            $report = Report::find($reportId);
            if ($report) {
                $this->deleteReportWithFiles($report);
                $count++;
            }
        }

        $this->selectedReports = [];
        $this->selectAll = false;
        $this->showDeleteModal = false;

        session()->flash('success', "{$count} rapor başarıyla silindi.");
    }

    /**
     * Belirli bir tarihteki tüm raporları sil
     */
    public function deleteByDate($date)
    {
        $reports = Report::whereDate('created_at', $date)->get();
        $count = 0;

        foreach ($reports as $report) {
            $this->deleteReportWithFiles($report);
            $count++;
        }

        session()->flash('success', "{$date} tarihli {$count} rapor silindi.");
    }

    /**
     * Raporu ve dosyalarını sil
     */
    protected function deleteReportWithFiles(Report $report): void
    {
        // Önce dosyaları sil
        foreach ($report->files as $file) {
            // Storage'dan fiziksel dosyayı sil
            if (Storage::disk('local')->exists($file->file_path)) {
                Storage::disk('local')->delete($file->file_path);
            }
            $file->delete();
        }

        // Rapor klasörünü temizle
        $reportDir = 'private/reports/' . $report->id;
        if (Storage::disk('local')->exists($reportDir)) {
            Storage::disk('local')->deleteDirectory($reportDir);
        }

        // Raporu sil
        $report->delete();
    }

    /**
     * Eski raporları otomatik temizle (30 günden eski)
     */
    public function cleanupOldReports()
    {
        $cutoffDate = now()->subDays(30);
        $reports = Report::where('created_at', '<', $cutoffDate)->get();
        $count = 0;

        foreach ($reports as $report) {
            $this->deleteReportWithFiles($report);
            $count++;
        }

        session()->flash('success', "30 günden eski {$count} rapor temizlendi.");
    }

    // === İNDİRME İŞLEMLERİ ===

    public function downloadFile($fileId)
    {
        $file = ReportFile::find($fileId);
        if ($file) {
            $fullPath = Storage::disk('local')->path($file->file_path);
            if (file_exists($fullPath)) {
                return response()->download($fullPath, $file->filename);
            }
        }
    }

    public function downloadAllForDate($date)
    {
        // ZIP oluşturma işlemi - gelecekte eklenecek
        session()->flash('info', 'ZIP indirme özelliği yakında eklenecek.');
    }

    // === VERİ SORGULARI ===

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

        // Performans için limit uygula
        $reports = $query->limit($this->perPage * 10)->get();

        return $reports->groupBy(fn($report) => $report->created_at->format('Y-m-d'));
    }

    /**
     * Toplam rapor sayısı (istatistik için)
     */
    public function getTotalReportsProperty(): int
    {
        return Report::count();
    }

    /**
     * Toplam dosya boyutu (istatistik için)
     */
    public function getTotalSizeProperty(): string
    {
        $totalBytes = ReportFile::sum(\DB::raw('COALESCE(
            (SELECT SUM(LENGTH(LOAD_FILE(CONCAT("' . storage_path('app/') . '", file_path)))) FROM report_files), 
            0
        )'));
        
        // Basit hesaplama - dosya sayısı * ortalama boyut
        $fileCount = ReportFile::count();
        $estimatedSize = $fileCount * 20000; // ~20KB ortalama
        
        if ($estimatedSize > 1073741824) {
            return round($estimatedSize / 1073741824, 2) . ' GB';
        } elseif ($estimatedSize > 1048576) {
            return round($estimatedSize / 1048576, 2) . ' MB';
        } else {
            return round($estimatedSize / 1024, 2) . ' KB';
        }
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->deletingReportId = null;
    }

    public function render()
    {
        return view('livewire.report-history')
            ->layout('layouts.app');
    }
}
