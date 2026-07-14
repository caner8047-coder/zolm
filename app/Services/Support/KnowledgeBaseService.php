<?php

namespace App\Services\Support;

use App\Models\WaKnowledgeArticle;
use App\Services\Support\TenantContext;

class KnowledgeBaseService
{
    /**
     * Mağazaya özel yayınlanmış bilgi bankası makalelerini arar.
     */
    public function searchArticles(int $storeId, string $query, ?\App\Models\User $user = null): array
    {
        $user = $user ?? auth()->user();
        if (!$user) {
            $user = TenantContext::getSystemActor();
        }

        TenantContext::enforceStoreAccess($storeId, $user);

        // Validasyon ve Prompt-injection Güvenlik Sınırı (Sanitization & Length Limit)
        $query = trim(strip_tags($query));
        if (mb_strlen($query) > 200) {
            $query = mb_substr($query, 0, 200);
        }

        // Basit prompt injection engelleme kuralları
        $injectionKeywords = [
            'ignore previous', 'system prompt', 'translate to', 'you are now', 'dan mode',
            'talimatları unut', 'ignore all', 'sen artık', 'temsilci rolü', 'sistem ayarı'
        ];
        foreach ($injectionKeywords as $keyword) {
            if (mb_stripos($query, $keyword) !== false) {
                throw new \InvalidArgumentException('Potansiyel prompt injection tespiti nedeniyle işlem engellendi.');
            }
        }

        $words = explode(' ', $query);

        $q = WaKnowledgeArticle::published()
            ->where('store_id', $storeId);

        $q->where(function ($sub) use ($words) {
            foreach ($words as $word) {
                if (trim($word) === '') continue;
                $sub->where(function ($inner) use ($word) {
                    $inner->where('title', 'like', '%' . $word . '%')
                          ->orWhere('content', 'like', '%' . $word . '%');
                });
            }
        });

        return $q->get()->map(function ($article) {
            return [
                'id' => $article->id,
                'title' => $article->title,
                'content' => $article->content,
                'source' => 'knowledge_article:' . $article->id,
            ];
        })->toArray();
    }

    /**
     * Bilgi bankasına yeni makale ekler.
     */
    public function createArticle(int $storeId, string $title, string $content, ?\App\Models\User $user = null, array $metadata = []): \App\Models\WaKnowledgeArticle
    {
        $user = $user ?? auth()->user();
        if (!$user) {
            $user = TenantContext::getSystemActor();
        }
        TenantContext::enforceStoreAccess($storeId, $user);

        $redactor = app(\App\Services\Support\Security\PiiRedactor::class);
        $title = $redactor->maskPii(trim(strip_tags($title)));
        $content = $redactor->maskPii(trim(strip_tags($content)));

        // Prompt injection kontrolü
        $injectionKeywords = [
            'ignore previous', 'system prompt', 'translate to', 'you are now', 'dan mode',
            'talimatları unut', 'ignore all', 'sen artık', 'temsilci rolü', 'sistem ayarı'
        ];
        foreach ($injectionKeywords as $keyword) {
            if (mb_stripos($title, $keyword) !== false || mb_stripos($content, $keyword) !== false) {
                throw new \InvalidArgumentException('Potansiyel prompt injection tespiti nedeniyle işlem engellendi.');
            }
        }

        return \App\Models\WaKnowledgeArticle::create([
            'store_id' => $storeId,
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title),
            'content' => $content,
            'status' => 'published',
            'category' => mb_substr(trim(strip_tags((string) ($metadata['category'] ?? 'general'))), 0, 60),
            'version' => max(1, (int) ($metadata['version'] ?? 1)),
            'effective_from' => now(),
            'effective_until' => $metadata['effective_until'] ?? null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }
}
