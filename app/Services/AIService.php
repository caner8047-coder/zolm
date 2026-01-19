<?php

namespace App\Services;

use App\Models\Report;
use Illuminate\Support\Facades\Http;

class AIService
{
    protected string $provider;
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->provider = config('ai.provider', 'groq');
        $this->apiKey = config('ai.api_key', '');
        $this->model = config('ai.model', 'llama-3.3-70b-versatile');
    }

    /**
     * Ask a question to the AI
     */
    public function ask(string $role, string $question, ?Report $report = null): string
    {
        // Eğer API anahtarı yoksa demo yanıt dön
        if (empty($this->apiKey)) {
            return $this->getDemoResponse($role, $question, $report);
        }

        try {
            $systemPrompt = $this->getSystemPrompt($role, $report);
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->getApiUrl(), [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $question],
                ],
                'max_tokens' => 1024,
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['choices'][0]['message']['content'] ?? 'Yanıt alınamadı.';
            }

            return 'API hatası: ' . $response->body();

        } catch (\Exception $e) {
            return 'Bağlantı hatası: ' . $e->getMessage();
        }
    }

    /**
     * Get API URL based on provider
     */
    protected function getApiUrl(): string
    {
        return match ($this->provider) {
            'groq' => 'https://api.groq.com/openai/v1/chat/completions',
            'openai' => 'https://api.openai.com/v1/chat/completions',
            default => 'https://api.groq.com/openai/v1/chat/completions',
        };
    }

    /**
     * Get system prompt based on role
     */
    protected function getSystemPrompt(string $role, ?Report $report): string
    {
        $basePrompt = match ($role) {
            'production' => 'Sen bir üretim müdürüsün. E-ticaret sipariş verilerini analiz edip üretim planlaması konusunda önerilerde bulunuyorsun. Türkçe yanıt ver.',
            'operation' => 'Sen bir operasyon sorumlusun. E-ticaret sipariş ve kargo verilerini analiz edip operasyonel önerilerde bulunuyorsun. Acil durumları, gecikmeli kargoları ve öncelikli işleri belirle. Türkçe yanıt ver.',
            default => 'Sen bir E-ticaret, Üretim ve Operasyon Uzmanısın. Sipariş verilerini analiz edip profesyonel önerilerde bulunuyorsun. Türkçe yanıt ver.',
        };

        if ($report && $report->files->isNotEmpty()) {
            $basePrompt .= "\n\nMevcut rapor bilgileri:\n";
            $basePrompt .= "- Dosya: {$report->original_filename}\n";
            $basePrompt .= "- Oluşturulma: {$report->created_at}\n";
            $basePrompt .= "- Çıktı dosyaları: " . $report->files->pluck('filename')->join(', ');
        }

        return $basePrompt;
    }

    /**
     * Demo response when API key is not configured
     */
    protected function getDemoResponse(string $role, string $question, ?Report $report): string
    {
        $demoResponses = [
            'Merhaba! Ben ZOLM AI asistanıyım. Şu anda demo modunda çalışıyorum.',
            'Sipariş verilerinizi analiz etmek için hazırım. API anahtarı yapılandırıldığında tam işlevsellik sağlanacaktır.',
            'Bu bir demo yanıttır. Gerçek AI entegrasyonu için Groq API anahtarı gereklidir.',
        ];

        $response = $demoResponses[array_rand($demoResponses)];
        
        if (str_contains(strtolower($question), 'acil') || str_contains(strtolower($question), 'bugün')) {
            $response .= "\n\n📦 **Demo Öneri:** Bugün kargoya verilmesi gereken siparişler için 'Kargoya Son Teslim Tarihi' sütununu kontrol edin.";
        }

        return $response;
    }
}
