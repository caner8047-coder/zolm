<?php

namespace App\Services\Support;

use App\Models\WaKnowledgeArticle;
use App\Models\ChannelListing;
use App\Services\Support\Security\PiiRedactor;

class CustomerCareKnowledgeGroundingService
{
    protected array $injectionKeywords = [
        'ignore previous', 'system prompt', 'translate to', 'you are now', 'dan mode',
        'talimatları unut', 'ignore all', 'sen artık', 'temsilci rolü', 'sistem ayarı'
    ];

    /** Max content length allowed per article (chars) */
    private const MAX_ARTICLE_LENGTH = 1500;

    public function containsPromptInjection(string $text): bool
    {
        foreach ($this->injectionKeywords as $keyword) {
            if (mb_stripos($text, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Güvenli ve fresh kaynaklardan bilgi grounding context'i oluşturur.
     */
    public function ground(int $storeId, string $query, string $historyText = ''): array
    {
        $query = trim(strip_tags($query));
        if (mb_strlen($query) > 200) {
            $query = mb_substr($query, 0, 200);
        }

        // Prompt Injection kontrolü
        if ($this->containsPromptInjection($query)) {
            // Güvenli fallback: boş sonuç döner
            return [
                'kb' => '',
                'products' => '',
                'has_stale_data' => false,
                'citations' => []
            ];
        }

        $citations = [];
        $hasStaleData = false;
        $redactor = app(PiiRedactor::class);

        // 1. Bilgi Bankası (Knowledge Base) Arama
        $kbText = "";
        if (!empty($query)) {
            $words = array_filter(explode(' ', $query), fn($w) => mb_strlen(trim($w)) > 2);

            if (config('customer-care.release_center_enabled', false)) {
                $latestVersions = \App\Models\SupportArtifactVersion::where('store_id', $storeId)
                    ->where('artifact_type', 'knowledge_article')
                    ->where('is_current', true)
                    ->get();
                foreach ($latestVersions as $v) {
                    $cJson = $v->content_json;
                    $title = $cJson['title'] ?? 'Bilgi Makalesi';
                    $body = $cJson['text'] ?? $cJson['content'] ?? '';

                    $matches = false;
                    if (!empty($words)) {
                        foreach ($words as $word) {
                            if (mb_stripos($title, $word) !== false || mb_stripos($body, $word) !== false) {
                                $matches = true;
                                break;
                            }
                        }
                    } else {
                        $matches = true;
                    }

                    if ($matches) {
                        $cleanTitle = $redactor->maskPii($title);
                        $cleanContent = $redactor->maskPii($body);

                        if (mb_strlen($cleanContent) > self::MAX_ARTICLE_LENGTH) {
                            $cleanContent = mb_substr($cleanContent, 0, self::MAX_ARTICLE_LENGTH) . '...';
                        }

                        $kbText .= "Başlık: {$cleanTitle}\nİçerik: {$cleanContent}\n\n";
                        $citations[] = [
                            'type' => 'knowledge_article',
                            'name' => $cleanTitle,
                            'record_id' => $v->artifact_id ?? $v->id,
                            'version' => $v->version_number,
                            'freshness_at' => $v->updated_at?->toIso8601String(),
                            'is_stale' => false,
                        ];
                    }
                }
            } else {
                $q = WaKnowledgeArticle::published()->where('store_id', $storeId);

                if (!empty($words)) {
                    $q->where(function ($sub) use ($words) {
                        foreach ($words as $word) {
                            $sub->orWhere('title', 'like', '%' . $word . '%')
                                ->orWhere('content', 'like', '%' . $word . '%');
                        }
                    });
                }

                $articles = $q->limit(3)->get();
                foreach ($articles as $article) {
                // P0-1 FIX: PII redaction ÖNCE, prompt injection filtresi BAĞIMSIZ
                $rawTitle = $article->title;
                $rawContent = $article->content;

                // HTML/XML strip ve kontrol karakter temizliği
                $rawTitle = strip_tags($rawTitle);
                $rawContent = strip_tags($rawContent);
                $rawContent = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $rawContent ?? '');

                // PII maskeleme (merkezi redactor)
                $cleanTitle = $redactor->maskPii($rawTitle);
                $cleanContent = $redactor->maskPii($rawContent);

                // Maksimum uzunluk sınırı
                if (mb_strlen($cleanContent) > self::MAX_ARTICLE_LENGTH) {
                    $cleanContent = mb_substr($cleanContent, 0, self::MAX_ARTICLE_LENGTH) . '...';
                }

                // Prompt injection sanitasyonu (PII redaction'dan BAĞIMSIZ)
                foreach ($this->injectionKeywords as $keyword) {
                    if (mb_stripos($cleanContent, $keyword) !== false) {
                        $cleanContent = '[İçerik güvenlik filtresi nedeniyle gizlendi]';
                        break;
                    }
                }

                $kbText .= "Başlık: {$cleanTitle}\nİçerik: {$cleanContent}\n\n";
                $citations[] = [
                    'type' => 'knowledge_article',
                    'name' => $cleanTitle,
                    'record_id' => $article->id,
                    'version' => $article->version ?? 1,
                    'freshness_at' => $article->updated_at?->toIso8601String(),
                    'is_stale' => false,
                ];
            }
        }
    }

        // 2. Ürün Kataloğu & Stok/Fiyat fresh kontrolü
        $productsText = "";
        $searchText = trim($query . ' ' . $historyText);
        if (!empty($searchText)) {
            $words = array_filter(explode(' ', $searchText), fn($w) => mb_strlen(trim($w)) > 2);
            if (!empty($words)) {
                // Store-scoped ChannelProduct arama
                $listings = ChannelListing::where('store_id', $storeId)
                    ->whereHas('channelProduct', function ($q) use ($words) {
                        $q->where(function ($sub) use ($words) {
                            foreach ($words as $word) {
                                $sub->orWhere('title', 'like', '%' . $word . '%')
                                    ->orWhere('stock_code', 'like', '%' . $word . '%');
                            }
                        });
                    })
                    ->with(['channelProduct', 'product'])
                    ->limit(3)
                    ->get();

                if ($listings->count() > 0) {
                    foreach ($listings as $listing) {
                        $prod = $listing->channelProduct;
                        if (!$prod) continue;

                        // Freshness kontrolü (24 saat sınırı)
                        $staleStock = !$listing->last_stock_sync_at || $listing->last_stock_sync_at->lt(now()->subHours(24));
                        $stalePrice = !$listing->last_price_sync_at || $listing->last_price_sync_at->lt(now()->subHours(24));

                        if ($staleStock || $stalePrice) {
                            $hasStaleData = true;
                        }

                        $cleanTitle = $redactor->maskPii($prod->title);

                        $stockDesc = $staleStock ? "Belirsiz (Güncelleme Bekleniyor)" : $listing->stock_quantity;
                        $priceDesc = $stalePrice ? "Belirsiz (Güncelleme Bekleniyor)" : "{$listing->sale_price} {$listing->currency}";

                        $productsText .= "Ürün Adı: {$cleanTitle}\nStok Kodu: {$prod->stock_code}\nFiyat: {$priceDesc}\nStok: {$stockDesc}\n\n";

                        $citations[] = [
                            'type' => 'product_catalog',
                            'name' => $cleanTitle,
                            'record_id' => $prod->id,
                            'version' => null,
                            'freshness_at' => $listing->last_synced_at?->toIso8601String(),
                            'is_stale' => ($staleStock || $stalePrice),
                        ];
                    }
                }

            }
        }

        return [
            'kb' => trim($kbText),
            'products' => trim($productsText),
            'has_stale_data' => $hasStaleData,
            'citations' => $citations,
        ];
    }
}
