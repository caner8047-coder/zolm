<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceQuestion;
use App\Models\MarketplaceQuestionTemplate;
use App\Services\AIService;
use Illuminate\Support\Str;

class MarketplaceQuestionAiService
{
    public function __construct(
        protected AIService $aiService,
    ) {
    }

    public function suggestAnswer(MarketplaceQuestion $question): string
    {
        $templates = MarketplaceQuestionTemplate::query()
            ->active()
            ->where(function ($query) use ($question) {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $question->store->user_id);
            })
            ->where(function ($query) use ($question) {
                $query->whereNull('marketplace')
                    ->orWhere('marketplace', $question->store->marketplace);
            })
            ->latest('usage_count')
            ->limit(6)
            ->get(['title', 'body'])
            ->map(fn (MarketplaceQuestionTemplate $template) => "- {$template->title}: {$template->body}")
            ->implode("\n");

        $prompt = <<<PROMPT
ZOLM pazaryeri müşteri soruları ekranında satıcı adına kısa, net ve güven veren Türkçe cevap yaz.

Kurallar:
- Sadece müşteriye gönderilecek cevabı yaz.
- Kesin olmayan stok, kargo, fiyat veya garanti vaadi verme.
- Müşteriye hitap sıcak ama sade olsun.
- Cevap 2-4 cümleyi geçmesin.
- Marka veya mağaza adına özür/teşekkür gerekiyorsa kullan.

Pazaryeri: {$question->store->marketplace}
Mağaza: {$question->store->store_name}
Ürün: {$question->product_name}
SKU: {$question->product_sku}
Barkod: {$question->product_barcode}
Müşteri sorusu:
{$question->question_text}

Hazır cevap örnekleri:
{$templates}
PROMPT;

        $answer = trim($this->aiService->ask('expert', $prompt));

        if ($answer === '' || Str::startsWith($answer, ['❌', 'Bağlantı hatası'])) {
            $answer = 'Merhaba, ürünümüzle ilgili sorunuz için teşekkür ederiz. Kontrol edip size en kısa sürede net bilgi paylaşacağız.';
        }

        $question->forceFill([
            'ai_suggested_answer' => $answer,
            'ai_confidence' => 60,
            'ai_status' => 'suggested',
        ])->save();

        return $answer;
    }
}
