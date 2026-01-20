<?php

namespace App\Livewire\Admin;

use App\Models\ActivityLog;
use Livewire\Component;
use Livewire\WithPagination;

class ActivityLogs extends Component
{
    use WithPagination;

    public string $search = '';
    public string $actionFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    public function mount()
    {
        $this->dateFrom = now()->subDays(7)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'actionFilter']);
        $this->dateFrom = now()->subDays(7)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function render()
    {
        $query = ActivityLog::with('user');

        if ($this->search) {
            $query->whereHas('user', function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%");
            })->orWhere('description', 'like', "%{$this->search}%");
        }

        if ($this->actionFilter) {
            $query->where('action', $this->actionFilter);
        }

        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(20);

        $actions = ActivityLog::distinct()->pluck('action')->toArray();

        return view('livewire.admin.activity-logs', [
            'logs' => $logs,
            'actions' => $actions,
        ])->layout('layouts.app');
    }
}
