<?php

namespace App\Livewire;

use App\Models\AIConversation;
use App\Models\Report;
use App\Services\AIService;
use Livewire\Component;

class AIChat extends Component
{
    public string $message = '';
    public array $messages = [];
    public bool $isTyping = false;
    public ?int $selectedReportId = null;
    public string $role = 'expert';

    public function mount()
    {
        $this->messages = [
            [
                'role' => 'assistant',
                'content' => 'Merhaba! Ben ZOLM AI asistanıyım. E-ticaret, üretim ve operasyon konularında size yardımcı olabilirim. Nasıl yardımcı olabilirim?',
            ]
        ];
    }

    public function sendMessage()
    {
        if (empty(trim($this->message))) {
            return;
        }

        $userMessage = $this->message;
        $this->messages[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];
        $this->message = '';
        $this->isTyping = true;

        $aiService = app(AIService::class);
        $report = $this->selectedReportId ? Report::find($this->selectedReportId) : null;
        
        $response = $aiService->ask($this->role, $userMessage, $report);

        $this->messages[] = [
            'role' => 'assistant',
            'content' => $response,
        ];

        $this->isTyping = false;

        // Konuşmayı kaydet
        $conversation = AIConversation::firstOrCreate(
            ['user_id' => auth()->id(), 'report_id' => $this->selectedReportId],
            ['messages' => []]
        );
        $conversation->messages = $this->messages;
        $conversation->save();
    }

    public function setRole($role)
    {
        $this->role = $role;
    }

    public function clearChat()
    {
        $this->messages = [
            [
                'role' => 'assistant',
                'content' => 'Sohbet temizlendi. Size nasıl yardımcı olabilirim?',
            ]
        ];
    }

    public function getRecentReportsProperty()
    {
        return Report::where('user_id', auth()->id())
            ->where('status', 'success')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }

    public function render()
    {
        return view('livewire.a-i-chat')
            ->layout('layouts.app');
    }
}
