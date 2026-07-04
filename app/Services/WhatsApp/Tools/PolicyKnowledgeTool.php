<?php

namespace App\Services\WhatsApp\Tools;

use App\Models\WaKnowledgeArticle;

class PolicyKnowledgeTool implements AiTool
{
    public function name(): string { return 'policy_knowledge'; }

    public function description(): string
    {
        return 'Teslimat, iade, değişim, garanti, ödeme politikası ve SSS bilgilerini döndürür.';
    }

    public function execute(array $params, int $storeId, ?int $contactId = null): array
    {
        $query = $params['query'] ?? '';
        $category = $params['category'] ?? null;

        $articlesQuery = WaKnowledgeArticle::published();

        if ($storeId) {
            $articlesQuery->where(function ($q) use ($storeId) {
                $q->where('store_id', $storeId)->orWhereNull('store_id');
            });
        }

        if ($category) {
            $articlesQuery->where('category', $category);
        }

        if ($query) {
            $articlesQuery->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('content', 'like', "%{$query}%");
            });
        }

        $articles = $articlesQuery->limit(3)->get();

        if ($articles->isEmpty()) {
            return ['found' => false, 'message' => 'İlgili bilgi bulunamadı.'];
        }

        $results = $articles->map(function ($article) {
            return [
                'title' => $article->title,
                'category' => $article->category,
                'content' => substr($article->content, 0, 500),
            ];
        })->toArray();

        return ['found' => true, 'articles' => $results];
    }
}
