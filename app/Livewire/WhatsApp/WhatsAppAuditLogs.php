<?php

namespace App\Livewire\WhatsApp;

use App\Models\WaAuditLog;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class WhatsAppAuditLogs extends Component
{
    use WithPagination;

    public string $actionFilter = '';
    public string $entityFilter = '';
    public int $limit = 50;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    public function getLogsProperty()
    {
        $query = WaAuditLog::with('user')->orderByDesc('created_at');

        if ($this->actionFilter) {
            $query->where('action', 'like', "%{$this->actionFilter}%");
        }
        if ($this->entityFilter) {
            $query->where('entity_type', $this->entityFilter);
        }

        return $query->paginate($this->limit);
    }

    public function render()
    {
        return view('livewire.whatsapp.whatsapp-audit-logs');
    }
}
