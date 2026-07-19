<?php
if (! defined("ABSPATH")) exit;

class ZOLM_Booster_API {
    private static $instance = null;

    public static function instance(): self {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action("rest_api_init", [$this, "register_routes"]);
    }

    public function register_routes(): void {
        $namespace = "zolm-booster/v1";

        register_rest_route($namespace, "/reviews", [
            ["methods" => "POST", "callback" => [$this, "create_review"], "permission_callback" => [$this, "check_auth"]],
            ["methods" => "GET", "callback" => [$this, "list_reviews"], "permission_callback" => [$this, "check_auth"]],
        ]);

        register_rest_route($namespace, "/reviews/batch", [
            "methods" => "POST", "callback" => [$this, "batch_reviews"], "permission_callback" => [$this, "check_auth"],
        ]);

        register_rest_route($namespace, "/reviews/(?P<id>\d+)", [
            ["methods" => "PUT", "callback" => [$this, "update_review"], "permission_callback" => [$this, "check_auth"]],
            ["methods" => "DELETE", "callback" => [$this, "delete_review"], "permission_callback" => [$this, "check_auth"]],
        ]);

        register_rest_route($namespace, "/products/(?P<product_id>\d+)/reviews", [
            "methods" => "GET", "callback" => [$this, "get_product_reviews"], "permission_callback" => "__return_true",
        ]);

        register_rest_route($namespace, "/products/(?P<product_id>\d+)/rating", [
            "methods" => "GET", "callback" => [$this, "get_product_rating"], "permission_callback" => "__return_true",
        ]);

        register_rest_route($namespace, "/products/match", [
            ["methods" => "POST", "callback" => [$this, "match_product"], "permission_callback" => [$this, "check_auth"]],
            ["methods" => "GET", "callback" => [$this, "match_product_get"], "permission_callback" => [$this, "check_auth"]],
        ]);

        register_rest_route($namespace, "/stats", [
            "methods" => "GET", "callback" => [$this, "get_stats"], "permission_callback" => [$this, "check_auth"],
        ]);
    }

    public function check_auth(WP_REST_Request $request): bool {
        $api_key = $request->get_header("x_zolm_api_key") ?: $request->get_header("x-zolm-api-key");
        $stored_key = get_option(ZOLM_BOOSTER_OPTION_KEY);
        if (empty($stored_key) || empty($api_key)) return false;
        return hash_equals($stored_key, $api_key);
    }

    public function create_review(WP_REST_Request $request): WP_REST_Response {
        $data = $request->get_json_params();
        if (empty($data["comment"]) || empty($data["rating"])) {
            return new WP_REST_Response(["ok" => false, "message" => "comment ve rating zorunludur."], 400);
        }
        $result = ZOLM_Booster_DB::instance()->upsert_review($data);
        return new WP_REST_Response(["ok" => true, "action" => $result["action"], "id" => $result["id"]], 200);
    }

    public function batch_reviews(WP_REST_Request $request): WP_REST_Response {
        $body = $request->get_json_params();
        $reviews = $body["reviews"] ?? [];
        if (empty($reviews) || ! is_array($reviews)) {
            return new WP_REST_Response(["ok" => false, "message" => "reviews array zorunludur."], 400);
        }

        $inserted = 0; $updated = 0; $failed = 0;
        foreach ($reviews as $review) {
            try {
                $result = ZOLM_Booster_DB::instance()->upsert_review($review);
                if ($result["action"] === "inserted") $inserted++; else $updated++;
            } catch (Exception $e) {
                $failed++;
            }
        }
        return new WP_REST_Response(["ok" => true, "inserted" => $inserted, "updated" => $updated, "failed" => $failed], 200);
    }

    public function list_reviews(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table = ZOLM_Booster_DB::instance()->table();
        $status = $request->get_param("status") ?: "all";
        $limit = min(100, (int) ($request->get_param("per_page") ?: 50));
        $offset = max(0, (int) ($request->get_param("offset") ?: 0));

        $where = "";
        if ($status !== "all") $where = $wpdb->prepare(" WHERE status = %s", $status);
        $sql = "SELECT * FROM {$table}{$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $limit, $offset));

        // Field filtering — sadece istenen alanlar (_fields=id,rating,comment)
        $fields = $request->get_param("_fields");
        if ($fields) {
            $allowed = array_flip(array_map("trim", explode(",", $fields)));
            $rows = array_map(function ($row) use ($allowed) {
                return (object) array_intersect_key((array) $row, $allowed);
            }, $rows);
        }
        return new WP_REST_Response(["ok" => true, "reviews" => $rows], 200);
    }

    public function update_review(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request["id"];
        $data = $request->get_json_params();
        if (isset($data["status"])) {
            ZOLM_Booster_DB::instance()->update_status($id, sanitize_text_field($data["status"]));
        }
        if (isset($data["is_featured"])) {
            ZOLM_Booster_DB::instance()->toggle_featured($id);
        }
        return new WP_REST_Response(["ok" => true], 200);
    }

    public function delete_review(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request["id"];
        ZOLM_Booster_DB::instance()->delete_review($id);
        return new WP_REST_Response(["ok" => true], 200);
    }

    public function get_product_reviews(WP_REST_Request $request): WP_REST_Response {
        $product_id = (int) $request["product_id"];
        $cache = get_transient("zolm_reviews_" . $product_id);
        if ($cache !== false) {
            $response = $cache;
        } else {
            $reviews = ZOLM_Booster_DB::instance()->get_reviews_for_product($product_id);
            $summary = ZOLM_Booster_DB::instance()->get_product_rating_summary($product_id);
            $response = ["ok" => true, "reviews" => $reviews, "summary" => $summary];

            $settings = get_option(ZOLM_BOOSTER_SETTINGS, []);
            $ttl = (int) ($settings["cache_ttl_seconds"] ?? 3600);
            set_transient("zolm_reviews_" . $product_id, $response, $ttl);
        }

        // Field filtering — sadece istenen alanlar
        $fields = $request->get_param("_fields");
        if ($fields) {
            $allowed = array_flip(array_map("trim", explode(",", $fields)));
            $response["reviews"] = array_map(function ($row) use ($allowed) {
                return (object) array_intersect_key((array) $row, $allowed);
            }, $response["reviews"]);
        }
        return new WP_REST_Response($response, 200);
    }

    public function get_product_rating(WP_REST_Request $request): WP_REST_Response {
        $product_id = (int) $request["product_id"];
        $cache = get_transient("zolm_rating_" . $product_id);
        if ($cache !== false) return new WP_REST_Response(["ok" => true, "summary" => $cache], 200);
        $summary = ZOLM_Booster_DB::instance()->get_product_rating_summary($product_id);
        set_transient("zolm_rating_" . $product_id, $summary, HOUR_IN_SECONDS * 6);
        return new WP_REST_Response(["ok" => true, "summary" => $summary], 200);
    }

    public function match_product(WP_REST_Request $request): WP_REST_Response {
        $data = $request->get_json_params();
        $barcode = $data["barcode"] ?? "";
        $sku = $data["sku"] ?? "";
        $product_id = 0;
        if (function_exists("wc_get_product_id_by_sku")) {
            if ($barcode) {
                $product_id = wc_get_product_id_by_sku($barcode);
            }
            if (!$product_id && $sku) {
                $product_id = wc_get_product_id_by_sku($sku);
            }
        }
        if (!$product_id) {
            global $wpdb;
            $lookup = $barcode ?: $sku;
            if ($lookup) {
                $product_id = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                    "_sku", $lookup
                ));
            }
        }
        return new WP_REST_Response(["ok" => true, "product_id" => (int) $product_id], 200);
    }

    public function match_product_get(WP_REST_Request $request): WP_REST_Response {
        $barcode = (string) $request->get_param("barcode") ?? "";
        $sku = (string) $request->get_param("sku") ?? "";
        $product_id = 0;
        if (function_exists("wc_get_product_id_by_sku")) {
            if ($barcode) $product_id = wc_get_product_id_by_sku($barcode);
            if (!$product_id && $sku) $product_id = wc_get_product_id_by_sku($sku);
        }
        if (!$product_id && ($barcode || $sku)) {
            global $wpdb;
            $lookup = $barcode ?: $sku;
            $product_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                "_sku", $lookup
            ));
        }
        return new WP_REST_Response(["ok" => true, "product_id" => (int) $product_id], 200);
    }

    public function get_stats(WP_REST_Request $request): WP_REST_Response {
        $stats = ZOLM_Booster_DB::instance()->get_stats();
        return new WP_REST_Response(["ok" => true, "stats" => $stats], 200);
    }
}
