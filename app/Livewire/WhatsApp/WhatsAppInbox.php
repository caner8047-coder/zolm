<?php

namespace App\Livewire\WhatsApp;

use App\Models\WaConversation;
use App\Models\WaInboundMessage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class WhatsAppInbox extends Component
{
    use WithPagination;

    public string $statusFilter = 'all';
    public ?int $selectedConversationId = null;
    public $conversations = [];
    public $messages = [];
    public int $unreadCount = 0;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $this->loadData();
    }

    public function loadData(): void
    {
        $this->unreadCount = WaInboundMessage::whereNull('read_at')->count();

        $query = WaConversation::with('contact', 'assignedUser')
            ->orderByDesc('last_message_at');

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $this->conversations = $query->limit(50)->get()->toArray();
    }

    public function selectConversation(int $conversationId): void
    {
        $this->selectedConversationId = $conversationId;

        $this->messages = WaInboundMessage::where('conversation_id', $conversationId)
            ->orderBy('received_at', 'asc')
            ->get()
            ->toArray();

        // Okunmamışları işaretle
        WaInboundMessage::where('conversation_id', $conversationId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $this->unreadCount = WaInboundMessage::whereNull('read_at')->count();
    }

    public function updatedStatusFilter(): void
    {
        $this->loadData();
    }

    public function render()
    {
        return view('livewire.whatsapp.whatsapp-inbox');
    }
}
