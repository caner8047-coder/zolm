<?php

namespace App\Services\Support;

use App\Models\SupportConversation;
use App\Models\SupportAgentAction;
use App\Models\ChannelListing;
use App\Models\ChannelProduct;
use App\Services\Support\Security\PiiRedactor;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class CustomerCareSalesAssistService
{
    /**
     * Cart signal freshness window (minutes).
     * Signed web chat cart signal bu süreden eskiyse kullanılmaz.
     */
    private const CART_SIGNAL_MAX_AGE_MINUTES = 60;

    /**
     * Müşteriye sunulacak satış copilot önerilerini ve alternatiflerini üretir.
     */
    public function generateSalesSuggestions(SupportConversation $conversation): array
    {
        // 1. Feature Flag Check
        if (!config('customer-care.sales_copilot_enabled', false)) {
            return [];
        }

        // 2. Public Comments/Reviews Constraint
        // Public comment kanallarında proaktif satış önerisi otomatik olarak engellenir.
        $publicChannels = ['google_business', 'instagram_comment', 'facebook_comment'];
        if (in_array($conversation->source_type, $publicChannels, true)) {
            return [];
        }

        $storeId = $conversation->store_id;
        $redactor = app(PiiRedactor::class);

        // 3. Web Chat sepet terk veya sepet verisi entegrasyonu
        // P0-3 FIX: Cart signal yalnız verified=true + fresh + source_type=web_chat ise kullanılır
        $recommendations = [];

        $ref = $conversation->source_reference_json ?? [];
        $cartValue = $this->resolveVerifiedCartValue($conversation, $ref);

        // Senaryo A: Sepet terk sinyali var ise sepet kurtarma taslağı öner
        if ($cartValue !== null && config('customer-care.cart_recovery_enabled', false)) {
            $recommendations[] = [
                'type' => 'cart_recovery',
                'title' => 'Sepet Kurtarma Taslağı',
                'description' => "Müşterinin doğrulanmış sepetinde ürün bulunmaktadır. Tamamlanmamış sipariş için sepet hatırlatması yapabilirsiniz.",
                'suggested_draft' => 'Merhaba, sepetinizdeki ürünleri fark ettik. Alışverişinizi tamamlamak isterseniz yardımcı olmaktan mutluluk duyarız.',
                'citation' => 'Verified Web Chat Cart Signal'
            ];
        }

        // Senaryo B: Stokta kalmayan ürün için alternatif bulma
        $lastInbound = $conversation->messages()
            ->where('direction', 'inbound')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastInbound) {
            $body = mb_strtolower($lastInbound->body_encrypted);

            // Eğer müşteri stokta olmayan veya biten bir şeyi soruyorsa
            if (str_contains($body, 'tükendi') || str_contains($body, 'bitti') || str_contains($body, 'stok yok') || str_contains($body, 'kalmadı')) {
                // P0-2 FIX: Hem stok hem fiyat freshness kontrolü zorunlu
                if ($this->isHighRiskAdviceRequest($body)) {
                    return $recommendations;
                }

                $queryTokens = $this->meaningfulTokens($body);
                $alternatives = ChannelListing::where('store_id', $storeId)
                    ->where('stock_quantity', '>', 0)
                    ->whereNotNull('last_stock_sync_at')
                    ->where('last_stock_sync_at', '>=', now()->subHours(24)) // fresh stok kontrolü
                    ->whereNotNull('last_price_sync_at')
                    ->where('last_price_sync_at', '>=', now()->subHours(24)) // P0-2: fresh fiyat zorunlu
                    ->with('channelProduct')
                    ->limit(100)
                    ->get();

                $rankedAlternatives = $alternatives->map(function ($listing) use ($queryTokens) {
                    $product = $listing->channelProduct;
                    $haystack = mb_strtolower(implode(' ', [
                        $product?->title, $product?->category_name, $product?->brand, $product?->stock_code,
                    ]));
                    $score = collect($queryTokens)->sum(fn ($token) => str_contains($haystack, $token) ? 1 : 0);
                    return ['listing' => $listing, 'score' => $score];
                })->filter(fn ($candidate) => $candidate['score'] > 0)
                    ->sortByDesc('score')
                    ->values();

                foreach ($rankedAlternatives as $candidate) {
                    $alt = $candidate['listing'];
                    $prod = $alt->channelProduct;
                    if (!$prod) continue;

                    $fitGuard = $this->fitCompatibilityGuard($body, (array) $prod->raw_payload);
                    if (!$fitGuard['allowed']) {
                        continue;
                    }

                    // P0-2 FIX: Fiyat geçerliliği kontrolü — 0 veya negatif fiyatlar elensin
                    $salePrice = (float)$alt->sale_price;
                    if ($salePrice <= 0) {
                        continue; // Geçersiz fiyat — öneri üretme
                    }

                    $cleanTitle = $redactor->maskPii($prod->title);

                    // P0-2 FIX: Net fiyat cümlesi yerine "fiyat bilgisi güncel" ifadesi
                    // Stale kontrolü zaten yukarıda yapıldı; ancak cümlede fiyatı açıkça
                    // yazarken "güncel liste fiyatı" olduğu belirtilmeli.
                    $recommendations[] = [
                        'type' => 'out_of_stock_alternative',
                        'product_id' => $prod->id,
                        'title' => $cleanTitle,
                        'sale_price' => $salePrice,
                        'currency' => $alt->currency,
                        'stock_quantity' => $alt->stock_quantity,
                        'recommendation_reason' => $fitGuard['reason'] ?: 'Müşteri sorusundaki ürün/kategori özellikleriyle eşleşti.',
                        // P0-2 FIX: "güncel liste fiyatı" — stale guard zaten geçti
                        'suggested_draft' => "Aradığınız ürün şu an stokta kalmamıştır. Alternatif olarak stokta bulunan {$cleanTitle} ürününü inceleyebilirsiniz.",
                        'citation' => "Catalog Alternative: {$cleanTitle} (Kayıt: {$alt->id}, Kod: {$prod->stock_code}, Eşleşme: {$candidate['score']})"
                    ];
                }
            }
        }

        // 4. Limit Recommendations to maximum 3 items to avoid spamming
        $maxSuggestions = max(1, min(5, (int) config('customer-care.sales_max_recommendations', 3)));
        $recommendations = array_slice($recommendations, 0, $maxSuggestions);

        // Audit log (PII-free)
        if (!empty($recommendations)) {
            SupportAgentAction::create([
                'conversation_id' => $conversation->id,
                'action' => 'sales_suggestions_generated',
                'details_json' => [
                    'suggestion_count' => count($recommendations),
                    'types' => collect($recommendations)->pluck('type')->toArray()
                ]
            ]);
        }

        return $recommendations;
    }

    /**
     * P0-3: Verified + fresh signed web chat cart signal kontrolü.
     * Yalnız source_type=web_chat + cart_signal_verified=true + fresh ise değer döner.
     * Diğer tüm durumlarda null döner → cart recovery pasif kalır.
     */
    private function resolveVerifiedCartValue(SupportConversation $conversation, array $ref): ?float
    {
        // Yalnız web_chat kanalında geçerli
        if ($conversation->source_type !== 'web_chat') {
            return null;
        }

        // cart_signal_verified bayrağı WebChatSupportChannelAdapter tarafından set edilmeli
        if (!($ref['cart_signal_verified'] ?? false)) {
            return null;
        }

        // Freshness kontrolü
        $signalAt = $ref['cart_signal_at'] ?? null;
        if (!$signalAt) {
            return null;
        }

        try {
            $signalTime = \Carbon\Carbon::parse($signalAt);
        } catch (\Exception $e) {
            return null;
        }

        if ($signalTime->lt(now()->subMinutes(self::CART_SIGNAL_MAX_AGE_MINUTES))) {
            return null; // Stale cart signal
        }

        $cartValue = (float)($ref['cart_value'] ?? 0);
        if ($cartValue <= 0) {
            return null;
        }

        return $cartValue;
    }

    private function meaningfulTokens(string $text): array
    {
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', mb_strtolower($text)) ?? '';
        $stopWords = ['urun', 'ürün', 'stok', 'bitti', 'tukendi', 'tükendi', 'kalmadi', 'kalmadı', 'baska', 'başka', 'var', 'acaba'];
        return collect(preg_split('/\s+/', trim($text)) ?: [])
            ->filter(fn ($token) => mb_strlen($token) >= 3 && !in_array($token, $stopWords, true))
            ->unique()->values()->all();
    }

    private function isHighRiskAdviceRequest(string $text): bool
    {
        return preg_match('/tedavi|hastalık|hastalik|ilaç|ilac|doktor|hamile|alerji|hukuk|yasal|dava/u', $text) === 1;
    }

    private function fitCompatibilityGuard(string $text, array $productData): array
    {
        $needsSize = preg_match('/\bbeden\b|\bnumara\b|\b(x?s|m|l|xl|xxl)\s*beden\b/u', $text) === 1;
        $needsCompatibility = preg_match('/uyar\s*m[ıi]|uyumlu|compatible|modeline\s*uyar/u', $text) === 1;
        if (!$needsSize && !$needsCompatibility) {
            return ['allowed' => true, 'reason' => null];
        }

        if ($needsSize) {
            $sizes = collect(data_get($productData, 'verified_sizes', []))->map(fn ($v) => mb_strtolower(trim((string) $v)));
            preg_match('/\b(xs|s|m|l|xl|xxl|\d{2})\s*(?:beden|numara)?\b/u', $text, $match);
            $requested = mb_strtolower((string) ($match[1] ?? ''));
            if ($requested === '' || !$sizes->contains($requested)) {
                return ['allowed' => false, 'reason' => 'Beden uygunluğu doğrulanmış varyant verisiyle eşleşmedi.'];
            }
        }

        if ($needsCompatibility) {
            $models = collect(data_get($productData, 'verified_compatible_models', []))->map(fn ($v) => mb_strtolower(trim((string) $v)));
            $matched = $models->first(fn ($model) => $model !== '' && str_contains($text, $model));
            if (!$matched) {
                return ['allowed' => false, 'reason' => 'Uyumluluk doğrulanmış model listesiyle eşleşmedi.'];
            }
        }

        return ['allowed' => true, 'reason' => 'Beden/uyumluluk doğrulanmış katalog varyantıyla eşleşti.'];
    }
}
