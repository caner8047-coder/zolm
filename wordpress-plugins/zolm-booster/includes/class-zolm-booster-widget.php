<?php
if (! defined("ABSPATH")) exit;

class ZOLM_Booster_Widget {
    private static $instance = null;
    private static bool $rendered = false;

    public static function instance(): self {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_shortcode("zolm_reviews", [$this, "render_shortcode"]);
        add_action("woocommerce_after_single_product_summary", [$this, "render_product_widget"], 12);
        add_filter("the_content", [$this, "append_to_content"], 99);
        add_action("wp_footer", [$this, "inject_via_footer"], 99);
        add_action("wp_head", [$this, "render_schema_markup"]);
    }

    public function append_to_content(string $content): string {
        if (is_admin() || get_post_type() !== "product") return $content;
        if (function_exists("is_product") && is_product()) return $content;
        if (self::$rendered) return $content;
        $product_id = get_the_ID();
        if (!$product_id) return $content;
        if (!$this->canary_allows_product($product_id)) return $content;
        if (strpos($content, "zolm-reviews-widget") !== false) return $content;
        $html = $this->render_widget($product_id, 20);
        if ($html !== "") self::$rendered = true;
        return $content . $html;
    }

    public function inject_via_footer(): void {
        $product_id = 0;
        if (function_exists("is_product") && is_product()) {
            $product_id = get_the_ID();
        } elseif (is_singular("product")) {
            $product_id = get_the_ID();
        } else {
            $qv = get_query_var("p");
            if ($qv) {
                $post = get_post($qv);
                if ($post && $post->post_type === "product") $product_id = $post->ID;
            }
        }
        if (!$product_id) return;

        // Canary deploy kontrolü
        if (!$this->canary_allows_product($product_id)) return;

        // Always output schema markup for SEO (regardless of render method)
        $this->output_schema_markup($product_id);

        // If widget already rendered via WooCommerce hook or content filter,
        // CSS/JS were enqueued in <head> and widget HTML is in the page — done.
        if (self::$rendered) return;

        // Fallback: hooks did not fire (e.g. theme bypasses WooCommerce templates).
        // Inject widget HTML via JavaScript and load assets directly in footer.
        $html = $this->render_widget($product_id, 20);
        if (empty($html)) return;
        self::$rendered = true;

        echo '<link rel="stylesheet" href="' . esc_url(ZOLM_BOOSTER_URL . "assets/css/zolm-booster.css") . '?v=' . ZOLM_BOOSTER_VERSION . "\" />\n";
        echo '<script>var zolmBooster={"restUrl":"' . esc_url_raw(rest_url("zolm-booster/v1")) . '","nonce":"' . wp_create_nonce("wp_rest") . '"};</script>' . "\n";
        echo '<script src="' . esc_url(ZOLM_BOOSTER_URL . "assets/js/zolm-booster.js") . '?v=' . ZOLM_BOOSTER_VERSION . '"></script>' . "\n";

        $encoded = base64_encode($html);
        echo "<script>\n";
        echo "if(!document.querySelector('.zolm-reviews-widget')){\n";
        echo "var d=document.createElement('div');\n";
        echo "d.innerHTML=atob('{$encoded}');\n";
        echo "var w=d.firstChild;\n";
        echo "var tabs=document.querySelector('.woocommerce-tabs, .product-tabs-wrapper, .wd-accordion-tabs');\n";
        echo "if(tabs&&tabs.parentNode)tabs.insertAdjacentElement('afterend',w);else{var t=document.querySelector('.single-product .product, .product-page-wrapper, main .product, #product-{$product_id}, .entry-content');if(t)t.appendChild(w);else document.body.appendChild(w);}\n";
        echo "}\n";
        echo "</script>\n";
    }

    public function render_shortcode($atts = []): string {
        $atts = shortcode_atts(["product_id" => 0, "limit" => 20], $atts);
        $product_id = (int) $atts["product_id"];
        if (!$product_id) $product_id = get_the_ID();
        if (!$product_id) return "";
        return $this->render_widget($product_id, (int) $atts["limit"]);
    }

    public function render_product_widget(): void {
        if (self::$rendered) return;
        $product_id = get_the_ID();
        if (!$product_id) return;
        if (!$this->canary_allows_product($product_id)) return;
        $html = $this->render_widget($product_id, 20);
        if ($html !== "") {
            self::$rendered = true;
            echo $html;
        }
    }

    /**
     * Canary deploy kontrolü: canary_percentage ayarına göre bu üründe widget gösterilip gösterilmeyeceğini belirler.
     * Deterministik — aynı ürün her zaman aynı kararı alır (cache uyumu için).
     */
    private function canary_allows_product(int $product_id): bool {
        $settings = get_option(ZOLM_BOOSTER_SETTINGS, []);
        $pct = (int) ($settings["canary_percentage"] ?? 100);
        if ($pct >= 100) return true;
        if ($pct <= 0) return false;
        // Deterministik hash: ürün ID'sinin yüzdeye göre mod'u
        return (crc32("zolm_canary_" . $product_id) % 100) < $pct;
    }

    private function render_widget(int $product_id, int $limit = 20): string {
        $settings = get_option(ZOLM_BOOSTER_SETTINGS, []);
        $min_rating = (int) ($settings["min_rating"] ?? 0);
        $max_reviews = (int) ($settings["max_reviews"] ?? 20);
        $limit = min($limit, $max_reviews);

        $cache = get_transient("zolm_reviews_" . $product_id);
        if ($cache !== false) {
            $reviews = $cache["reviews"] ?? [];
            $summary = $cache["summary"] ?? [];
        } else {
            $reviews = ZOLM_Booster_DB::instance()->get_reviews_for_product($product_id, ["limit" => $limit, "min_rating" => $min_rating]);
            $summary = ZOLM_Booster_DB::instance()->get_product_rating_summary($product_id);
            set_transient("zolm_reviews_" . $product_id, ["reviews" => $reviews, "summary" => $summary], HOUR_IN_SECONDS);
        }

        if (empty($reviews)) return "";

        wp_enqueue_style("zolm-booster");
        wp_enqueue_script("zolm-booster");

        ob_start();
        $this->render_widget_html($product_id, $reviews, $summary);
        return ob_get_clean();
    }
    private function render_widget_html(int $product_id, array $reviews, array $summary): void {
        $total = $summary["total"] ?? 0;
        $average = $summary["average"] ?? 0;
        $dist = $summary["distribution"] ?? [];
        $has_media = false;
        foreach ($reviews as $r) {
            if (!empty($r->review_media)) { $has_media = true; break; }
        }
        $srcBadge = "Trendyol doğrulanmış";
?>
        <div class="zolm-reviews-widget" id="zolm-reviews-<?php echo esc_attr($product_id); ?>" data-product-id="<?php echo esc_attr($product_id); ?>">
            <div class="zolm-reviews-header">
                <div class="zolm-reviews-title-wrap">
                    <h3 class="zolm-reviews-title">Müşteri Yorumları</h3>
                    <span class="zolm-reviews-source-badge"><?php echo esc_html($srcBadge); ?></span>
                </div>
                <div class="zolm-reviews-summary">
                    <div class="zolm-reviews-avg-box">
                        <span class="zolm-reviews-avg"><?php echo esc_html(number_format($average, 1)); ?></span>
                        <div class="zolm-reviews-stars"><?php echo $this->render_stars($average); ?></div>
                        <span class="zolm-reviews-count"><?php echo esc_html($total); ?> yorum</span>
                    </div>
                    <div class="zolm-reviews-dist">
                        <?php for ($s = 5; $s >= 1; $s--):
                            $count = $dist[$s] ?? 0;
                            $pct = $total > 0 ? ($count / $total * 100) : 0; ?>
                            <div class="zolm-dist-row">
                                <span class="zolm-dist-label"><?php echo $s; ?> yıldız</span>
                                <div class="zolm-dist-bar"><div class="zolm-dist-fill" style="width:<?php echo esc_attr($pct); ?>%"></div></div>
                                <span class="zolm-dist-count"><?php echo esc_html($count); ?></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            <div class="zolm-reviews-filter-bar">
                <button class="zolm-filter-btn active" data-rating="0">Tümü</button>
                <?php for ($r = 5; $r >= 1; $r--): ?>
                    <button class="zolm-filter-btn" data-rating="<?php echo $r; ?>"><?php echo $r; ?> yıldız</button>
                <?php endfor; ?>
                <?php if ($has_media): ?>
                    <button class="zolm-filter-btn" data-rating="photo">Fotoğraflar</button>
                <?php endif; ?>
                <select class="zolm-sort-select">
                    <option value="newest">En yeni</option>
                    <option value="oldest">En eski</option>
                    <option value="highest">En yüksek puan</option>
                    <option value="lowest">En düşük puan</option>
                    <option value="helpful">En faydalı</option>
                </select>
            </div>
            <div class="zolm-reviews-list">
                <?php foreach ($reviews as $review): $media = $review->review_media ? json_decode($review->review_media, true) : []; ?>
                    <div class="zolm-review-item<?php echo $review->is_featured ? " is-featured" : "" ?>" data-rating="<?php echo esc_attr($review->rating); ?>" data-has-photo="<?php echo !empty($media) ? "1" : "0" ?>" data-date="<?php echo esc_attr($review->reviewed_at); ?>" data-helpful="<?php echo esc_attr($review->helpful_count); ?>">
                        <?php if ($review->is_featured): ?>
                            <span class="zolm-featured-badge">Öne çıkan yorum</span>
                        <?php endif; ?>
                        <div class="zolm-review-header">
                            <div class="zolm-reviewer-avatar"><?php echo esc_html(mb_substr($review->reviewer_name ?: "A", 0, 1)); ?></div>
                            <div class="zolm-reviewer-info">
                                <span class="zolm-reviewer-name"><?php echo esc_html($review->reviewer_name ?: "Anonim"); ?></span>
                                <div class="zolm-review-meta">
                                    <span class="zolm-review-stars"><?php echo $this->render_stars((float) $review->rating); ?></span>
                                    <?php if ($review->reviewed_at): ?>
                                        <span class="zolm-review-date"><?php echo esc_html(date_i18n("d M Y", strtotime($review->reviewed_at))); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="zolm-verified-badge" title="Trendyol doğrulanmış yorum">
                                <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm3 9H7v-2h6v2z"/></svg>
                            </span>
                        </div>
                        <p class="zolm-review-text"><?php echo esc_html($review->comment); ?></p>
                        <?php if (!empty($media)): ?>
                            <div class="zolm-review-photos">
                                <?php foreach ($media as $m):
                                    $url = is_array($m) ? ($m["url"] ?? "") : (string) $m;
                                    if (!$url) continue; ?>
                                    <div class="zolm-review-photo" data-src="<?php echo esc_url($url); ?>">
                                        <img src="<?php echo esc_url($url); ?>" alt="Müşteri fotoğrafı" loading="lazy" />
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($review->helpful_count > 0): ?>
                            <span class="zolm-helpful"><?php echo esc_html($review->helpful_count); ?> kişi faydalı buldu</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($total > count($reviews)): ?>
                <button class="zolm-load-more" data-product-id="<?php echo esc_attr($product_id); ?>" data-offset="<?php echo esc_attr(count($reviews)); ?>">
                    Daha fazla yorum göster
                </button>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_stars(float $rating): string {
        $html = '<div class="zolm-stars">';
        for ($i = 1; $i <= 5; $i++) {
            if ($rating >= $i) $html .= '<span class="zolm-star full">★</span>';
            elseif ($rating >= $i - 0.5) $html .= '<span class="zolm-star half">★</span>';
            else $html .= '<span class="zolm-star empty">★</span>';
        }
        $html .= '</div>';
        return $html;
    }

    public function render_schema_markup(): void {
    }

    private function output_schema_markup(int $product_id): void {
        $summary = ZOLM_Booster_DB::instance()->get_product_rating_summary($product_id);
        if (($summary["total"] ?? 0) === 0) return;
        $schema = [
            "@context" => "https://schema.org/",
            "@type" => "Product",
            "aggregateRating" => [
                "@type" => "AggregateRating",
                "ratingValue" => $summary["average"],
                "reviewCount" => $summary["total"],
                "bestRating" => "5",
                "worstRating" => "1",
            ],
        ];
        echo '<script type="application/ld+json">' . wp_json_encode($schema) . "</script>
";
    }
}
