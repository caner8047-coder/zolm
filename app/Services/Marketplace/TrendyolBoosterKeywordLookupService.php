<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterKeywordLookup;
use Illuminate\Support\Str;

class TrendyolBoosterKeywordLookupService
{
    public function __construct(
        protected TrendyolSearchResultReader $searchReader,
        protected TrendyolBoosterActivityLogger $activityLogger,
    ) {
    }

    /**
     * @return array{ok: bool, message: string, lookup: TrendyolBoosterKeywordLookup}
     */
    public function search(int $userId, string $keyword): array
    {
        $keyword = $this->normalizeKeyword($keyword);
        $result = $this->searchReader->fetch($keyword);
        $data = $result['data'];

        $lookup = TrendyolBoosterKeywordLookup::query()->create([
            'user_id' => $userId,
            'keyword' => $keyword,
            'keyword_hash' => hash('sha256', Str::lower($keyword)),
            'source_url' => $data['source_url'] ?? null,
            'result_count' => (int) ($data['result_count'] ?? 0),
            'top_products' => array_values((array) ($data['top_products'] ?? [])),
            'raw_payload' => $data,
            'searched_at' => now(),
        ]);

        $this->activityLogger->log(
            $userId,
            'keyword_lookup',
            'Anahtar Kelime Aratma',
            $keyword,
            (int) $lookup->result_count . ' ürün sonucu okundu.',
            'sonuç',
            $lookup->result_count,
            ['lookup_id' => $lookup->id, 'ok' => $result['ok']],
        );

        return [
            'ok' => $result['ok'],
            'message' => $result['ok'] ? 'Anahtar kelime araması kaydedildi.' : $result['message'],
            'lookup' => $lookup,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(int $userId): array
    {
        $base = TrendyolBoosterKeywordLookup::query()->where('user_id', $userId);

        return [
            'total' => (clone $base)->count(),
            'last_result_count' => (int) ((clone $base)->latest('searched_at')->value('result_count') ?? 0),
            'latest' => (clone $base)->latest('searched_at')->limit(10)->get(),
            'unique_keywords' => (clone $base)->distinct('keyword_hash')->count('keyword_hash'),
        ];
    }

    protected function normalizeKeyword(string $keyword): string
    {
        $keyword = html_entity_decode(strip_tags($keyword), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $keyword = preg_replace('/\s+/u', ' ', $keyword) ?: '';

        return trim(Str::limit($keyword, 180, ''));
    }
}
