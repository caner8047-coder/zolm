<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterProduct;
use App\Models\TrendyolBoosterStockCheck;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TrendyolBoosterStockService
{
    public function __construct(
        protected TrendyolProductPageReader $reader,
        protected TrendyolBoosterActivityLogger $activityLogger,
        protected TrendyolBoosterNotificationService $notificationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{ok: bool, message: string, check: ?TrendyolBoosterStockCheck}
     */
    public function check(int $userId, array $input): array
    {
        $sourceUrl = $this->normalizeUrl((string) ($input['source_url'] ?? ''));
        $sourceHash = hash('sha256', $sourceUrl);
        $page = (array) ($input['page'] ?? []);
        $readerMessage = '';

        if (! $this->hasPageData($page)) {
            $result = $this->reader->fetch($sourceUrl);
            $page = $result['ok'] ? $result['data'] : $page;
            $readerMessage = (string) ($result['message'] ?? '');
        }

        $tracked = TrendyolBoosterProduct::query()
            ->where('user_id', $userId)
            ->where('source_url_hash', $sourceHash)
            ->first();
        $previous = TrendyolBoosterStockCheck::query()
            ->with('sellers')
            ->where('user_id', $userId)
            ->where('source_url_hash', $sourceHash)
            ->where('stock_status', '!=', 'unknown')
            ->latest('checked_at')
            ->first();
        $sellers = $this->normalizeSellers((array) ($input['sellers'] ?? []), $page, $input);
        $totalWasProvided = array_key_exists('total_stock', $input) && $input['total_stock'] !== null && $input['total_stock'] !== '';
        $pageTotalWasProvided = array_key_exists('total_stock', $page) && $page['total_stock'] !== null && $page['total_stock'] !== '';
        $totalStock = $totalWasProvided
            ? max(0, (int) $input['total_stock'])
            : ($pageTotalWasProvided ? max(0, (int) $page['total_stock']) : $sellers->sum('stock'));
        $hasStockSignal = $totalWasProvided
            || $pageTotalWasProvided
            || $sellers->isNotEmpty()
            || ($page['stock_status'] ?? '') === 'out_of_stock';

        if (! $hasStockSignal) {
            return [
                'ok' => false,
                'message' => $this->missingStockMessage($readerMessage),
                'check' => null,
            ];
        }

        $previousTotal = $previous?->total_stock;
        $stockDelta = $previousTotal !== null ? $totalStock - (int) $previousTotal : 0;
        $estimatedSales = $previousTotal !== null ? max(0, (int) $previousTotal - $totalStock) : 0;
        $stockStatus = $this->stockStatus($totalStock, $hasStockSignal);

        $check = TrendyolBoosterStockCheck::query()->create([
            'user_id' => $userId,
            'trendyol_booster_product_id' => $tracked?->id,
            'source_url' => $sourceUrl,
            'source_url_hash' => $sourceHash,
            'trendyol_product_id' => $this->filledText($page['trendyol_product_id'] ?? null, $this->extractProductId($sourceUrl)),
            'barcode' => $this->filledText($input['barcode'] ?? null, $this->filledText($page['barcode'] ?? null, '')),
            'title' => $this->filledText($page['title'] ?? null, $tracked?->title ?: ''),
            'brand' => $this->filledText($page['brand'] ?? null, $tracked?->brand ?: ''),
            'image_url' => $this->filledText($page['image_url'] ?? null, ''),
            'total_stock' => $totalStock,
            'previous_total_stock' => $previousTotal,
            'stock_delta' => $stockDelta,
            'estimated_sales' => $estimatedSales,
            'seller_count' => $sellers->count(),
            'stock_status' => $stockStatus,
            'raw_payload' => [
                'page' => $page,
                'input' => Arr::except($input, ['page', 'sellers']),
            ],
            'checked_at' => now(),
        ]);

        $this->persistSellers($check, $sellers, $previous);

        $this->activityLogger->log(
            $userId,
            'stock_check',
            'Stok Sorgulama',
            $check->title ?: $sourceUrl,
            $sellers->count() . ' satıcıda toplam ' . $totalStock . ' stok okundu.',
            'stok',
            $totalStock,
            ['check_id' => $check->id, 'estimated_sales' => $estimatedSales],
            $tracked?->id,
        );
        $this->notificationService->notifyStockCheck($check->fresh(['trackedProduct']) ?: $check);

        return [
            'ok' => true,
            'message' => $previous
                ? 'Stok sorgusu kaydedildi; önceki sorguyla karşılaştırıldı.'
                : 'İlk stok sorgusu kaydedildi. Sonraki sorgularda satış düşüşü hesaplanacak.',
            'check' => $check->fresh(['sellers']) ?: $check,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(int $userId): array
    {
        $base = TrendyolBoosterStockCheck::query()
            ->where('user_id', $userId)
            ->where('stock_status', '!=', 'unknown');
        $latest = (clone $base)->with('sellers')->latest('checked_at')->limit(10)->get();

        return [
            'total_checks' => (clone $base)->count(),
            'last_total_stock' => (int) ((clone $base)->latest('checked_at')->value('total_stock') ?? 0),
            'estimated_sales' => (int) ((clone $base)->sum('estimated_sales') ?? 0),
            'seller_count' => (int) ($latest->first()?->seller_count ?? 0),
            'latest_checks' => $latest,
        ];
    }

    /**
     * @param  array<int, mixed>  $sellers
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>  $input
     * @return Collection<int, array<string, mixed>>
     */
    protected function normalizeSellers(array $sellers, array $page, array $input): Collection
    {
        if ($sellers === [] && is_array($page['sellers'] ?? null)) {
            $sellers = $page['sellers'];
        }

        $normalized = collect($sellers)
            ->filter(fn (mixed $seller): bool => is_array($seller))
            ->map(function (array $seller): array {
                return [
                    'seller_name' => Str::limit($this->filledText($seller['seller_name'] ?? $seller['name'] ?? null, 'Bilinmeyen satıcı'), 180, ''),
                    'seller_id' => Str::limit($this->filledText($seller['seller_id'] ?? null, ''), 80, ''),
                    'stock' => max(0, (int) ($seller['stock'] ?? 0)),
                    'sale_price' => $this->money($seller['sale_price'] ?? 0),
                    'seller_score' => $seller['seller_score'] ?? null,
                    'shipping_note' => Str::limit($this->filledText($seller['shipping_note'] ?? null, ''), 180, ''),
                ];
            })
            ->values();

        if ($normalized->isNotEmpty()) {
            return $normalized;
        }

        if (array_key_exists('total_stock', $input) && $input['total_stock'] !== null && $input['total_stock'] !== '') {
            return collect([[
                'seller_name' => $this->filledText($input['seller_name'] ?? null, $this->filledText($page['brand'] ?? null, 'Ana satıcı')),
                'seller_id' => '',
                'stock' => max(0, (int) $input['total_stock']),
                'sale_price' => $this->money($page['sale_price'] ?? $input['sale_price'] ?? 0),
                'seller_score' => null,
                'shipping_note' => '',
            ]]);
        }

        return collect();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $sellers
     */
    protected function persistSellers(TrendyolBoosterStockCheck $check, Collection $sellers, ?TrendyolBoosterStockCheck $previous): void
    {
        $previousSellers = $previous?->sellers
            ? $previous->sellers->keyBy(fn ($seller) => $seller->seller_id ?: Str::lower($seller->seller_name))
            : collect();

        foreach ($sellers as $seller) {
            $key = $seller['seller_id'] ?: Str::lower($seller['seller_name']);
            $previousStock = $previousSellers->get($key)?->stock;
            $stockDelta = $previousStock !== null ? (int) $seller['stock'] - (int) $previousStock : 0;

            $check->sellers()->create([
                'user_id' => $check->user_id,
                'seller_name' => $seller['seller_name'],
                'seller_id' => $seller['seller_id'] ?: null,
                'stock' => $seller['stock'],
                'previous_stock' => $previousStock,
                'stock_delta' => $stockDelta,
                'estimated_sales' => $previousStock !== null ? max(0, (int) $previousStock - (int) $seller['stock']) : 0,
                'sale_price' => $seller['sale_price'],
                'seller_score' => is_numeric($seller['seller_score']) ? (float) $seller['seller_score'] : null,
                'shipping_note' => $seller['shipping_note'],
            ]);
        }
    }

    protected function stockStatus(int $stock, bool $hasSignal): string
    {
        if (! $hasSignal) {
            return 'unknown';
        }

        return $stock > 0 ? 'in_stock' : 'out_of_stock';
    }

    protected function normalizeUrl(string $url): string
    {
        $url = trim($url);
        $url = preg_replace('/\s+/u', '', $url) ?: '';

        return Str::limit($url, 1000, '');
    }

    protected function hasPageData(array $page): bool
    {
        return trim((string) ($page['title'] ?? '')) !== ''
            || trim((string) ($page['trendyol_product_id'] ?? '')) !== ''
            || (float) ($page['sale_price'] ?? 0) > 0;
    }

    protected function missingStockMessage(string $readerMessage): string
    {
        $limited = Str::contains(Str::lower($readerMessage), ['erişimi sınırladı', '403', 'okunamadı']);

        return $limited
            ? 'Trendyol stok verisini sunucuya kapattı. Chrome eklentisiyle sorgulayın veya toplam stoku manuel girin; sıfır stok kaydı oluşturulmadı.'
            : 'Bu üründen doğrulanabilir stok adedi alınamadı. Chrome eklentisiyle sorgulayın veya toplam stoku manuel girin; sıfır stok kaydı oluşturulmadı.';
    }

    protected function extractProductId(string $url): string
    {
        return preg_match('/-p-(\d+)/iu', $url, $match) ? (string) $match[1] : '';
    }

    protected function filledText(mixed $value, string $fallback): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? Str::limit($text, 1000, '') : $fallback;
    }

    protected function money(mixed $value): float
    {
        if (is_string($value)) {
            $value = $this->normalizeMoneyString($value);
        }

        return round(max(0, (float) $value), 2);
    }

    protected function normalizeMoneyString(string $value): string
    {
        $value = preg_replace('/[^\d,.\-]/u', '', $value) ?: '0';
        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            return $lastComma > $lastDot
                ? str_replace(',', '.', str_replace('.', '', $value))
                : str_replace(',', '', $value);
        }

        if ($lastComma !== false) {
            return str_replace(',', '.', $value);
        }

        if (substr_count($value, '.') > 1) {
            return str_replace('.', '', $value);
        }

        return $value;
    }
}
