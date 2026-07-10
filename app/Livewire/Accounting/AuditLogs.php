<?php

namespace App\Livewire\Accounting;

use App\Models\MpAuditLog;
use App\Models\MpPeriod;
use App\Services\AuditEngine;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLogs extends Component
{
    use WithPagination;

    // Filters
    public string $search = '';
    public string $filterStatus = ''; // open, resolved, ignored
    public string $filterSeverity = ''; // critical, warning, info
    public string $filterPeriod = '';

    // Action variables
    public ?int $selectedLogId = null;
    public string $resolutionNote = '';
    public string $actionType = ''; // resolve, ignore

    // Run audit
    public string $activePeriodId = '';

    // Messaging
    public string $message = '';
    public string $messageType = 'success';

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => ''],
        'filterSeverity' => ['except' => ''],
        'filterPeriod' => ['except' => ''],
    ];

    public function selectLog(int $logId, string $actionType): void
    {
        $userId = auth()->id();
        $log = MpAuditLog::whereHas('period', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->findOrFail($logId);

        $this->selectedLogId = $logId;
        $this->actionType = $actionType;
        $this->resolutionNote = $log->resolution_note ?? '';
    }

    public function saveResolution(): void
    {
        if (!$this->selectedLogId) {
            return;
        }

        $userId = auth()->id();
        $log = MpAuditLog::whereHas('period', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->findOrFail($this->selectedLogId);

        try {
            $newStatus = $this->actionType === 'resolve' ? 'resolved' : 'ignored';
            $log->update([
                'status' => $newStatus,
                'resolution_note' => $this->resolutionNote,
            ]);

            $this->message = 'Bulgu başarıyla güncellendi.';
            $this->messageType = 'success';
            $this->closeModal();
        } catch (\Exception $e) {
            $this->message = 'Güncelleme sırasında hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function reopenLog(int $logId): void
    {
        $userId = auth()->id();
        $log = MpAuditLog::whereHas('period', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->findOrFail($logId);

        try {
            $log->update([
                'status' => 'open',
            ]);
            $this->message = 'Bulgu tekrar açıldı.';
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->message = 'Hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function closeModal(): void
    {
        $this->selectedLogId = null;
        $this->resolutionNote = '';
        $this->actionType = '';
    }

    public function runAudit(): void
    {
        if ($this->activePeriodId === '') {
            $this->message = 'Lütfen denetlenecek dönemi seçin.';
            $this->messageType = 'error';
            return;
        }

        $userId = auth()->id();
        $period = MpPeriod::where('user_id', $userId)->findOrFail($this->activePeriodId);

        if ($period->is_locked) {
            $this->message = 'Kilitlenmiş dönemlerde denetim çalıştırılamaz.';
            $this->messageType = 'error';
            return;
        }

        try {
            $engine = app(AuditEngine::class);
            $engine->runAllRules($period);

            // Update error count
            $errCount = MpAuditLog::where('period_id', $period->id)->count();
            $period->update([
                'total_audit_errors' => $errCount,
            ]);

            $this->message = "Dönem denetimi tamamlandı. Toplam {$errCount} bulgu tespit edildi.";
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->message = 'Denetim çalıştırılırken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function getPeriodsProperty()
    {
        return MpPeriod::where('user_id', auth()->id())
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();
    }

    public function getLogsProperty()
    {
        $userId = auth()->id();
        $query = MpAuditLog::whereHas('period', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->with(['period', 'order']);

        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterSeverity !== '') {
            $query->where('severity', $this->filterSeverity);
        }

        if ($this->filterPeriod !== '') {
            $query->where('period_id', $this->filterPeriod);
        }

        if ($this->search !== '') {
            $query->where(function($q) {
                $q->where('title', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%")
                  ->orWhere('rule_code', 'like', "%{$this->search}%");
            });
        }

        return $query->orderByDesc('id')
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.accounting.audit-logs')
            ->layout('layouts.app');
    }
}
