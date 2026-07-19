<?php
if (! defined("ABSPATH")) exit;

class ZOLM_Booster_Badge {
    private static $instance = null;

    public static function instance(): self {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action("woocommerce_after_shop_loop_item_title", [$this, "render_mini_badge"], 5);
        add_filter("woocommerce_product_get_rating_html", [$this, "filter_rating_html"], 10, 3);
        add_action("wp_enqueue_scripts", [$this, "enqueue_archive_assets"]);
        add_action("woocommerce_shortcode_before_products_loop", [$this, "enqueue_assets"]);
        add_action("woocommerce_before_shop_loop", [$this, "enqueue_assets"]);
        add_action("woocommerce_before_shop_loop", [$this, "render_shop_trust_badge"], 5);
        add_action("wp_head", [$this, "render_category_schema"]);
    }

    public function enqueue_assets(): void {
        wp_enqueue_style("zolm-booster");
    }

    public function enqueue_archive_assets(): void {
        if (is_shop() || is_product_category() || is_product_tag()) {
            wp_enqueue_style("zolm-booster");
        }
    }

    public function render_mini_badge(): void {
        global $product;
        if (! $product) return;
        $settings = get_option(ZOLM_BOOSTER_SETTINGS, []);
        if (($settings["enable_category_badge"] ?? 1) == 0) return;
        $product_id = $product->get_id();
        if (! $this->canary_allows_product($product_id)) return;
        $summary = $this->get_cached_summary($product_id);
        if (($summary["total"] ?? 0) === 0) return;

        $average = number_format($summary["average"], 1);
        $total = $summary["total"];
        echo '<div class="zolm-mini-badge" data-product-id="' . esc_attr($product_id) . '">';
        echo $this->render_mini_stars((float) $summary["average"]);
        echo '<span class="zolm-mini-rating">' . esc_html($average) . '</span>';
        echo '<span class="zolm-mini-count">(' . esc_html($total) . ')</span>';
        echo '</div>';
    }

    public function filter_rating_html($html, $rating, $count) {
        global $product;
        if (! $product) return $html;
        $product_id = $product->get_id();
        $summary = $this->get_cached_summary($product_id);
        if (($summary["total"] ?? 0) === 0) return $html;

        $average = number_format($summary["average"], 1);
        $total = $summary["total"];
        $stars = $this->render_mini_stars((float) $summary["average"]);
        return '<div class="star-rating zolm-rating-override">' . $stars . '</div><span class="zolm-mini-rating-inline">' . esc_html($average) . ' (' . esc_html($total) . ')</span>';
    }

    private function get_cached_summary(int $product_id): array {
        $cache = get_transient("zolm_rating_" . $product_id);
        if ($cache !== false) return $cache;
        $summary = ZOLM_Booster_DB::instance()->get_product_rating_summary($product_id);
        set_transient("zolm_rating_" . $product_id, $summary, HOUR_IN_SECONDS * 6);
        return $summary;
    }

    private function render_mini_stars(float $rating): string {
        $html = '<span class="zolm-mini-stars">';
        for ($i = 1; $i <= 5; $i++) {
            $cls = $rating >= $i ? "full" : ($rating >= $i - 0.5 ? "half" : "empty");
            $html .= '<span class="zolm-mini-star ' . $cls . '">★</span>';
        }
        $html .= '</span>';
        return $html;
    }

    /**
     * Canary deploy kontrolü — widget ile aynı mantık.
     */
    private function canary_allows_product(int $product_id): bool {
        $settings = get_option(ZOLM_BOOSTER_SETTINGS, []);
        $pct = (int) ($settings["canary_percentage"] ?? 100);
        if ($pct >= 100) return true;
        if ($pct <= 0) return false;
        return (crc32("zolm_canary_" . $product_id) % 100) < $pct;
    }

    /**
     * Faz 3.5: Mağaza seviyesi güven rozeti (shop trust badge).
     * "Trendyol'da X yorum · 4.7 ortalama ⭐"
     */
    public function render_shop_trust_badge(): void {
        if (! is_shop() && ! is_product_category()) return;
        $settings = get_option(ZOLM_BOOSTER_SETTINGS, []);
        if (($settings["enable_shop_trust"] ?? 1) == 0) return;

        $summary = ZOLM_Booster_DB::instance()->get_shop_summary();
        if (($summary["total_reviews"] ?? 0) === 0) return;

        wp_enqueue_style("zolm-booster");
        $avg = number_format($summary["average_rating"], 1);
        $total = $summary["total_reviews"];
        $products = $summary["total_products"];
        $stars = $this->render_mini_stars((float) $summary["average_rating"]);

        echo '<div class="zolm-shop-trust-badge">';
        echo '<span class="zolm-trust-icon">★</span>';
        echo '<span class="zolm-trust-text">Trendyol\'da <strong>' . esc_html(number_format($total, 0, "", ".")) . '</strong> yorum · ';
        echo $stars . ' <strong>' . esc_html($avg) . '</strong> ortalama';
        echo ' · <strong>' . esc_html(number_format($products, 0, "", ".")) . '</strong> ürün</span>';
        echo '<span class="zolm-trust-verified">Trendyol doğrulanmış</span>';
        echo '</div>';
    }

    /**
     * Faz 3.5: Kategori/shop sayfalarında her ürün için Schema.org AggregateRating.
     * Google arama sonuçlarında yıldız görünür.
     */
    public function render_category_schema(): void {
        if (! is_product() && ! is_shop() && ! is_product_category()) return;

        if (is_product()) {
            // Single product: widget zaten schema output ediyor (inject_via_footer → output_schema_markup)
            return;
        }

        // Shop/category: tüm ürünlerin rating'lerini JSON-LD olarak output et
        global $wp_query;
        if (! $wp_query || ! $wp_query->have_posts()) return;

        $schemas = [];
        while ($wp_query->have_posts()) {
            $wp_query->the_post();
            $product_id = get_the_ID();
            $summary = $this->get_cached_summary($product_id);
            if (($summary["total"] ?? 0) === 0) continue;

            $product = wc_get_product($product_id);
            $schemas[] = [
                "@type" => "Product",
                "name" => $product ? $product->get_name() : get_the_title(),
                "aggregateRating" => [
                    "@type" => "AggregateRating",
                    "ratingValue" => $summary["average"],
                    "reviewCount" => $summary["total"],
                    "bestRating" => "5",
                    "worstRating" => "1",
                ],
            ];
        }
        wp_reset_postdata();

        if (empty($schemas)) return;
        echo '<script type="application/ld+json">' . wp_json_encode($schemas) . "</script>\n";
    }
}
