<?php
if (! defined("ABSPATH")) exit;

class ZOLM_Booster_DB {
    private static $instance = null;
    private $table;

    public static function instance(): self {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . ZOLM_BOOSTER_DB_TABLE;
    }

    public static function create_table(): void {
        global $wpdb;
        $table = $wpdb->prefix . ZOLM_BOOSTER_DB_TABLE;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            zolm_review_id VARCHAR(80) UNIQUE,
            product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            trendyol_product_id VARCHAR(80) NOT NULL,
            trendyol_review_id VARCHAR(80) NOT NULL,
            trendyol_product_barcode VARCHAR(120) DEFAULT NULL,
            wc_product_sku VARCHAR(100) DEFAULT NULL,
            reviewer_name VARCHAR(180) DEFAULT NULL,
            reviewer_avatar_url VARCHAR(1000) DEFAULT NULL,
            rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
            comment TEXT NOT NULL,
            comment_length INT UNSIGNED DEFAULT 0,
            review_media LONGTEXT DEFAULT NULL,
            helpful_count INT UNSIGNED DEFAULT 0,
            seller_name VARCHAR(180) DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \"pending\",
            is_featured TINYINT(1) DEFAULT 0,
            display_order INT DEFAULT 0,
            is_spam TINYINT(1) DEFAULT 0,
            spam_score DECIMAL(3,2) DEFAULT 0.00,
            spam_flags TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_trendyol_review (trendyol_product_id, trendyol_review_id),
            KEY idx_product (product_id),
            KEY idx_status (status),
            KEY idx_rating (rating),
            KEY idx_featured (is_featured),
            KEY idx_reviewed (reviewed_at)
        ) $charset;";

        require_once ABSPATH . "wp-admin/includes/upgrade.php";
        dbDelta($sql);

        update_option("zolm_booster_db_version", ZOLM_BOOSTER_VERSION);
        self::maybe_migrate();
    }

    /**
     * Mevcut tabloya yeni kolonlar ekler (dbDelta sadece CREATE için çalışır).
     */
    public static function maybe_migrate(): void {
        global $wpdb;
        $table = $wpdb->prefix . ZOLM_BOOSTER_DB_TABLE;
        $existing = $wpdb->get_col("DESCRIBE {$table}", 0);
        $new_columns = [
            "trendyol_product_barcode" => "ADD COLUMN trendyol_product_barcode VARCHAR(120) DEFAULT NULL AFTER trendyol_review_id",
            "wc_product_sku" => "ADD COLUMN wc_product_sku VARCHAR(100) DEFAULT NULL AFTER trendyol_product_barcode",
            "comment_length" => "ADD COLUMN comment_length INT UNSIGNED DEFAULT 0 AFTER comment",
            "display_order" => "ADD COLUMN display_order INT DEFAULT 0 AFTER is_featured",
        ];
        foreach ($new_columns as $col => $ddl) {
            if (! in_array($col, $existing, true)) {
                $wpdb->query("ALTER TABLE {$table} {$ddl}");
            }
        }
    }

    public function table(): string {
        return $this->table;
    }

    public function upsert_review(array $data): array {
        global $wpdb;
        $now = current_time("mysql");

        // Laravel push servisi zb_review_id / wc_product_id gönderir — WP alan adlarına eşle
        $zolm_id = $data["zolm_review_id"]
            ?? $data["zb_review_id"]
            ?? ($data["trendyol_product_id"] . "-" . $data["trendyol_review_id"]);

        $product_id = (int) ($data["product_id"] ?? $data["wc_product_id"] ?? 0);
        $barcode = (string) ($data["trendyol_product_barcode"] ?? "");
        $sku = (string) ($data["wc_product_sku"] ?? $barcode);

        // Auto-match: product_id boşsa barcode/SKU'dan WC ürününü bul
        if ($product_id === 0 && ($barcode || $sku)) {
            $product_id = $this->resolve_product_id($barcode, $sku);
        }

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, status FROM {$this->table} WHERE zolm_review_id = %s",
            $zolm_id
        ));

        $comment = (string) ($data["comment"] ?? "");
        $payload = [
            "zolm_review_id" => $zolm_id,
            "product_id" => $product_id,
            "trendyol_product_id" => (string) ($data["trendyol_product_id"] ?? ""),
            "trendyol_review_id" => (string) ($data["trendyol_review_id"] ?? ""),
            "trendyol_product_barcode" => $barcode ?: null,
            "wc_product_sku" => $sku ?: null,
            "reviewer_name" => substr((string) ($data["reviewer_name"] ?? $data["reviewer_name_masked"] ?? ""), 0, 180),
            "reviewer_avatar_url" => substr((string) ($data["reviewer_avatar_url"] ?? ""), 0, 1000) ?: null,
            "rating" => max(1, min(5, (int) ($data["rating"] ?? 5))),
            "comment" => $comment,
            "comment_length" => mb_strlen($comment),
            "review_media" => isset($data["review_media"]) ? wp_json_encode($data["review_media"]) : null,
            "helpful_count" => (int) ($data["helpful_count"] ?? 0),
            "seller_name" => substr((string) ($data["seller_name"] ?? ""), 0, 180) ?: null,
            "reviewed_at" => ! empty($data["reviewed_at"]) ? date("Y-m-d H:i:s", strtotime($data["reviewed_at"])) : null,
            "is_spam" => (int) ($data["is_spam"] ?? 0),
            "spam_score" => (float) ($data["spam_score"] ?? 0),
            "spam_flags" => isset($data["spam_flags"]) ? wp_json_encode($data["spam_flags"]) : null,
            "updated_at" => $now,
        ];

        if ($existing) {
            $wpdb->update($this->table, $payload, ["id" => $existing->id], null, ["%d"]);
            $this->invalidate_cache((int) $payload["product_id"]);
            return ["action" => "updated", "id" => $existing->id];
        }

        $allowed_statuses = ["pending", "approved", "rejected", "deleted"];
        $payload["status"] = in_array($data["status"] ?? "", $allowed_statuses, true) ? $data["status"] : "pending";
        $payload["created_at"] = $now;
        $wpdb->insert($this->table, $payload);
        $this->invalidate_cache((int) $payload["product_id"]);
        return ["action" => "inserted", "id" => (int) $wpdb->insert_id];
    }

    /**
     * Barcode veya SKU'dan WooCommerce ürün ID'sini bulur.
     */
    private function resolve_product_id(string $barcode, string $sku): int {
        if (function_exists("wc_get_product_id_by_sku")) {
            if ($barcode) {
                $id = wc_get_product_id_by_sku($barcode);
                if ($id) return (int) $id;
            }
            if ($sku) {
                $id = wc_get_product_id_by_sku($sku);
                if ($id) return (int) $id;
            }
        }
        // Fallback: postmeta'dan _sku ara
        global $wpdb;
        $lookup = $barcode ?: $sku;
        if ($lookup) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                "_sku", $lookup
            ));
        }
        return 0;
    }

    public function get_reviews_for_product(int $product_id, array $args = []): array {
        global $wpdb;
        $defaults = [
            "status" => "approved",
            "min_rating" => (int) ((get_option(ZOLM_BOOSTER_SETTINGS, []))["min_rating"] ?? 0),
            "limit" => 20,
            "offset" => 0,
            "orderby" => "reviewed_at",
            "order" => "DESC",
            "featured_first" => true,
        ];
        $args = wp_parse_args($args, $defaults);

        $where = $wpdb->prepare("WHERE product_id = %d", $product_id);
        if ($args["status"] !== "all") {
            $where .= $wpdb->prepare(" AND status = %s", $args["status"]);
        }
        if ($args["min_rating"] > 0) {
            $where .= $wpdb->prepare(" AND rating >= %d", $args["min_rating"]);
        }

        $orderby = in_array($args["orderby"], ["reviewed_at", "rating", "helpful_count", "id"], true) ? $args["orderby"] : "reviewed_at";
        $order = strtoupper($args["order"]) === "ASC" ? "ASC" : "DESC";
        $order_clause = $args["featured_first"] ? "is_featured DESC, {$orderby} {$order}" : "{$orderby} {$order}";

        $sql = "SELECT * FROM {$this->table} {$where} ORDER BY {$order_clause} LIMIT %d OFFSET %d";
        return $wpdb->get_results($wpdb->prepare($sql, $args["limit"], $args["offset"]));
    }

    public function get_product_rating_summary(int $product_id): array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) as total, AVG(rating) as average, SUM(CASE WHEN rating=5 THEN 1 ELSE 0 END) as r5, SUM(CASE WHEN rating=4 THEN 1 ELSE 0 END) as r4, SUM(CASE WHEN rating=3 THEN 1 ELSE 0 END) as r3, SUM(CASE WHEN rating=2 THEN 1 ELSE 0 END) as r2, SUM(CASE WHEN rating=1 THEN 1 ELSE 0 END) as r1 FROM {$this->table} WHERE product_id = %d AND status = %s",
            $product_id, "approved"
        ), ARRAY_A);

        return [
            "total" => (int) ($row["total"] ?? 0),
            "average" => round((float) ($row["average"] ?? 0), 1),
            "distribution" => [
                5 => (int) ($row["r5"] ?? 0),
                4 => (int) ($row["r4"] ?? 0),
                3 => (int) ($row["r3"] ?? 0),
                2 => (int) ($row["r2"] ?? 0),
                1 => (int) ($row["r1"] ?? 0),
            ],
        ];
    }

    public function update_status(int $id, string $status): bool {
        global $wpdb;
        $allowed = ["pending", "approved", "rejected", "deleted"];
        if (! in_array($status, $allowed, true)) return false;
        $result = $wpdb->update($this->table, ["status" => $status, "updated_at" => current_time("mysql")], ["id" => $id], ["%s", "%s"], ["%d"]);
        $product_id = (int) $wpdb->get_var($wpdb->prepare("SELECT product_id FROM {$this->table} WHERE id = %d", $id));
        $this->invalidate_cache($product_id);
        return $result !== false;
    }

    public function toggle_featured(int $id): bool {
        global $wpdb;
        $current = (int) $wpdb->get_var($wpdb->prepare("SELECT is_featured FROM {$this->table} WHERE id = %d", $id));
        $new = $current ? 0 : 1;
        $wpdb->update($this->table, ["is_featured" => $new, "updated_at" => current_time("mysql")], ["id" => $id], ["%d", "%s"], ["%d"]);
        $product_id = (int) $wpdb->get_var($wpdb->prepare("SELECT product_id FROM {$this->table} WHERE id = %d", $id));
        $this->invalidate_cache($product_id);
        return (bool) $new;
    }

    public function delete_review(int $id): bool {
        global $wpdb;
        $product_id = (int) $wpdb->get_var($wpdb->prepare("SELECT product_id FROM {$this->table} WHERE id = %d", $id));
        $result = $wpdb->update(
            $this->table,
            ["status" => "deleted", "updated_at" => current_time("mysql")],
            ["id" => $id],
            ["%s", "%s"],
            ["%d"]
        );
        $this->invalidate_cache($product_id);
        return $result !== false;
    }

    public function invalidate_cache(int $product_id): void {
        if ($product_id > 0) {
            delete_transient("zolm_reviews_" . $product_id);
            delete_transient("zolm_rating_" . $product_id);
        }
        delete_transient("zolm_shop_summary");
    }

    /**
     * Genel istatistikler (admin panel + REST /stats için).
     */
    public function get_stats(): array {
        global $wpdb;
        $base = "FROM {$this->table}";
        $total = (int) $wpdb->get_var("SELECT COUNT(*) {$base}");
        $approved = (int) $wpdb->get_var("SELECT COUNT(*) {$base} WHERE status = 'approved'");
        $pending = (int) $wpdb->get_var("SELECT COUNT(*) {$base} WHERE status = 'pending'");
        $rejected = (int) $wpdb->get_var("SELECT COUNT(*) {$base} WHERE status = 'rejected'");
        $spam = (int) $wpdb->get_var("SELECT COUNT(*) {$base} WHERE is_spam = 1");
        $matched = (int) $wpdb->get_var("SELECT COUNT(*) {$base} WHERE product_id > 0");
        $avg = (float) $wpdb->get_var("SELECT AVG(rating) {$base} WHERE status = 'approved' AND is_spam = 0");

        $dist = [];
        for ($i = 5; $i >= 1; $i--) {
            $dist[$i] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) {$base} WHERE status = 'approved' AND rating = %d",
                $i
            ));
        }

        return [
            "total" => $total,
            "approved" => $approved,
            "pending" => $pending,
            "rejected" => $rejected,
            "spam" => $spam,
            "matched" => $matched,
            "average_rating" => round($avg, 2),
            "rating_distribution" => $dist,
        ];
    }

    /**
     * Mağaza seviyesi toplam güven rozeti (shop trust badge) için özet.
     */
    public function get_shop_summary(): array {
        global $wpdb;
        $cache = get_transient("zolm_shop_summary");
        if ($cache !== false) return $cache;

        $row = $wpdb->get_row(
            "SELECT COUNT(*) as total_reviews, AVG(rating) as average, COUNT(DISTINCT product_id) as total_products FROM {$this->table} WHERE status = 'approved' AND is_spam = 0 AND product_id > 0",
            ARRAY_A
        );

        $summary = [
            "total_reviews" => (int) ($row["total_reviews"] ?? 0),
            "average_rating" => round((float) ($row["average"] ?? 0), 1),
            "total_products" => (int) ($row["total_products"] ?? 0),
        ];
        set_transient("zolm_shop_summary", $summary, HOUR_IN_SECONDS * 6);
        return $summary;
    }
}
