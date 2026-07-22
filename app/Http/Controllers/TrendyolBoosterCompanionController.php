<?php

namespace App\Http\Controllers;

use App\Models\TrendyolBoosterProduct;
use App\Models\TrendyolBoosterReviewSync;
use App\Services\Marketplace\TrendyolBestsellerReportService;
use App\Services\Marketplace\TrendyolBoosterAnalysisService;
use App\Services\Marketplace\TrendyolBoosterProductAnalysisService;
use App\Services\Marketplace\TrendyolBoosterReviewService;
use App\Services\Marketplace\TrendyolBoosterStockService;
use App\Services\Marketplace\TrendyolBoosterStoreWatchService;
use App\Services\Marketplace\TrendyolBoosterSupplierResearchService;
use App\Services\Marketplace\TrendyolProductPageReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TrendyolBoosterCompanionController extends Controller
{
    public function __construct(
        protected TrendyolBoosterAnalysisService $analysisService,
        protected TrendyolBoosterProductAnalysisService $productAnalysisService,
        protected TrendyolBoosterStockService $stockService,
        protected TrendyolBoosterStoreWatchService $storeWatchService,
        protected TrendyolBoosterSupplierResearchService $supplierResearchService,
        protected TrendyolBoosterReviewService $reviewService,
        protected TrendyolProductPageReader $reader,
        protected TrendyolBestsellerReportService $bestsellerReportService,
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
                'bestseller_capture' => route('mp.trendyol-booster.companion.bestseller-capture'),
                'opportunity_scan' => route('mp.trendyol-booster.companion.opportunity-scan'),
                'pending_jobs' => route('mp.trendyol-booster.companion.pending-jobs'),
                'pricing_cost_lookup' => route('mp.trendyol-booster.companion.pricing-cost-lookup'),
                'update_product_cost' => route('mp.trendyol-booster.companion.update-product-cost'),
                'order_profit_lookup' => route('mp.trendyol-booster.companion.order-profit-lookup'),
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
            ->get(['id', 'keyword'])
            ->map(fn ($keyword): array => [
                'id' => $keyword->id,
                'keyword' => $keyword->keyword,
                'search_url' => 'https://www.trendyol.com/sr?q='.rawurlencode((string) $keyword->keyword),
            ]);

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

    public function bestsellerCapture(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:180'],
            'matched_label' => ['nullable', 'string', 'max:180'],
            'source_url' => ['required', 'string', 'max:1000', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $this->isValidTrendyolUrl((string) $value)) {
                    $fail('Geçerli bir Trendyol liste linki gerekir.');
                }
            }],
            'items' => ['required', 'array', 'min:1', 'max:72'],
            'items.*.trendyol_product_id' => ['required', 'regex:/^\d{1,30}$/'],
            'items.*.source_url' => ['required', 'url:http,https', 'max:1000'],
            'items.*.title' => ['required', 'string', 'max:500'],
            'items.*.brand' => ['nullable', 'string', 'max:120'],
            'items.*.image_url' => ['nullable', 'url:http,https', 'max:1000'],
            'items.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'items.*.review_count' => ['nullable', 'integer', 'min:0'],
            'items.*.favorite_count' => ['nullable', 'integer', 'min:0'],
            'items.*.seller_name' => ['nullable', 'string', 'max:180'],
            'items.*.campaign_badges' => ['nullable', 'array', 'max:12'],
            'items.*.campaign_badges.*' => ['string', 'max:180'],
        ]);

        $items = collect($validated['items'])
            ->values()
            ->map(function (array $item, int $index): array {
                return $item + [
                    'rank' => $index + 1,
                    'price' => $item['sale_price'] ?? null,
                    'rating_count' => $item['review_count'] ?? 0,
                    'campaigns' => $item['campaign_badges'] ?? [],
                    'campaign_count' => count((array) ($item['campaign_badges'] ?? [])),
                ];
            })
            ->all();

        $result = $this->bestsellerReportService->storeRun($this->userId(), [
            'query' => trim((string) $validated['query']),
            'matched_label' => trim((string) ($validated['matched_label'] ?? $validated['query'])),
            'source_url' => $this->normalizeUrl((string) $validated['source_url']),
            'source' => 'browser_companion',
        ], $items);

        $report = $result['report'];
        $run = $result['run'];

        return response()->json([
            'ok' => true,
            'mode' => 'bestseller_capture',
            'message' => $result['created']
                ? "{$run->item_count} görünür ürünle ilk pazar ölçümü kaydedildi."
                : "{$run->item_count} görünür ürünle yeni pazar ölçümü kaydedildi.",
            'report_id' => $report->id,
            'run_id' => $run->id,
            'item_count' => (int) $run->item_count,
            'priced_item_count' => (int) $run->priced_item_count,
            'created' => (bool) $result['created'],
            'dashboard_url' => route('mp.trendyol-booster', [
                'booster' => 'bestseller',
                'bestseller_q' => $report->query,
                'bestseller_mode' => 'reports',
                'bestseller_report' => $report->id,
            ]),
        ]);
    }

    public function opportunityScan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:2', 'max:40'],
            'items.*.trendyol_product_id' => ['required', 'regex:/^\d{1,30}$/'],
            'items.*.source_url' => ['required', 'url:http,https', 'max:1000'],
            'items.*.title' => ['required', 'string', 'max:500'],
            'items.*.brand' => ['nullable', 'string', 'max:120'],
            'items.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'items.*.review_count' => ['nullable', 'integer', 'min:0'],
        ]);

        $scan = app(\App\Services\Marketplace\TrendyolBoosterOpportunityScannerService::class)
            ->scan((array) $validated['items']);

        return response()->json([
            'ok' => true,
            'mode' => 'opportunity_scan',
            'message' => $scan['scanned_count'].' görünür ürün fırsat sinyalleriyle sıralandı.',
            'scan' => $scan,
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
        $maxSellers = $this->companionLimit('max_stock_sellers', 30);

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
            'page.total_stock' => ['nullable', 'integer', 'min:0'],
            'page.favorite_count' => ['nullable', 'integer', 'min:0'],
            'page.seller_id' => ['nullable', 'string', 'max:80'],
            'page.seller_name' => ['nullable', 'string', 'max:180'],
            'barcode' => ['nullable', 'string', 'max:120'],
            'total_stock' => ['nullable', 'integer', 'min:0'],
            'sellers' => ['nullable', 'array', 'max:'.$maxSellers],
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
        $maxItems = $this->companionLimit('max_store_items', 200);

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
            'store.items' => ['nullable', 'array', 'max:'.$maxItems],
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

    protected function companionLimit(string $key, int $default): int
    {
        return max(1, (int) config("marketplace.trendyol_booster.companion.{$key}", $default));
    }

    protected function userId(): int
    {
        return (int) Auth::id();
    }

    // ===== Trendyol Yorum Senkronizasyonu =====

    /**
     * Yeni bir yorum tarama çalışması başlatır.
     * Chrome eklentisine bridge event tetikler.
     */
    public function reviewScanStart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sync_run_id' => [
                'nullable',
                'integer',
                Rule::exists('trendyol_booster_review_syncs', 'id')
                    ->where(fn ($query) => $query->where('user_id', $this->userId())),
            ],
            'sync_type' => ['nullable', 'in:full,delta'],
            'review_source_id' => [
                'nullable',
                'integer',
                Rule::exists('trendyol_booster_review_sources', 'id')
                    ->where(fn ($query) => $query->where('user_id', $this->userId())),
            ],
            'total_products' => ['nullable', 'integer', 'min:0'],
        ]);

        $syncType = $validated['sync_type'] ?? 'delta';
        $syncRun = isset($validated['sync_run_id'])
            ? TrendyolBoosterReviewSync::where('user_id', $this->userId())
                ->whereKey($validated['sync_run_id'])
                ->whereIn('status', ['queued', 'running'])
                ->firstOrFail()
            : $this->reviewService->createSyncRun(
                $this->userId(),
                $syncType,
                isset($validated['review_source_id']) ? (int) $validated['review_source_id'] : null,
            );

        if (isset($validated['total_products'])) {
            $syncRun->update(['total_products' => $validated['total_products']]);
        }

        // Eklentiye başlatma bilgisi döner (bridge event için)
        return response()->json([
            'ok' => true,
            'sync_run_id' => $syncRun->id,
            'sync_type' => $syncRun->sync_type,
            'review_source_id' => $syncRun->review_source_id,
            'last_synced_at' => $syncRun->last_synced_at?->toIso8601String(),
            'total_products' => $syncRun->total_products,
            'status_endpoint' => route('mp.trendyol-booster.companion.review-scan.status', $syncRun->id),
            'ingest_endpoint' => route('mp.trendyol-booster.companion.review-scan.ingest'),
        ]);
    }

    /**
     * Chrome eklentisinden gelen batch yorum verisini DB'ye kaydeder.
     */
    public function reviewScanIngest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sync_run_id' => [
                'required',
                'integer',
                Rule::exists('trendyol_booster_review_syncs', 'id')
                    ->where(fn ($query) => $query->where('user_id', $this->userId())),
            ],
            'reviews' => ['present', 'array', 'max:100'],
            'total_products' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'processed_products' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'completed' => ['nullable', 'boolean'],
            'reviews.*.trendyol_product_id' => ['required', 'string', 'max:80'],
            'reviews.*.trendyol_review_id' => ['required', 'string', 'max:80'],
            'reviews.*.trendyol_product_barcode' => ['nullable', 'string', 'max:120'],
            'reviews.*.product_title' => ['nullable', 'string', 'max:500'],
            'reviews.*.product_image_url' => ['nullable', 'string', 'max:1000'],
            'reviews.*.reviewer_name' => ['nullable', 'string', 'max:180'],
            'reviews.*.reviewer_avatar_url' => ['nullable', 'string', 'max:1000'],
            'reviews.*.rating' => ['required', 'integer', 'min:1', 'max:5'],
            'reviews.*.comment' => ['required', 'string', 'max:3000'],
            'reviews.*.review_media' => ['nullable', 'array'],
            'reviews.*.review_media.*.url' => ['nullable', 'string', 'max:1000'],
            'reviews.*.helpful_count' => ['nullable', 'integer', 'min:0'],
            'reviews.*.seller_name' => ['nullable', 'string', 'max:180'],
            'reviews.*.reviewed_at' => ['nullable', 'date'],
        ]);

        $result = $this->reviewService->ingestReviews(
            $this->userId(),
            $validated['reviews'],
            $validated['sync_run_id'],
            [
                'total_products' => $validated['total_products'] ?? null,
                'processed_products' => $validated['processed_products'] ?? null,
            ],
        );

        if (! empty($validated['completed'])) {
            $this->reviewService->completeSyncRun(
                $validated['sync_run_id'],
                null,
                $this->userId(),
            );
        }

        return response()->json([
            'ok' => true,
            'new' => $result['new'],
            'updated' => $result['updated'],
            'spam' => $result['spam'],
            'progress_percent' => $result['progress_percent'],
            'processed_products' => $result['processed_products'],
            'total_products' => $result['total_products'],
            'status' => ! empty($validated['completed']) ? 'completed' : 'running',
        ]);
    }

    /**
     * Senkronizasyon çalışmasının durumunu döner (Livewire polling için).
     */
    public function reviewScanStatus(int $syncRunId): JsonResponse
    {
        $syncRun = TrendyolBoosterReviewSync::where('user_id', $this->userId())
            ->where('id', $syncRunId)
            ->first();

        if (! $syncRun) {
            return response()->json(['ok' => false, 'message' => 'Senkronizasyon bulunamadı.'], 404);
        }

        return response()->json([
            'ok' => true,
            'status' => $syncRun->status,
            'sync_type' => $syncRun->sync_type,
            'progress_percent' => $syncRun->progress_percent,
            'total_products' => $syncRun->total_products,
            'processed_products' => $syncRun->processed_products,
            'total_reviews' => $syncRun->total_reviews,
            'new_reviews' => $syncRun->new_reviews,
            'updated_reviews' => $syncRun->updated_reviews,
            'spam_detected' => $syncRun->spam_detected,
            'started_at' => $syncRun->started_at?->toIso8601String(),
            'completed_at' => $syncRun->completed_at?->toIso8601String(),
            'error_message' => $syncRun->error_message,
        ]);
    }

    /**
     * Trendyol'da silinen/düzenlenen yorumları doğrular (orphan cleanup).
     * Eklenti belirtilen review_id'leri Trendyol'da kontrol eder.
     */
    public function reviewScanVerify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'trendyol_product_id' => ['required', 'string', 'max:80'],
            'checked_review_ids' => ['required', 'array', 'max:100'],
            'checked_review_ids.*' => ['string', 'max:80'],
            'existing_review_ids' => ['present', 'array', 'max:100'],
            'existing_review_ids.*' => ['string', 'max:80'],
        ]);

        $checkedIds = collect($validated['checked_review_ids'])->unique();
        $existingIds = collect($validated['existing_review_ids'])->unique();
        $missingIds = $checkedIds->diff($existingIds)->values();

        $deleted = \App\Models\TrendyolBoosterReview::where('user_id', $this->userId())
            ->where('trendyol_product_id', $validated['trendyol_product_id'])
            ->whereIn('trendyol_review_id', $missingIds)
            ->where('status', '!=', 'deleted')
            ->get();

        $count = 0;
        foreach ($deleted as $review) {
            $review->markDeleted('orphan_cleanup');
            $count++;
        }

        return response()->json([
            'ok' => true,
            'checked' => $checkedIds->count(),
            'verified' => $existingIds->count(),
            'marked_deleted' => $count,
        ]);
    }

    /**
     * Trendyol Seller Panel fiyatlandırma sayfası için toplu maliyet sorgulama.
     * Barkod veya model kodu listesiyle ZOLM'daki ürün maliyetlerini döner.
     * Chrome eklentisi bu endpoint'i kullanarak her ürün satırına karlılık badge'i gösterir.
     */
    public function pricingCostLookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcodes'    => ['nullable', 'array', 'max:100'],
            'barcodes.*'  => ['string', 'max:120'],
            'model_codes' => ['nullable', 'array', 'max:100'],
            'model_codes.*' => ['string', 'max:120'],
            'stock_codes' => ['nullable', 'array', 'max:100'],
            'stock_codes.*' => ['string', 'max:120'],
        ]);

        $userId = $this->userId();
        $barcodes   = array_filter(array_unique($validated['barcodes'] ?? []), fn ($v) => trim($v) !== '');
        $modelCodes = array_filter(array_unique($validated['model_codes'] ?? []), fn ($v) => trim($v) !== '');
        $stockCodes = array_filter(array_unique($validated['stock_codes'] ?? []), fn ($v) => trim($v) !== '');

        if (empty($barcodes) && empty($modelCodes) && empty($stockCodes)) {
            return response()->json([
                'ok' => true,
                'products' => [],
                'matched' => 0,
                'total_requested' => 0,
            ]);
        }

        // Tüm eşleşen ürünleri tek sorguda çek
        $query = \App\Models\MpProduct::where('user_id', $userId);

        $query->where(function ($q) use ($barcodes, $modelCodes, $stockCodes) {
            if (! empty($barcodes)) {
                $q->orWhereIn('barcode', $barcodes);
            }
            if (! empty($modelCodes)) {
                $q->orWhereIn('model_code', $modelCodes);
            }
            if (! empty($stockCodes)) {
                $q->orWhereIn('stock_code', $stockCodes);
            }
        });

        $products = $query->get();

        // Barkod → ürün map'i oluştur (hızlı lookup için)
        $result = [];
        foreach ($products as $product) {
            $salePrice = (float) $product->sale_price;
            $cogs = (float) $product->cogs;
            $packagingCost = (float) $product->packaging_cost;
            $cargoCost = (float) $product->cargo_cost;
            $extraFixed = (float) $product->extra_cost_fixed;
            $extraPercent = (float) $product->extra_cost_percentage;
            $commissionRate = (float) $product->commission_rate;
            $vatRate = (float) $product->vat_rate;

            $totalCost = $product->total_cost;

            $entry = [
                'mp_product_id'       => $product->id,
                'barcode'             => $product->barcode,
                'stock_code'          => $product->stock_code,
                'model_code'          => $product->model_code,
                'product_name'        => $product->product_name,
                'cogs'                => $cogs,
                'packaging_cost'      => $packagingCost,
                'cargo_cost'          => $cargoCost,
                'extra_cost_fixed'    => $extraFixed,
                'extra_cost_percentage' => $extraPercent,
                'total_cost'          => round($totalCost, 2),
                'commission_rate'     => $commissionRate,
                'vat_rate'            => $vatRate,
                'sale_price'          => $salePrice,
                'stock_quantity'      => (int) $product->stock_quantity,
                'status'              => $product->status,
                'has_cost'            => $cogs > 0,
            ];

            // Birden fazla key ile eşleştir (barkod, model kodu, stok kodu)
            if ($product->barcode && trim($product->barcode) !== '') {
                $result[$product->barcode] = $entry;
            }
            if ($product->model_code && trim($product->model_code) !== '') {
                $result['mc:' . $product->model_code] = $entry;
            }
            if ($product->stock_code && trim($product->stock_code) !== '') {
                $result['sc:' . $product->stock_code] = $entry;
            }
        }

        $totalRequested = count($barcodes) + count($modelCodes) + count($stockCodes);

        return response()->json([
            'ok' => true,
            'products' => $result,
            'matched' => $products->count(),
            'total_requested' => $totalRequested,
        ]);
    }

    /**
     * Trendyol Seller Panel'den doğrudan ürün maliyetini (cogs) günceller.
     */
    public function updateProductCost(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode'    => ['nullable', 'string', 'max:120'],
            'model_code' => ['nullable', 'string', 'max:120'],
            'stock_code' => ['nullable', 'string', 'max:120'],
            'mp_product_id' => ['nullable', 'integer'],
            'cogs'       => ['required', 'numeric', 'min:0'],
        ]);

        $userId = $this->userId();
        $query = \App\Models\MpProduct::where('user_id', $userId);

        if (!empty($validated['mp_product_id'])) {
            $query->where('id', $validated['mp_product_id']);
        } else {
            $query->where(function ($q) use ($validated) {
                if (!empty($validated['barcode'])) {
                    $q->orWhere('barcode', $validated['barcode']);
                }
                if (!empty($validated['model_code'])) {
                    $q->orWhere('model_code', $validated['model_code']);
                }
                if (!empty($validated['stock_code'])) {
                    $q->orWhere('stock_code', $validated['stock_code']);
                }
            });
        }

        $product = $query->first();

        if (!$product) {
            return response()->json([
                'ok' => false,
                'message' => 'Ürün ZOLM panelinde bulunamadı.',
            ], 404);
        }

        $product->update([
            'cogs' => $validated['cogs'],
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Ürün maliyeti başarıyla güncellendi.',
            'product' => [
                'mp_product_id' => $product->id,
                'product_name' => $product->product_name,
                'barcode' => $product->barcode,
                'cogs' => (float) $product->cogs,
                'total_cost' => (float) $product->total_cost,
            ],
        ]);
    }

    /**
     * Trendyol sipariş ekranındaki görünür siparişler için anlık kârlılık.
     * ZOLM snapshot'ı varsa onu, yoksa ürün kartı maliyetlerinden tahmini sonucu döner.
     */
    public function orderProfitLookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orders' => ['required', 'array', 'max:100'],
            'orders.*.order_number' => ['required', 'string', 'max:100'],
            'orders.*.revenue' => ['required', 'numeric', 'min:0'],
            'orders.*.items' => ['required', 'array', 'min:1', 'max:20'],
            'orders.*.items.*.barcode' => ['nullable', 'string', 'max:120'],
            'orders.*.items.*.model_code' => ['nullable', 'string', 'max:120'],
            'orders.*.items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'orders.*.items.*.line_amount' => ['required', 'numeric', 'min:0'],
            'service_fee_fixed' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'withholding_tax_enabled' => ['nullable', 'boolean'],
        ]);

        $userId = $this->userId();
        $orders = collect($validated['orders'])->unique('order_number')->values();
        $orderNumbers = $orders->pluck('order_number')->all();
        $withholdingEnabled = (bool) ($validated['withholding_tax_enabled'] ?? false);
        $serviceFeeFixed = round((float) ($validated['service_fee_fixed'] ?? 0), 2);

        $channelOrders = \App\Models\ChannelOrder::query()
            ->whereHas('store', fn ($query) => $query
                ->where('user_id', $userId)
                ->where('marketplace', 'trendyol'))
            ->whereIn('order_number', $orderNumbers)
            ->with('profitSnapshot')
            ->get()
            ->keyBy('order_number');

        $barcodes = $orders->flatMap(fn ($order) => collect($order['items'])
            ->pluck('barcode'))
            ->filter()
            ->unique()
            ->values();

        $products = \App\Models\MpProduct::query()
            ->where('user_id', $userId)
            ->whereIn('barcode', $barcodes->all())
            ->get()
            ->keyBy(fn ($product) => (string) $product->barcode);

        $results = [];

        foreach ($orders as $input) {
            $orderNumber = (string) $input['order_number'];
            $channelOrder = $channelOrders->get($orderNumber);
            $snapshot = $channelOrder?->profitSnapshot;

            if ($snapshot) {
                $profitState = (string) ($snapshot->profit_state ?: 'estimated');
                $profit = $profitState === 'confirmed'
                    ? (float) $snapshot->confirmed_profit
                    : (float) $snapshot->estimated_profit;

                $results[$orderNumber] = [
                    'source' => 'snapshot',
                    'state' => $profitState,
                    'profit' => round($profit, 2),
                    'margin_percent' => round((float) $snapshot->margin_percent, 1),
                    'gross_revenue' => round((float) $snapshot->gross_revenue, 2),
                    'commission_total' => round((float) $snapshot->commission_total, 2),
                    'withholding_total' => round((float) $snapshot->withholding_total, 2),
                    'marketplace_cargo_total' => round((float) $snapshot->cargo_total, 2),
                    'service_fee_total' => round((float) $snapshot->service_fee_total, 2),
                    'cogs_cost' => round((float) $snapshot->cogs_cost, 2),
                    'packaging_cost' => round((float) $snapshot->packaging_cost, 2),
                    'own_cargo_cost' => round((float) $snapshot->own_cargo_cost, 2),
                    'calculated_at' => $snapshot->calculated_at?->toIso8601String(),
                ];
                continue;
            }

            $revenue = round((float) $input['revenue'], 2);
            $items = collect($input['items']);
            $lineAmountTotal = max(0.01, (float) $items->sum('line_amount'));
            $allocationFactor = $revenue / $lineAmountTotal;
            $missingIdentifiers = [];
            $cogs = 0.0;
            $packaging = 0.0;
            $ownCargo = 0.0;
            $totalCost = 0.0;
            $commission = 0.0;
            $withholding = 0.0;

            foreach ($items as $item) {
                $barcode = trim((string) ($item['barcode'] ?? ''));
                $product = $barcode !== '' ? $products->get($barcode) : null;
                if (! $product) {
                    $missingIdentifiers[] = $barcode ?: (string) ($item['model_code'] ?? 'Bilinmeyen ürün');
                    continue;
                }

                $quantity = (int) $item['quantity'];
                $allocatedRevenue = (float) $item['line_amount'] * $allocationFactor;
                $commissionRate = (float) $product->commission_rate;
                $vatRate = (float) $product->vat_rate;

                $cogs += (float) $product->cogs * $quantity;
                $packaging += (float) $product->packaging_cost * $quantity;
                $ownCargo += (float) $product->cargo_cost * $quantity;
                $totalCost += (float) $product->total_cost * $quantity;
                $commission += $allocatedRevenue * ($commissionRate / 100);

                if ($withholdingEnabled) {
                    $withholdingBase = $vatRate > 0
                        ? $allocatedRevenue / (1 + ($vatRate / 100))
                        : $allocatedRevenue;
                    $withholding += $withholdingBase * 0.01;
                }
            }

            if ($missingIdentifiers !== []) {
                $results[$orderNumber] = [
                    'source' => 'live_estimate',
                    'state' => 'missing_product',
                    'missing_identifiers' => array_values(array_unique($missingIdentifiers)),
                ];
                continue;
            }

            // Trendyol finans kalemlerini ayrı ayrı kuruşa yuvarlayarak tahakkuk ettirir.
            // Net kârı ham ondalıklardan değil, gösterilen/tahsil edilen kalemlerden üret.
            $commission = round($commission, 2);
            $withholding = round($withholding, 2);
            $profit = $revenue - $commission - $serviceFeeFixed - $withholding - $totalCost;
            $margin = $cogs > 0 ? ($profit / $cogs) * 100 : 0;

            $results[$orderNumber] = [
                'source' => 'live_estimate',
                'state' => $cogs > 0 ? 'estimated' : 'missing_cost',
                'profit' => round($profit, 2),
                'margin_percent' => round($margin, 1),
                'gross_revenue' => $revenue,
                'commission_total' => round($commission, 2),
                'withholding_total' => round($withholding, 2),
                'marketplace_cargo_total' => 0.0,
                'service_fee_total' => $serviceFeeFixed,
                'cogs_cost' => round($cogs, 2),
                'packaging_cost' => round($packaging, 2),
                'own_cargo_cost' => round($ownCargo, 2),
                'total_cost' => round($totalCost, 2),
            ];
        }

        return response()->json([
            'ok' => true,
            'orders' => $results,
        ]);
    }
}
