<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Gemini API kullanarak gerçek LLM entegrasyonu.
 * FakeAiProvider yerine kullanılır.
 */
class GeminiAiProvider implements AiProviderInterface
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key', '');
        $this->model = config('services.gemini.model', 'gemini-1.5-flash');
    }

    public function chat(string $systemPrompt, string $userMessage, array $tools = []): array
    {
        if (empty($this->apiKey)) {
            Log::warning('Gemini API key tanımlı değil, FakeAiProvider kullanılıyor');
            $fake = new FakeAiProvider();
            return $fake->chat($systemPrompt, $userMessage, $tools);
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(30)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}",
                [
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $userMessage]]],
                    ],
                    'systemInstruction' => [
                        'parts' => [['text' => $systemPrompt]],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.3,
                        'maxOutputTokens' => 500,
                    ],
                ]
            );

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

                return [
                    'intent' => $this->classifyIntent($userMessage),
                    'response' => $text,
                    'tool_calls' => $this->suggestToolCalls($this->classifyIntent($userMessage), $userMessage),
                ];
            }

            Log::warning('Gemini API hatası', ['status' => $response->status()]);
        } catch (\Throwable $e) {
            Log::error('Gemini API bağlantı hatası', ['error' => $e->getMessage()]);
        }

        // Hata durumunda fake provider'a dön
        $fake = new FakeAiProvider();
        return $fake->chat($systemPrompt, $userMessage, $tools);
    }

    private function classifyIntent(string $message): string
    {
        $lowerMessage = mb_strtolower($message);

        $patterns = [
            'product_lookup' => ['ürün', 'fiyat', 'stok', 'model', 'renk', 'varyasyon'],
            'order_status' => ['sipariş', 'siparişim', 'kargom', 'nerede', 'teslimat'],
            'return_status' => ['iade', 'iadem', 'geri gönderim', 'değişim'],
            'policy' => ['teslimat', 'garanti', 'ödeme', 'montaj', 'politika'],
            'human_handoff' => ['temsilci', 'destek', 'insan', 'konuşmak', 'şikayet'],
            'greeting' => ['merhaba', 'selam', 'günaydın'],
        ];

        foreach ($patterns as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_strpos($lowerMessage, $keyword) !== false) {
                    return $intent;
                }
            }
        }

        return 'general';
    }

    private function suggestToolCalls(string $intent, string $message): array
    {
        return match ($intent) {
            'product_lookup' => [['tool' => 'product_lookup', 'params' => ['query' => $message]]],
            'order_status' => [['tool' => 'order_status', 'params' => []]],
            'return_status' => [['tool' => 'return_status', 'params' => []]],
            'policy' => [['tool' => 'policy_knowledge', 'params' => ['query' => $message]]],
            default => [],
        };
    }
}
