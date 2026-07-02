<?php

namespace App\Http\Controllers;

use App\Models\TrendyolBoosterProduct;
use App\Services\Marketplace\TrendyolBoosterAnalysisService;
use App\Services\Marketplace\TrendyolBoosterProductAnalysisService;
use App\Services\Marketplace\TrendyolBoosterStockService;
use App\Services\Marketplace\TrendyolBoosterStoreWatchService;
use App\Services\Marketplace\TrendyolBoosterSupplierResearchService;
use App\Services\Marketplace\TrendyolProductPageReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TrendyolBoosterCompanionController extends Controller
{
    public function __construct(
        protected TrendyolBoosterAnalysisService $analysisService,
        protected TrendyolBoosterProductAnalysisService $productAnalysisService,
        protected TrendyolBoosterStockService $stockService,
        protected TrendyolBoosterStoreWatchService $storeWatchService,
        protected TrendyolBoosterSupplierResearchService $supplierResearchService,
        protected TrendyolProductPageReader $reader,
    ) {}

    public function session(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'ok' => true,
            'authenticated' => true,
            'user' => [
                'id' => $user?->id,
                'name' => $user?->name,
                'email' => $user?->email,
            ],
            'csrf_token' => csrf_token(),
            'endpoints' => [
                'preview' => route('mp.trendyol-booster.companion.preview'),
                'product_analysis' => route('mp.trendyol-booster.companion.product-analysis'),
                'track' => route('mp.trendyol-booster.companion.track'),
                'status' => route('mp.trendyol-booster.companion.status'),
                'stock_check' => route('mp.trendyol-booster.companion.stock-check'),
                'store_scan' => route('mp.trendyol-booster.companion.store-scan'),
                'market_research' => route('mp.trendyol-booster.companion.market-research'),
                'pending_jobs' => route('mp.trendyol-booster.companion.pending-jobs'),
                'dashboard' => route('mp.trendyol-booster'),
            ],
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $input = $this->companionInput($request);
        $preview = $this->analysisService->preview($input);

        return response()->json([
            'ok' => true,
            'mode' => 'preview',
            'decision' => [
                'status' => $preview['decision'],
                'label' => $this->decisionLabel((string) $preview['decision']),
                'score' => (int) $preview['score'],
                'reasons' => $preview['reasons'],
            ],
            'metrics' => $this->metrics($preview['simulation']),
            'normalized' => $preview['normalized'],
            'dashboard_url' => route('mp.trendyol-booster'),
        ]);
    }

    public function pendingJobs(): JsonResponse
    {
        $userId = Auth::id();
        
        $dueKeywords = \App\Models\TrendyolBoosterKeyword::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('last_checked_at')
                      ->orWhere('last_checked_at', '<=', now()->subHours(12));
            })
            ->limit(5)
            ->get(['id', 'keyword', 'search_url']);

        $dueStores = \App\Models\TrendyolBoosterStoreWatch::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('last_checked_at')
                      ->orWhere('last_checked_at', '<=', now()->subHours(12));
            })
            ->limit(3)
            ->get(['id', 'store_url']);

        return response()->json([
            'ok' => true,
            'jobs' => [
                'keywords' => $dueKeywords,
                'stores' => $dueStores,
            ],
        ]);
    }

    public function productAnalysis(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_url' => ['required', 'string', 'max:1000', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $this->isValidTrendyolUrl((string) $value)) {
                    $fail('Geçerli bir Trendyol ürün linki girin.');
                }
            }],
            'page' => ['required', 'array'],
            'page.trendyol_product_id' => ['required', 'string', 'max:80'],
            'page.title' => ['required', 'string', 'max:500'],
            'page.brand' => ['nullable', 'string', 'max:120'],
            'page.category_name' => ['nullable', 'string', 'max:180'],
            'page.sale_price' => ['required', 'numeric', 'min:0'],
            'page.currency' => ['nullable', 'string', 'max:8'],
            'page.image_url' => ['nullable', 'string', 'max:1000'],
            'page.availability' => ['nullable', 'string', 'max:120'],
            'page.stock_status' => ['nullable', 'string', 'max:40'],
            'page.total_stock' => ['nullable', 'integer', 'min:0'],
            'page.sellers' => ['nullable', 'array', 'max:20'],
            'page.sellers.*.seller_name' => ['nullable', 'string', 'max:180'],
            'page.sellers.*.seller_id' => ['nullable', 'string', 'max:80'],
            'page.sellers.*.stock' => ['nullable', 'integer', 'min:0'],
            'page.sellers.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'page.sellers.*.seller_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'page.sellers.*.shipping_note' => ['nullable', 'string', 'max:180'],
            'page.question_count' => ['nullable', 'integer', 'min:0'],
            'page.category_rank' => ['nullable', 'integer', 'min:0'],
            'page.seller_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'page.seller_id' => ['nullable', 'string', 'max:80'],
            'page.seller_level' => ['nullable', 'integer', 'min:1', 'max:5'],
            'page.seller_follower_count' => ['nullable', 'integer', 'min:0'],
            'page.campaign_count' => ['nullable', 'integer', 'min:0'],
            'page.campaign_signature' => ['nullable', 'string', 'max:500'],
            'page.promotions' => ['nullable', 'array', 'max:20'],
            'page.promotions.*' => ['string', 'max:500'],
            'page.favorite_precision' => ['nullable', 'in:exact,rounded,unknown'],
            'page.listing_id' => ['nullable', 'string', 'max:120'],
            'page.item_number' => ['nullable', 'string', 'max:120'],
            'page.product_group_id' => ['nullable', 'string', 'max:120'],
            'page.product_code' => ['nullable', 'string', 'max:120'],
            'page.max_installment' => ['nullable', 'integer', 'min:0'],
            'page.max_sale_limit' => ['nullable', 'integer', 'min:0'],
            'page.rush_delivery_duration' => ['nullable', 'integer', 'min:0'],
            'page.image_count' => ['nullable', 'integer', 'min:0'],
            'page.attributes' => ['nullable', 'array', 'max:60'],
            'page.attributes.*.name' => ['nullable', 'string', 'max:180'],
            'page.attributes.*.value' => ['nullable', 'string', 'max:500'],
            'page.data_sources' => ['nullable', 'array', 'max:10'],
            'page.data_sources.*' => ['string', 'max:80'],
            'metrics' => ['nullable', 'array'],
            'metrics.evaluation_count' => ['nullable', 'integer', 'min:0'],
            'metrics.review_count' => ['nullable', 'integer', 'min:0'],
            'metrics.average_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'metrics.favorite_count' => ['nullable', 'integer', 'min:0'],
            'metrics.favorite_precision' => ['nullable', 'in:exact,rounded,unknown'],
            'metrics.basket_count' => ['nullable', 'integer', 'min:0'],
            'metrics.view_count_24h' => ['nullable', 'integer', 'min:0'],
            'metrics.question_count' => ['nullable', 'integer', 'min:0'],
            'metrics.category_rank' => ['nullable', 'integer', 'min:0'],
            'metrics.seller_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'metrics.seller_follower_count' => ['nullable', 'integer', 'min:0'],
            'metrics.campaign_count' => ['nullable', 'integer', 'min:0'],
            'recent_reviews' => ['nullable', 'array', 'max:10'],
            'recent_reviews.*.review_id' => ['nullable', 'string', 'max:80'],
            'recent_reviews.*.user_name' => ['nullable', 'string', 'max:180'],
            'recent_reviews.*.rate' => ['nullable', 'integer', 'min:0', 'max:5'],
            'recent_reviews.*.comment' => ['required', 'string', 'max:2000'],
            'recent_reviews.*.seller_name' => ['nullable', 'string', 'max:180'],
            'recent_reviews.*.reviewed_at' => ['nullable'],
        ]);

        if ((float) data_get($validated, 'page.sale_price', 0) <= 0) {
            return response()->json([
                'ok' => false,
                'mode' => 'product_analysis',
                'message' => 'Ürün fiyatı Chrome Companion tarafından okunamadı. Trendyol ürün sekmesini yenileyip tekrar analiz edin.',
            ], 422);
        }

        $result = $this->productAnalysisService->store($this->userId(), $validated);
        if ($result['product']->wasRecentlyCreated) {
            $result['product']->forceFill([
                'tracking_status' => 'candidate',
                'analysis_auto_refresh_enabled' => false,
                'next_analysis_refresh_at' => null,
                'tracking_sources' => ['product_analysis'],
            ])->save();
        }

        return response()->json([
            'ok' => true,
            'mode' => 'product_analysis',
            'message' => 'Ürün analizi kaydedildi. Sonraki taramada değişimler karşılaştırılacak.',
            'analysis' => $result['analysis'],
            'dashboard_url' => route('mp.trendyol-booster'),
        ]);
    }

    public function track(Request $request): JsonResponse
    {
        $trackingPayload = $this->trackingAnalysisPayload($request);

        if ($trackingPayload !== null) {
            $analysisResult = $this->productAnalysisService->store($this->userId(), $trackingPayload, 'browser_companion');
            $wasRecentlyCreated = $analysisResult['product']->wasRecentlyCreated;
            $tracked = $this->analysisService->store($this->userId(), $this->companionInput($request));
        } else {
            $input = $this->companionInput($request);
            $tracked = $this->analysisService->store($this->userId(), $input);
            $wasRecentlyCreated = $tracked->wasRecentlyCreated;
        }

        $this->activateTracking($tracked, 'chrome_extension');

        return response()->json([
            'ok' => true,
            'mode' => 'track',
            'message' => $wasRecentlyCreated ? 'Booster takibine eklendi.' : 'Booster analizi güncellendi.',
            'tracked_product_id' => $tracked->id,
            'decision' => [
                'status' => $tracked->decision_status,
                'label' => $this->decisionLabel((string) $tracked->decision_status),
                'score' => (int) $tracked->opportunity_score,
                'reasons' => $tracked->decision_reasons ?: [],
            ],
            'metrics' => [
                'sale_price' => (float) $tracked->sale_price,
                'net_profit' => (float) $tracked->net_profit,
                'profit_margin_percent' => (float) $tracked->profit_margin_percent,
                'break_even_price' => $tracked->break_even_price !== null ? (float) $tracked->break_even_price : null,
                'target_price' => $tracked->target_price !== null ? (float) $tracked->target_price : null,
            ],
            'dashboard_url' => route('mp.trendyol-booster'),
        ]);
    }

    /** @return array<string, mixed>|null */
    protected function trackingAnalysisPayload(Request $request): ?array
    {
        if (! $request->filled('page.trendyol_product_id')) {
            return null;
        }

        return $request->validate([
            'source_url' => ['required', 'string', 'max:1000', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $this->isValidTrendyolUrl((string) $value)) {
                    $fail('Geçerli bir Trendyol ürün linki girin.');
                }
            }],
            'page' => ['required', 'array'],
            'page.trendyol_product_id' => ['required', 'string', 'max:80'],
            'page.title' => ['required', 'string', 'max:500'],
            'page.brand' => ['nullable', 'string', 'max:120'],
            'page.category_name' => ['nullable', 'string', 'max:180'],
            'page.sale_price' => ['required', 'numeric', 'min:0'],
            'page.currency' => ['nullable', 'string', 'max:8'],
            'page.image_url' => ['nullable', 'string', 'max:1000'],
            'page.availability' => ['nullable', 'string', 'max:120'],
            'page.stock_status' => ['nullable', 'string', 'max:40'],
            'page.total_stock' => ['nullable', 'integer', 'min:0'],
            'page.sellers' => ['nullable', 'array', 'max:20'],
            'page.sellers.*.seller_name' => ['nullable', 'string', 'max:180'],
            'page.sellers.*.seller_id' => ['nullable', 'string', 'max:80'],
            'page.sellers.*.stock' => ['nullable', 'integer', 'min:0'],
            'page.sellers.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'page.sellers.*.seller_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'page.sellers.*.shipping_note' => ['nullable', 'string', 'max:180'],
            'page.question_count' => ['nullable', 'integer', 'min:0'],
            'page.category_rank' => ['nullable', 'integer', 'min:0'],
            'page.seller_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'page.seller_id' => ['nullable', 'string', 'max:80'],
            'page.seller_follower_count' => ['nullable', 'integer', 'min:0'],
            'page.campaign_count' => ['nullable', 'integer', 'min:0'],
            'page.campaign_signature' => ['nullable', 'string', 'max:500'],
            'page.promotions' => ['nullable', 'array', 'max:20'],
            'page.promotions.*' => ['string', 'max:500'],
            'page.favorite_precision' => ['nullable', 'in:exact,rounded,unknown'],
            'page.data_sources' => ['nullable', 'array', 'max:10'],
            'page.data_sources.*' => ['string', 'max:80'],
            'metrics' => ['nullable', 'array'],
            'metrics.evaluation_count' => ['nullable', 'integer', 'min:0'],
            'metrics.review_count' => ['nullable', 'integer', 'min:0'],
            'metrics.average_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'metrics.favorite_count' => ['nullable', 'integer', 'min:0'],
            'metrics.favorite_precision' => ['nullable', 'in:exact,rounded,unknown'],
            'metrics.basket_count' => ['nullable', 'integer', 'min:0'],
            'metrics.view_count_24h' => ['nullable', 'integer', 'min:0'],
            'metrics.question_count' => ['nullable', 'integer', 'min:0'],
            'metrics.category_rank' => ['nullable', 'integer', 'min:0'],
            'metrics.seller_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'metrics.seller_follower_count' => ['nullable', 'integer', 'min:0'],
            'metrics.campaign_count' => ['nullable', 'integer', 'min:0'],
            'recent_reviews' => ['nullable', 'array', 'max:10'],
            'recent_reviews.*.review_id' => ['nullable', 'string', 'max:80'],
            'recent_reviews.*.user_name' => ['nullable', 'string', 'max:180'],
            'recent_reviews.*.rate' => ['nullable', 'integer', 'min:0', 'max:5'],
            'recent_reviews.*.comment' => ['required', 'string', 'max:2000'],
            'recent_reviews.*.seller_name' => ['nullable', 'string', 'max:180'],
            'recent_reviews.*.reviewed_at' => ['nullable'],
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'string', 'max:80'],
        ]);

        $tracked = TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->where('trendyol_product_id', $validated['product_id'])
            ->with('latestSnapshot')
            ->latest('updated_at')
            ->first();
        $snapshot = $tracked?->latestSnapshot;

        return response()->json([
            'ok' => true,
            'tracked' => $tracked !== null && $tracked->tracking_status === 'active',
            'product' => $tracked ? [
                'id' => $tracked->id,
                'tracking_status' => $tracked->tracking_status,
                'last_checked_at' => $tracked->last_checked_at?->toIso8601String(),
                'next_refresh_at' => $tracked->next_analysis_refresh_at?->toIso8601String(),
                'price' => (float) $tracked->sale_price,
                'estimated_daily_sales' => $tracked->estimated_daily_sales !== null ? (float) $tracked->estimated_daily_sales : null,
                'estimated_daily_revenue' => $tracked->estimated_daily_revenue !== null ? (float) $tracked->estimated_daily_revenue : null,
                'interest_score' => (int) $tracked->interest_score,
                'competition_score' => (int) $tracked->competition_score,
                'risk_score' => (int) $tracked->risk_score,
                'confidence_score' => (int) ($snapshot?->confidence_score ?? 0),
                'sales_estimate' => (array) data_get($snapshot?->metrics_json, 'sales_estimate', []),
                'stock_quantity' => $snapshot?->stock_quantity,
                'estimated_days_of_stock' => $snapshot?->estimated_days_of_stock !== null ? (float) $snapshot->estimated_days_of_stock : null,
                'stock_drop_24h' => data_get($snapshot?->metrics_json, 'stock_velocity_24h.observed_drop_units'),
                'stock_observed_hours' => data_get($snapshot?->metrics_json, 'stock_velocity_24h.observed_hours'),
                'stock_sample_count' => (int) data_get($snapshot?->metrics_json, 'stock_velocity_24h.sample_count', 0),
                'favorite_count' => $snapshot?->favorite_count,
                'favorite_delta' => data_get($snapshot?->metrics_json, 'deltas.favorite'),
                'price_delta' => $snapshot?->price_delta !== null ? (float) $snapshot->price_delta : null,
                'category_rank' => $snapshot?->category_rank,
            ] : null,
            'dashboard_url' => route('mp.trendyol-booster', ['booster' => 'tracking']),
        ]);
    }

    public function stockCheck(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_url' => ['required', 'string', 'max:1000', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $this->isValidTrendyolUrl((string) $value)) {
                    $fail('Geçerli bir Trendyol ürün linki girin.');
                }
            }],
            'page' => ['nullable', 'array'],
            'page.trendyol_product_id' => ['nullable', 'string', 'max:80'],
            'page.title' => ['nullable', 'string', 'max:500'],
            'page.brand' => ['nullable', 'string', 'max:120'],
            'page.image_url' => ['nullable', 'string', 'max:1000'],
            'page.sale_price' => ['nullable', 'numeric', 'min:0'],
            'barcode' => ['nullable', 'string', 'max:120'],
            'total_stock' => ['nullable', 'integer', 'min:0'],
            'sellers' => ['nullable', 'array'],
            'sellers.*.seller_name' => ['nullable', 'string', 'max:1000'],
            'sellers.*.seller_id' => ['nullable', 'string', 'max:1000'],
            'sellers.*.stock' => ['nullable', 'integer', 'min:0'],
            'sellers.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'sellers.*.seller_score' => ['nullable', 'numeric', 'min:0'],
            'sellers.*.shipping_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $this->stockService->check($this->userId(), [
            'source_url' => $validated['source_url'],
            'page' => $validated['page'] ?? [],
            'barcode' => $validated['barcode'] ?? null,
            'total_stock' => $validated['total_stock'] ?? null,
            'sellers' => $validated['sellers'] ?? [],
        ]);

        $check = $result['check'];

        return response()->json([
            'ok' => $result['ok'],
            'mode' => 'stock_check',
            'message' => $result['message'],
            'stock' => $check ? [
                'check_id' => $check->id,
                'total_stock' => (int) $check->total_stock,
                'previous_total_stock' => $check->previous_total_stock !== null ? (int) $check->previous_total_stock : null,
                'estimated_sales' => (int) $check->estimated_sales,
                'seller_count' => (int) $check->seller_count,
            ] : null,
            'dashboard_url' => route('mp.trendyol-booster'),
        ], $result['ok'] ? 200 : 422);
    }

    public function storeScan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_url' => ['required', 'string', 'max:1000', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $this->isValidTrendyolUrl((string) $value)) {
                    $fail('Geçerli bir Trendyol mağaza veya ürün linki girin.');
                }
            }],
            'store' => ['nullable', 'array'],
            'store.store_id' => ['nullable', 'string', 'max:80'],
            'store.store_name' => ['nullable', 'string', 'max:180'],
            'store.total_products' => ['nullable', 'integer', 'min:0'],
            'store.source_product_url' => ['nullable', 'string', 'max:1000'],
            'store.resolved_from_product' => ['nullable', 'boolean'],
            'store.seller' => ['nullable', 'array'],
            'store.seller.title' => ['nullable', 'string', 'max:1000'],
            'store.seller.address' => ['nullable', 'string', 'max:1000'],
            'store.seller.kep' => ['nullable', 'string', 'max:255'],
            'store.seller.tax_number' => ['nullable', 'string', 'max:120'],
            'store.seller.tax_office' => ['nullable', 'string', 'max:255'],
            'store.seller.phone' => ['nullable', 'string', 'max:120'],
            'store.seller_title' => ['nullable', 'string', 'max:1000'],
            'store.address' => ['nullable', 'string', 'max:1000'],
            'store.kep' => ['nullable', 'string', 'max:255'],
            'store.tax_number' => ['nullable', 'string', 'max:120'],
            'store.tax_office' => ['nullable', 'string', 'max:255'],
            'store.phone' => ['nullable', 'string', 'max:120'],
            'store.product_preview' => ['nullable', 'array'],
            'store.product_preview.trendyol_product_id' => ['nullable', 'string', 'max:80'],
            'store.product_preview.source_url' => ['nullable', 'string', 'max:1000'],
            'store.product_preview.title' => ['nullable', 'string', 'max:500'],
            'store.product_preview.brand' => ['nullable', 'string', 'max:120'],
            'store.product_preview.category_name' => ['nullable', 'string', 'max:180'],
            'store.product_preview.image_url' => ['nullable', 'string', 'max:1000'],
            'store.product_preview.sale_price' => ['nullable', 'numeric', 'min:0'],
            'store.product_preview.total_stock' => ['nullable', 'integer', 'min:0'],
            'store.product_preview.stock_quantity' => ['nullable', 'integer', 'min:0'],
            'store.product_preview.stock_status' => ['nullable', 'string', 'max:80'],
            'store.product_preview.seller_id' => ['nullable', 'string', 'max:80'],
            'store.product_preview.seller_name' => ['nullable', 'string', 'max:180'],
            'store.items' => ['nullable', 'array'],
            'store.items.*.trendyol_product_id' => ['nullable', 'string', 'max:80'],
            'store.items.*.source_url' => ['nullable', 'string', 'max:1000'],
            'store.items.*.title' => ['nullable', 'string', 'max:500'],
            'store.items.*.brand' => ['nullable', 'string', 'max:120'],
            'store.items.*.category_name' => ['nullable', 'string', 'max:180'],
            'store.items.*.image_url' => ['nullable', 'string', 'max:1000'],
            'store.items.*.total_stock' => ['nullable', 'integer', 'min:0'],
            'store.items.*.stock_status' => ['nullable', 'string', 'max:80'],
            'store.items.*.seller_id' => ['nullable', 'string', 'max:80'],
            'store.items.*.seller_name' => ['nullable', 'string', 'max:180'],
            'store.items.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'store.items.*.stock_quantity' => ['nullable', 'integer', 'min:0'],
            'store.items.*.original_price' => ['nullable', 'numeric', 'min:0'],
            'store.items.*.discount_rate' => ['nullable', 'numeric', 'min:0'],
            'store.items.*.rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'store.items.*.review_count' => ['nullable', 'integer', 'min:0'],
            'store.items.*.favorite_count' => ['nullable', 'integer', 'min:0'],
            'store.items.*.favorite_precision' => ['nullable', 'string', 'max:40'],
            'store.items.*.campaign_badges' => ['nullable', 'array', 'max:20'],
            'store.items.*.campaign_badges.*' => ['string', 'max:120'],
            'store.items.*.is_first_seller' => ['nullable', 'boolean'],
            'store.items.*.enrichment_status' => ['nullable', 'string', 'max:40'],
            'store.items.*.enrichment_source' => ['nullable', 'string', 'max:40'],
            'store.items.*.enrichment_checked_at' => ['nullable', 'string', 'max:80'],
        ]);

        $result = $this->storeWatchService->scan($this->userId(), $validated['store_url'], $validated['store'] ?? []);

        return response()->json([
            'ok' => $result['ok'],
            'mode' => 'store_scan',
            'message' => $result['message'],
            'store' => [
                'watch_id' => $result['watch']->id,
                'store_name' => $result['watch']->store_name,
                'total_products' => (int) $result['watch']->total_products,
                'new_product_count' => (int) $result['watch']->new_product_count,
                'price_change_count' => (int) $result['watch']->price_change_count,
            ],
            'dashboard_url' => route('mp.trendyol-booster'),
        ]);
    }

    public function marketResearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_url' => ['required', 'string', 'max:1000', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $this->isValidTrendyolUrl((string) $value)) {
                    $fail('Geçerli bir Trendyol ürün linki girin.');
                }
            }],
            'page' => ['required', 'array'],
            'page.trendyol_product_id' => ['required', 'string', 'max:80'],
            'page.title' => ['required', 'string', 'max:500'],
            'page.brand' => ['nullable', 'string', 'max:120'],
            'page.category_name' => ['nullable', 'string', 'max:180'],
            'page.image_url' => ['nullable', 'string', 'max:1000'],
            'page.sale_price' => ['nullable', 'numeric', 'min:0'],
            'page.currency' => ['nullable', 'string', 'max:8'],
            'page.stock_status' => ['nullable', 'string', 'max:40'],
            'page.total_stock' => ['nullable', 'integer', 'min:0'],
            'page.favorite_count' => ['nullable', 'integer', 'min:0'],
            'page.review_count' => ['nullable', 'integer', 'min:0'],
            'page.average_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'page.seller_id' => ['nullable', 'string', 'max:80'],
            'page.seller_name' => ['nullable', 'string', 'max:180'],
            'page.sellers' => ['nullable', 'array', 'max:20'],
            'page.sellers.*.seller_name' => ['nullable', 'string', 'max:180'],
            'page.sellers.*.seller_id' => ['nullable', 'string', 'max:80'],
            'page.sellers.*.stock' => ['nullable', 'integer', 'min:0'],
            'page.sellers.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'page.sellers.*.seller_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'page.sellers.*.shipping_note' => ['nullable', 'string', 'max:180'],
            'offers' => ['nullable', 'array', 'max:50'],
            'offers.*.platform' => ['nullable', 'string', 'max:50'],
            'offers.*.platform_label' => ['nullable', 'string', 'max:100'],
            'offers.*.seller_name' => ['nullable', 'string', 'max:180'],
            'offers.*.seller_id' => ['nullable', 'string', 'max:80'],
            'offers.*.external_product_id' => ['nullable', 'string', 'max:120'],
            'offers.*.title' => ['required', 'string', 'max:500'],
            'offers.*.source_url' => ['required', 'url:http,https', 'max:1000'],
            'offers.*.image_url' => ['nullable', 'url:http,https', 'max:1000'],
            'offers.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'offers.*.stock' => ['nullable', 'integer', 'min:0'],
            'offers.*.availability' => ['nullable', 'string', 'max:40'],
            'offers.*.match_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'offers.*.source_type' => ['nullable', 'string', 'max:40'],
            'offers.*.rank' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offers.*.snippet' => ['nullable', 'string', 'max:1000'],
            'search_query' => ['nullable', 'string', 'max:1000'],
            'search_url' => ['nullable', 'url:http,https', 'max:1000'],
            'searched_platforms' => ['nullable', 'array', 'max:30'],
            'searched_platforms.*' => ['string', 'max:50'],
        ]);

        $page = (array) $validated['page'];
        $page['source_url'] = $validated['source_url'];
        $result = $this->supplierResearchService->capture(
            $this->userId(),
            $validated['source_url'],
            $page,
            (array) ($validated['offers'] ?? []),
            [
                'search_query' => $validated['search_query'] ?? '',
                'search_url' => $validated['search_url'] ?? '',
                'searched_platforms' => $validated['searched_platforms'] ?? [],
                'search_source' => 'browser_bridge',
                'search_message' => count((array) ($validated['offers'] ?? [])).' Google sonucu işlendi.',
            ],
        );
        $research = $result['research'];

        return response()->json([
            'ok' => $result['ok'],
            'mode' => 'market_research',
            'message' => $result['message'],
            'research' => $research->exists ? [
                'research_id' => $research->id,
                'platform_count' => (int) $research->platform_count,
                'seller_count' => (int) $research->seller_count,
                'offer_count' => (int) $research->offer_count,
                'verified_offer_count' => (int) $research->verified_offer_count,
                'market_fit_score' => $research->market_fit_score !== null ? (int) $research->market_fit_score : null,
                'confidence_score' => (int) $research->confidence_score,
            ] : null,
            'dashboard_url' => route('mp.trendyol-booster', ['booster' => 'supplier_finder']),
        ], $result['ok'] ? 200 : 422);
    }

    /**
     * @return array<string, mixed>
     */
    protected function companionInput(Request $request): array
    {
        $validated = $request->validate([
            'source_url' => ['required', 'string', 'max:1000', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $this->isValidTrendyolUrl((string) $value)) {
                    $fail('Geçerli bir Trendyol ürün linki girin.');
                }
            }],
            'page' => ['nullable', 'array'],
            'page.trendyol_product_id' => ['nullable', 'string', 'max:80'],
            'page.title' => ['nullable', 'string', 'max:500'],
            'page.brand' => ['nullable', 'string', 'max:120'],
            'page.category_name' => ['nullable', 'string', 'max:180'],
            'page.sale_price' => ['nullable', 'numeric', 'min:0'],
            'page.currency' => ['nullable', 'string', 'max:8'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'mp_product_id' => ['nullable', 'integer', 'min:1'],
            'channel_listing_id' => ['nullable', 'integer', 'min:1'],
            'cogs' => ['nullable', 'numeric', 'min:0'],
            'packaging_cost' => ['nullable', 'numeric', 'min:0'],
            'cargo_cost' => ['nullable', 'numeric', 'min:0'],
            'return_cargo_cost' => ['nullable', 'numeric', 'min:0'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'service_fee_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'advertising_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'return_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'target_margin_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'watch_price' => ['nullable', 'boolean'],
            'watch_stock' => ['nullable', 'boolean'],
            'watch_keyword' => ['nullable', 'boolean'],
        ]);

        $sourceUrl = $this->normalizeUrl((string) $validated['source_url']);
        $page = (array) ($validated['page'] ?? []);

        if (! $this->hasPageData($page)) {
            $readerResult = $this->reader->fetch($sourceUrl);
            $page = $readerResult['ok'] ? $readerResult['data'] : $page;
        }

        return [
            'user_id' => $this->userId(),
            'source_url' => $sourceUrl,
            'trendyol_product_id' => $page['trendyol_product_id'] ?? null,
            'mp_product_id' => $validated['mp_product_id'] ?? null,
            'channel_listing_id' => $validated['channel_listing_id'] ?? null,
            'title' => $page['title'] ?? '',
            'brand' => $page['brand'] ?? '',
            'category_name' => $page['category_name'] ?? '',
            'sale_price' => $validated['sale_price'] ?? $page['sale_price'] ?? 0,
            'cogs' => $validated['cogs'] ?? null,
            'packaging_cost' => $validated['packaging_cost'] ?? null,
            'cargo_cost' => $validated['cargo_cost'] ?? null,
            'return_cargo_cost' => $validated['return_cargo_cost'] ?? ($validated['cargo_cost'] ?? null),
            'commission_rate' => $validated['commission_rate'] ?? null,
            'service_fee_rate' => $validated['service_fee_rate'] ?? null,
            'advertising_rate' => $validated['advertising_rate'] ?? null,
            'return_rate' => $validated['return_rate'] ?? null,
            'target_margin_percent' => $validated['target_margin_percent'] ?? 20,
            'watch_price' => (bool) ($validated['watch_price'] ?? true),
            'watch_stock' => (bool) ($validated['watch_stock'] ?? false),
            'watch_keyword' => (bool) ($validated['watch_keyword'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $simulation
     * @return array<string, mixed>
     */
    protected function metrics(array $simulation): array
    {
        return [
            'sale_price' => (float) $simulation['sale_price'],
            'net_profit' => (float) $simulation['net_profit'],
            'profit_margin_percent' => (float) $simulation['profit_margin_percent'],
            'break_even_price' => $simulation['break_even_price'] !== null ? (float) $simulation['break_even_price'] : null,
            'target_price' => $simulation['target_price'] !== null ? (float) $simulation['target_price'] : null,
            'total_deductions' => (float) data_get($simulation, 'breakdown.total_deductions', 0),
        ];
    }

    protected function hasPageData(array $page): bool
    {
        return trim((string) ($page['title'] ?? '')) !== ''
            || (float) ($page['sale_price'] ?? 0) > 0
            || trim((string) ($page['trendyol_product_id'] ?? '')) !== '';
    }

    protected function isValidTrendyolUrl(string $url): bool
    {
        $url = $this->normalizeUrl($url);

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            return false;
        }

        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'ty.gl'
            || Str::endsWith($host, '.ty.gl')
            || $host === 'trendyol.com'
            || Str::endsWith($host, '.trendyol.com');
    }

    protected function normalizeUrl(string $url): string
    {
        $url = trim($url);
        $url = preg_replace('/\s+/u', '', $url) ?: '';

        return Str::limit($url, 1000, '');
    }

    protected function decisionLabel(string $decision): string
    {
        return match ($decision) {
            'go' => 'Güçlü fırsat',
            'watch' => 'Takibe al',
            'risk' => 'Dikkat',
            'loss' => 'Zarar riski',
            default => 'İzle',
        };
    }

    protected function activateTracking(TrendyolBoosterProduct $tracked, string $source): void
    {
        $sources = collect((array) $tracked->tracking_sources)
            ->push($source)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $tracked->forceFill([
            'tracking_status' => 'active',
            'tracking_sources' => $sources,
            'tracking_started_at' => $tracked->tracking_started_at ?: now(),
            'tracking_paused_at' => null,
            'watch_stock' => true,
            'analysis_auto_refresh_enabled' => true,
            'analysis_refresh_interval_minutes' => max(60, min(1440, (int) config('marketplace.trendyol_booster.tracking_interval_minutes', 60))),
            'next_analysis_refresh_at' => now(),
        ])->save();
    }

    protected function userId(): int
    {
        return (int) Auth::id();
    }
}
