<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;

class IntegrationSyncProfile extends Model
{
    use HasFactory;

    public const WOOCOMMERCE_RECOMMENDED_WEBHOOK_TOPICS = [
        'order.created',
        'order.updated',
        'order.deleted',
        'product.created',
        'product.updated',
        'product.deleted',
    ];

    public const SHOPIFY_RECOMMENDED_WEBHOOK_TOPICS = [
        'orders/create',
        'orders/updated',
        'orders/cancelled',
        'products/create',
        'products/update',
        'products/delete',
        'inventory_levels/update',
        'refunds/create',
    ];

    public const IKAS_RECOMMENDED_WEBHOOK_TOPICS = [
        'store/order/created',
        'store/order/updated',
        'store/product/created',
        'store/product/updated',
        'store/product/deleted',
        'store/stock/created',
        'store/stock/updated',
    ];

    public const IDEASOFT_RECOMMENDED_WEBHOOK_TOPICS = [
        'order/create',
        'order/update',
        'order/delete',
        'product/create',
        'product/update',
        'product/delete',
        'payment/create',
        'payment/update',
        'order_refund_request/create',
        'order_refund_request/update',
    ];

    protected $fillable = [
        'store_id',
        'orders_poll_minutes',
        'finance_poll_minutes',
        'products_poll_minutes',
        'claims_poll_minutes',
        'questions_poll_minutes',
        'backfill_mode',
        'backfill_days',
        'backfill_custom_from',
        'backfill_custom_to',
        'orders_enabled',
        'finance_enabled',
        'products_enabled',
        'claims_enabled',
        'questions_enabled',
        'webhook_enabled',
        'price_push_enabled',
        'stock_push_enabled',
        'auto_match_enabled',
        'barcode_fallback_enabled',
        'strict_unique_match_enabled',
        'nightly_repair_sync_enabled',
        'max_parallel_jobs',
        'request_jitter_seconds',
        'extra_settings',
        'catalog_source_user_id',
    ];

    protected function casts(): array
    {
        return [
            'backfill_custom_from' => 'datetime',
            'backfill_custom_to' => 'datetime',
            'orders_enabled' => 'boolean',
            'finance_enabled' => 'boolean',
            'products_enabled' => 'boolean',
            'claims_enabled' => 'boolean',
            'questions_enabled' => 'boolean',
            'webhook_enabled' => 'boolean',
            'price_push_enabled' => 'boolean',
            'stock_push_enabled' => 'boolean',
            'auto_match_enabled' => 'boolean',
            'barcode_fallback_enabled' => 'boolean',
            'strict_unique_match_enabled' => 'boolean',
            'nightly_repair_sync_enabled' => 'boolean',
            'extra_settings' => 'array',
        ];
    }

    public static function defaults(): array
    {
        return [
            'orders_poll_minutes' => 15,
            'finance_poll_minutes' => 60,
            'products_poll_minutes' => 360,
            'claims_poll_minutes' => 30,
            'questions_poll_minutes' => 15,
            'backfill_mode' => '30_days',
            'backfill_days' => 30,
            'orders_enabled' => true,
            'finance_enabled' => true,
            'products_enabled' => true,
            'claims_enabled' => true,
            'questions_enabled' => true,
            'webhook_enabled' => true,
            'price_push_enabled' => false,
            'stock_push_enabled' => false,
            'auto_match_enabled' => true,
            'barcode_fallback_enabled' => true,
            'strict_unique_match_enabled' => true,
            'nightly_repair_sync_enabled' => true,
            'max_parallel_jobs' => 1,
            'request_jitter_seconds' => 5,
            'extra_settings' => [],
        ];
    }

    public static function defaultsForMarketplace(?string $marketplace): array
    {
        $defaults = self::defaults();

        return match (strtolower((string) $marketplace)) {
            'trendyol' => array_replace($defaults, config('marketplace.trendyol.sync_defaults', [])),
            'hepsiburada' => array_replace($defaults, config('marketplace.hepsiburada.sync_defaults', [])),
            'n11' => array_replace($defaults, config('marketplace.n11.sync_defaults', [])),
            'koctas' => array_replace($defaults, config('marketplace.koctas.sync_defaults', [])),
            'pazarama' => array_replace($defaults, config('marketplace.pazarama.sync_defaults', [])),
            'ciceksepeti' => array_replace($defaults, config('marketplace.ciceksepeti.sync_defaults', [])),
            'woocommerce' => array_replace($defaults, config('marketplace.woocommerce.sync_defaults', []), [
                'extra_settings' => [
                    'webhook_topics' => self::recommendedWebhookTopicsForMarketplace('woocommerce'),
                ],
            ]),
            'shopify' => array_replace($defaults, config('marketplace.shopify.sync_defaults', []), [
                'extra_settings' => [
                    'webhook_topics' => self::recommendedWebhookTopicsForMarketplace('shopify'),
                ],
            ]),
            'ikas' => array_replace($defaults, config('marketplace.ikas.sync_defaults', []), [
                'extra_settings' => [
                    'webhook_topics' => self::recommendedWebhookTopicsForMarketplace('ikas'),
                ],
            ]),
            'ideasoft' => array_replace($defaults, config('marketplace.ideasoft.sync_defaults', []), [
                'extra_settings' => [
                    'webhook_topics' => self::recommendedWebhookTopicsForMarketplace('ideasoft'),
                ],
            ]),
            'ticimax' => array_replace($defaults, config('marketplace.ticimax.sync_defaults', [])),
            'tsoft' => array_replace($defaults, config('marketplace.tsoft.sync_defaults', [])),
            'magento' => array_replace($defaults, config('marketplace.magento.sync_defaults', [])),
            default => $defaults,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function recommendedWooWebhookTopics(): array
    {
        return self::WOOCOMMERCE_RECOMMENDED_WEBHOOK_TOPICS;
    }

    /**
     * @return array<int, string>
     */
    public static function recommendedShopifyWebhookTopics(): array
    {
        return self::SHOPIFY_RECOMMENDED_WEBHOOK_TOPICS;
    }

    /**
     * @return array<int, string>
     */
    public static function recommendedWebhookTopicsForMarketplace(?string $marketplace): array
    {
        return match (strtolower((string) $marketplace)) {
            'woocommerce' => self::recommendedWooWebhookTopics(),
            'shopify' => self::recommendedShopifyWebhookTopics(),
            'ikas' => self::IKAS_RECOMMENDED_WEBHOOK_TOPICS,
            'ideasoft' => self::IDEASOFT_RECOMMENDED_WEBHOOK_TOPICS,
            default => [],
        };
    }

    /**
     * @param  array<int, string>|null  $topics
     * @return array<int, string>
     */
    public static function normalizeWebhookTopics(?array $topics): array
    {
        return collect(Arr::wrap($topics))
            ->filter(fn ($topic) => filled($topic))
            ->map(fn ($topic) => (string) $topic)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>|null  $topics
     * @return array{
     *     selected: array<int, string>,
     *     recommended: array<int, string>,
     *     missing: array<int, string>,
     *     extra: array<int, string>,
     *     is_empty: bool,
     *     matches_recommended: bool
     * }
     */
    public static function auditWebhookTopics(?string $marketplace, ?array $topics): array
    {
        $selected = self::normalizeWebhookTopics($topics);
        $recommended = self::recommendedWebhookTopicsForMarketplace($marketplace);
        $missing = array_values(array_diff($recommended, $selected));
        $extra = array_values(array_diff($selected, $recommended));

        return [
            'selected' => $selected,
            'recommended' => $recommended,
            'missing' => $missing,
            'extra' => $extra,
            'is_empty' => $selected === [],
            'matches_recommended' => $missing === [] && $extra === [],
        ];
    }

    /**
     * @param  array<int, string>|null  $topics
     * @return array<int, string>
     */
    public static function normalizeWooWebhookTopics(?array $topics): array
    {
        return self::normalizeWebhookTopics($topics);
    }

    /**
     * @param  array<int, string>|null  $topics
     * @return array{
     *     selected: array<int, string>,
     *     recommended: array<int, string>,
     *     missing: array<int, string>,
     *     extra: array<int, string>,
     *     is_empty: bool,
     *     matches_recommended: bool
     * }
     */
    public static function auditWooWebhookTopics(?array $topics): array
    {
        return self::auditWebhookTopics('woocommerce', $topics);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }
}
