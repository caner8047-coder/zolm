<?php

namespace App\Livewire\Accounting;

use App\Models\AssistantQuery;
use App\Models\AssistantSavedQuestion;
use App\Services\Accounting\AssistantService;
use InvalidArgumentException;
use Livewire\Component;

class Assistant extends Component
{
    public string $questionText  = '';
    public string $message       = '';
    public string $messageType   = 'success';
    public string $selectedIntent = '';
    public string $searchHistory  = '';

    // ─── HAZİR SORULAR ────────────────────────────────────────────────────

    private array $defaultSuggestedQuestions = [
        'Bu ayki nakit akışım nasıl?',
        'Geciken alacaklarım kimde?',
        'En çok borcum olan tedarikçiler kim?',
        'Stok değerim ne kadar?',
        'Bu ay kar zarar durumum ne?',
        'Genel finans durumumu özetle.',
        'Riskli carileri göster.',
        'Önümüzdeki 30 gün nakit durumum nasıl?',
    ];

    // ─── SORU SOR ─────────────────────────────────────────────────────────

    public function askQuestion(?string $customText = null): void
    {
        $userId = auth()->id();
        $text   = $customText ?: $this->questionText;
        $text   = trim($text);

        $this->message = '';

        // Livewire validation
        try {
            $this->validateOnly('questionText', [
                'questionText' => ['string', 'min:3', 'max:1000'],
            ]);
        } catch (\Throwable) {
            // customText geliyorsa validate atlıyoruz — sadece uzunluk guard
        }

        if ($text === '') {
            $this->message     = 'Lütfen bir soru yazın.';
            $this->messageType = 'error';
            return;
        }

        if (mb_strlen($text) < 3) {
            $this->message     = 'Soru en az 3 karakter olmalıdır.';
            $this->messageType = 'error';
            return;
        }

        if (mb_strlen($text) > 1000) {
            $this->message     = 'Soru en fazla 1000 karakter olabilir.';
            $this->messageType = 'error';
            return;
        }

        try {
            $service = app(AssistantService::class);
            $service->askAssistant($userId, $text);

            $this->questionText = '';
        } catch (InvalidArgumentException $e) {
            $this->message     = $e->getMessage();
            $this->messageType = 'error';
        } catch (\Exception $e) {
            $this->message     = 'Asistan yanıt verirken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    // ─── KAYDET ──────────────────────────────────────────────────────────

    public function saveQuestion(string $text): void
    {
        $userId = auth()->id();
        $text   = mb_substr(trim($text), 0, 1000);

        if ($text === '') {
            return;
        }

        try {
            AssistantSavedQuestion::firstOrCreate(
                ['user_id' => $userId, 'query_text' => $text],
                ['title'   => mb_substr($text, 0, 200)]
            );

            $this->message     = 'Soru sık kullanılanlara eklendi.';
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->message     = 'Soru kaydedilirken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    // ─── SİL ─────────────────────────────────────────────────────────────

    public function deleteSavedQuestion(int $id): void
    {
        $userId = auth()->id();
        AssistantSavedQuestion::where('user_id', $userId)->findOrFail($id)->delete();
        $this->message     = 'Soru sık kullanılanlardan çıkarıldı.';
        $this->messageType = 'success';
    }

    // ─── GEÇMİŞ TEMİZLE ─────────────────────────────────────────────────

    public function clearHistory(): void
    {
        $userId = auth()->id();
        AssistantQuery::where('user_id', $userId)->delete();
        $this->message     = 'Sohbet geçmişi temizlendi.';
        $this->messageType = 'success';
    }

    // ─── TEKRARLA ────────────────────────────────────────────────────────

    public function repeatQuestion(int $queryId): void
    {
        $userId = auth()->id();
        $query  = AssistantQuery::where('user_id', $userId)->findOrFail($queryId);
        $this->askQuestion($query->query_text);
    }

    // ─── COMPUTED PROPERTIES ─────────────────────────────────────────────

    public function getChatHistoryProperty()
    {
        $q = AssistantQuery::where('user_id', auth()->id());

        if ($this->selectedIntent !== '') {
            $q->where('intent', $this->selectedIntent);
        }

        if ($this->searchHistory !== '') {
            $q->where('query_text', 'like', '%' . $this->searchHistory . '%');
        }

        // Konuşma akışı: en eski üstte (kronolojik)
        return $q->orderBy('id')->get();
    }

    public function getSavedQuestionsProperty()
    {
        return AssistantSavedQuestion::where('user_id', auth()->id())
            ->orderByDesc('id')
            ->get();
    }

    public function getSuggestedQuestionsProperty(): array
    {
        // Kaydedilmiş + varsayılan sorular birleşik
        $saved = $this->savedQuestions->pluck('query_text')->toArray();
        return array_unique(array_merge($saved, $this->defaultSuggestedQuestions));
    }

    // ─── RENDER ──────────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.accounting.assistant')
            ->layout('layouts.app');
    }
}
