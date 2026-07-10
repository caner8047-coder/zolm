<?php

namespace App\Livewire\Accounting;

use App\Models\AssistantQuery;
use App\Models\AssistantSavedQuestion;
use App\Services\Accounting\AssistantService;
use Livewire\Component;

class Assistant extends Component
{
    public string $questionText = '';

    public string $message = '';
    public string $messageType = 'success';

    public array $suggestedQuestions = [
        'Nakit akışım ve likidite durumum nedir?',
        'Alacaklarımın yaşlandırma durumu nedir?',
        'Bu ayki kârlılık durumum nasıl?',
        'Depolardaki toplam stok değerim nedir?',
    ];

    public function askQuestion(?string $customText = null): void
    {
        $userId = auth()->id();
        $text = $customText ?: $this->questionText;

        if (trim($text) === '') {
            return;
        }

        try {
            $service = app(AssistantService::class);
            $service->askAssistant($userId, $text);

            $this->questionText = '';
            $this->message = '';
        } catch (\Exception $e) {
            $this->message = 'Asistan yanıt verirken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function saveQuestion(string $text): void
    {
        $userId = auth()->id();

        try {
            AssistantSavedQuestion::firstOrCreate([
                'user_id' => $userId,
                'query_text' => $text,
            ], [
                'title' => $text,
            ]);

            $this->message = 'Soru sık kullanılanlara eklendi.';
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->message = 'Soru kaydedilirken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function deleteSavedQuestion(int $id): void
    {
        $userId = auth()->id();
        AssistantSavedQuestion::where('user_id', $userId)->findOrFail($id)->delete();
        $this->message = 'Soru sık kullanılanlardan çıkarıldı.';
        $this->messageType = 'success';
    }

    public function clearHistory(): void
    {
        $userId = auth()->id();
        AssistantQuery::where('user_id', $userId)->delete();
        $this->message = 'Sohbet geçmişi temizlendi.';
        $this->messageType = 'success';
    }

    public function getChatHistoryProperty()
    {
        return AssistantQuery::where('user_id', auth()->id())
            ->orderBy('id')
            ->get();
    }

    public function getSavedQuestionsProperty()
    {
        return AssistantSavedQuestion::where('user_id', auth()->id())
            ->orderByDesc('id')
            ->get();
    }

    public function render()
    {
        return view('livewire.accounting.assistant')
            ->layout('layouts.app');
    }
}
