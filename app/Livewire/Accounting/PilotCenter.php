<?php

namespace App\Livewire\Accounting;

use Livewire\Component;
use Livewire\WithPagination;
use App\Services\Accounting\AccountingPilotReadinessService;
use App\Models\AccountingPilotFeedback;
use App\Models\AccountingPilotHealthSnapshot;

class PilotCenter extends Component
{
    use WithPagination;

    // Tabs
    public string $activeTab = 'health';

    // Search & Sort for Feedbacks
    public string $search = '';
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';
    
    public array $visibleColumns = [
        'module' => true,
        'type' => true,
        'severity' => true,
        'status' => true,
        'title' => true,
        'created_at' => true,
        'actions' => true,
    ];

    // Feedback Form Properties
    public string $module = '';
    public string $feedbackType = 'bug';
    public string $severity = 'medium';
    public string $title = '';
    public string $description = '';
    public string $browser = '';
    public ?int $viewportWidth = null;
    public ?int $viewportHeight = null;

    // Feedback Notification
    public string $message = '';
    public string $messageType = 'success';

    public function mount()
    {
        // Temel tarayıcı verilerini yakalama (JS ile de desteklenebilir)
        $this->browser = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }

    public function runHealthCheck()
    {
        $service = app(AccountingPilotReadinessService::class);
        $service->runHealthCheck(auth()->id());
        
        $this->message = 'Sistem sağlık taraması başarıyla çalıştırıldı ve yeni rapor üretildi!';
        $this->messageType = 'success';
    }

    protected static array $sortableColumns = [
        'module', 'type', 'severity', 'status', 'title', 'created_at'
    ];

    public function toggleColumn(string $column)
    {
        if (isset($this->visibleColumns[$column])) {
            $this->visibleColumns[$column] = !$this->visibleColumns[$column];
        }
    }

    public function sortTable(string $field)
    {
        if (!in_array($field, self::$sortableColumns, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'desc';
        }
    }

    public function createFeedback()
    {
        $this->validate([
            'module' => 'required|string',
            'feedbackType' => 'required|string',
            'severity' => 'required|string',
            'title' => 'required|string|min:5|max:255',
            'description' => 'nullable|string',
        ]);

        $service = app(AccountingPilotReadinessService::class);
        $service->createFeedback(auth()->id(), auth()->id(), [
            'module' => $this->module,
            'type' => $this->feedbackType,
            'severity' => $this->severity,
            'title' => $this->title,
            'description' => $this->description,
            'browser' => $this->browser,
            'viewport_width' => $this->viewportWidth,
            'viewport_height' => $this->viewportHeight,
        ]);

        $this->resetForm();
        $this->message = 'Geri bildirim başarıyla kaydedildi. İnceleme sırasına alındı.';
        $this->messageType = 'success';
    }

    public function resolveFeedback(int $id)
    {
        $feedback = AccountingPilotFeedback::findOrFail($id);
        
        $service = app(AccountingPilotReadinessService::class);
        $service->resolveFeedback($feedback, auth()->id());

        $this->message = 'Geri bildirim "Çözüldü" olarak işaretlendi.';
        $this->messageType = 'success';
    }

    private function resetForm()
    {
        $this->module = '';
        $this->feedbackType = 'bug';
        $this->severity = 'medium';
        $this->title = '';
        $this->description = '';
        $this->viewportWidth = null;
        $this->viewportHeight = null;
    }

    public function render()
    {
        $userId = auth()->id();
        $service = app(AccountingPilotReadinessService::class);
        
        // Health Snapshot
        $latestSnapshot = $service->getLatestSnapshot($userId);
        $summary = $service->feedbackSummary($userId);

        // Whitelist Sort fields
        $validatedSortField = in_array($this->sortField, self::$sortableColumns, true) ? $this->sortField : 'created_at';
        $validatedSortDirection = in_array(strtolower($this->sortDirection), ['asc', 'desc'], true) ? strtolower($this->sortDirection) : 'desc';

        // Feedbacks Query with Tenant Isolation and Search
        $feedbacksQuery = AccountingPilotFeedback::where('user_id', $userId)
            ->when($this->search !== '', function ($q) {
                $q->where(function ($sq) {
                    $sq->where('title', 'like', "%{$this->search}%")
                        ->orWhere('description', 'like', "%{$this->search}%")
                        ->orWhere('module', 'like', "%{$this->search}%");
                });
            })
            ->orderBy($validatedSortField, $validatedSortDirection);

        $feedbacks = $feedbacksQuery->paginate(10);

        return view('livewire.accounting.pilot-center', [
            'latestSnapshot' => $latestSnapshot,
            'summary' => $summary,
            'feedbacks' => $feedbacks,
        ])->layout('layouts.app');
    }
}
