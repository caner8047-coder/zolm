<?php

namespace App\Services\WhatsApp;

/**
 * Test ve geliştirme ortamı için stub AI sağlayıcısı.
 * Gerçek LLM API çağrısı yapmaz.
 */
class FakeAiProvider implements AiProviderInterface
{
    private const INTENT_PATTERNS = [
        'product_lookup' => ['ürün', 'fiyat', 'stok', 'model', 'renk', 'varyasyon', 'kanepe', 'koltuk', 'masa', 'sandalye'],
        'order_status' => ['sipariş', 'siparişim', 'kargom', 'nerede', 'teslimat', 'gönderi'],
        'return_status' => ['iade', 'iadem', 'geri gönderim', 'değişim', 'kargo iade'],
        'policy' => ['teslimat', 'garanti', 'ödeme', 'montaj', 'kurulum', 'bakım', 'politika', 'nasıl'],
        'human_handoff' => ['temsilci', 'destek', 'insan', 'konuşmak', 'yönetici', 'şikayet'],
        'greeting' => ['merhaba', 'selam', 'günaydın', 'iyi günler', 'hey'],
    ];

    public function chat(string $systemPrompt, string $userMessage, array $tools = []): array
    {
        $intent = $this->classifyIntent($userMessage);
        $toolCalls = $this->suggestToolCalls($intent, $userMessage);

        $response = $this->generateResponse($intent, $userMessage);

        return [
            'intent' => $intent,
            'response' => $response,
            'tool_calls' => $toolCalls,
        ];
    }

    private function classifyIntent(string $message): string
    {
        $lowerMessage = mb_strtolower($message);

        foreach (self::INTENT_PATTERNS as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_strpos($lowerMessage, $keyword) !== false) {
                    return $intent;
                }
            }
        }

        return 'unknown';
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

    private function generateResponse(string $intent, string $message): string
    {
        return match ($intent) {
            'greeting' => 'Merhaba! Size nasıl yardımcı olabilirim?',
            'product_lookup' => 'Ürün bilgisini arıyorum, lütfen bekleyin...',
            'order_status' => 'Sipariş durumunuzu kontrol ediyorum...',
            'return_status' => 'İade durumunuzu kontrol ediyorum...',
            'policy' => 'Bilgi bankamızdan ilgili politikayı arıyorum...',
            'human_handoff' => 'Sizi destek ekibimize bağlıyorum, lütfen bekleyin.',
            default => 'Size nasıl yardımcı olabilirim? Ürün, sipariş veya iade hakkında soru sorabilirsiniz.',
        };
    }
}
