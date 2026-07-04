<?php

namespace App\Services\WhatsApp;

use App\Models\WaKnowledgeArticle;
use App\Models\WaKnowledgeArticleChunk;
use Illuminate\Support\Facades\Log;

class KnowledgeBaseService
{
    /**
     * Anahtar kelimelerle bilgi ara
     */
    public function search(string $query, ?int $storeId = null, ?string $category = null): array
    {
        $articles = WaKnowledgeArticle::published();

        if ($storeId) {
            $articles->where(function ($q) use ($storeId) {
                $q->where('store_id', $storeId)->orWhereNull('store_id');
            });
        }

        if ($category) {
            $articles->where('category', $category);
        }

        // Basit keyword arama
        $keywords = $this->extractKeywords($query);
        $articles->where(function ($q) use ($keywords) {
            foreach ($keywords as $keyword) {
                $q->orWhere('title', 'like', "%{$keyword}%")
                    ->orWhere('content', 'like', "%{$keyword}%");
            }
        });

        $articles = $articles->limit(5)->get();

        if ($articles->isEmpty()) {
            return [];
        }

        return $articles->map(function ($article) {
            return [
                'id' => $article->id,
                'title' => $article->title,
                'category' => $article->category,
                'content' => mb_substr($article->content, 0, 500),
                'relevance' => $this->calculateRelevance($article, ''),
            ];
        })->sortByDesc('relevance')->toArray();
    }

    /**
     * Makale oluştur veya güncelle
     */
    public function upsertArticle(array $data, ?int $userId = null): WaKnowledgeArticle
    {
        $article = WaKnowledgeArticle::updateOrCreate(
            ['store_id' => $data['store_id'] ?? null, 'slug' => $data['slug']],
            [
                'title' => $data['title'],
                'category' => $data['category'] ?? 'general',
                'content' => $data['content'],
                'status' => $data['status'] ?? 'draft',
                'effective_from' => $data['effective_from'] ?? now(),
                'effective_until' => $data['effective_until'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );

        // Chunk'ları oluştur
        $this->rebuildChunks($article);

        return $article;
    }

    /**
     * Chunk'ları yeniden oluştur
     */
    private function rebuildChunks(WaKnowledgeArticle $article): void
    {
        $article->chunks()->delete();

        $sentences = preg_split('/(?<=[.!?])\s+/', $article->content);
        $chunks = [];
        $currentChunk = '';
        $index = 0;

        foreach ($sentences as $sentence) {
            if (strlen($currentChunk . ' ' . $sentence) > 500) {
                if ($currentChunk !== '') {
                    $chunks[] = ['chunk_index' => $index++, 'content' => trim($currentChunk)];
                    $currentChunk = '';
                }
            }
            $currentChunk .= ($currentChunk ? ' ' : '') . $sentence;
        }

        if ($currentChunk !== '') {
            $chunks[] = ['chunk_index' => $index, 'content' => trim($currentChunk)];
        }

        foreach ($chunks as $chunk) {
            WaKnowledgeArticleChunk::create([
                'article_id' => $article->id,
                'chunk_index' => $chunk['chunk_index'],
                'content' => $chunk['content'],
                'content_hash' => hash('sha256', $chunk['content']),
            ]);
        }
    }

    private function extractKeywords(string $text): array
    {
        $stopWords = ['bir', 've', 'ile', 'için', 'olan', 'mi', 'mı', 'mu', 'mü', 'ne', 'nedir', 'nasıl', 'hangisi', 'hakkında'];
        $words = preg_split('/\s+/', mb_strtolower($text));
        return array_values(array_filter($words, fn ($w) => strlen($w) > 2 && !in_array($w, $stopWords)));
    }

    private function calculateRelevance(WaKnowledgeArticle $article, string $query): int
    {
        $score = 0;
        $keywords = $this->extractKeywords($query);

        foreach ($keywords as $keyword) {
            if (mb_strpos(mb_strtolower($article->title), $keyword) !== false) {
                $score += 10;
            }
            if (mb_strpos(mb_strtolower($article->content), $keyword) !== false) {
                $score += 5;
            }
        }

        return $score;
    }
}
