<?php

namespace App\Services\Support\AI;

use App\Models\SupportConversation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class GeminiCustomerCareAiAdapter implements CustomerCareAiProviderInterface
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = (string) config('services.gemini.api_key', '');
        $this->model = config('services.gemini.model', 'gemini-1.5-flash');
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function generateAnswer(
        SupportConversation $conversation,
        array $history,
        ?string $promptTemplate = null
    ): CustomerCareAiResponseDto {
        if (empty($this->apiKey)) {
            // DEMO_MODE kontrolü: YALNIZCA local/testing ortamında ve demo_mode=true ise Fake'e düşer
            $isDemoAllowed = config('customer-care.demo_mode') === true
                && app()->environment(['local', 'testing']);

            if ($isDemoAllowed) {
                Log::warning('Gemini API key tanımlı değil, FakeCustomerCareAiAdapter kullanılıyor (local/test demo modu)');
                $fake = new FakeCustomerCareAiAdapter();
                return $fake->generateAnswer($conversation, $history, $promptTemplate);
            }

            // Production fail-closed — demo_mode=true olsa bile production ortamında izin verilmez
            throw new Exception('AI Provider API anahtarı eksik. Fail-closed ilkesi gereği işlem durduruldu.');
        }

        $startTime = microtime(true);
        $userMessage = end($history)['text'] ?? '';

        try {
            $systemInstruction = $promptTemplate ?? 'Müşteri sorularına profesyonel ve kısa yanıt ver.';

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(30)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}",
                [
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $userMessage]]],
                    ],
                    'systemInstruction' => [
                        'parts' => [['text' => $systemInstruction]],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'maxOutputTokens' => 800,
                    ],
                ]
            );

            $latency = (int)((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

                $detected = app(CustomerCareLanguageService::class)->detect($text);
                return new CustomerCareAiResponseDto(
                    $text,
                    0, // Gemini native güven skoru sağlamaz; orchestrator kanıtla kalibre eder.
                    ['Gemini AI Core'],
                    false,
                    $detected['language'] === 'und' ? 'tr' : $detected['language'],
                    (float) $detected['confidence'],
                );
            }

            throw new Exception('Gemini API başarısız yanıt döndürdü: Status ' . $response->status());
        } catch (\Throwable $e) {
            Log::error('CustomerCare AI hatası', ['error' => $e->getMessage()]);
            throw new Exception('AI servis hatası nedeniyle işlem fail-closed olarak durduruldu: ' . $e->getMessage());
        }
    }
}
