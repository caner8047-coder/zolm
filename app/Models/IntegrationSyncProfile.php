<?php

namespace App\Models;

use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    protected $fillable = [
        'store_id',
        'orders_poll_minutes',
        'finance_poll_minutes',
        'products_poll_minutes',
        'backfill_mode',
        'backfill_days',
        'backfill_custom_from',
        'backfill_custom_to',
        'orders_enabled',
        'finance_enabled',
        'products_enabled',
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
    ];

    protected function casts(): array
    {
        return [
            'backfill_custom_from' => 'datetime',
            'backfill_custom_to' => 'datetime',
            'orders_enabled' => 'boolean',
            'finance_enabled' => 'boolean',
            'products_enabled' => 'boolean',
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
            'backfill_mode' => '30_days',
            'backfill_days' => 30,
            'orders_enabled' => true,
            'finance_enabled' => true,
            'products_enabled' => true,
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
