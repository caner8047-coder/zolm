<?php

if (! defined("ABSPATH")) exit;

class ZOLM_Booster_WhatsApp_Module {
    private static $instance = null;

    public static function instance(): self {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public static function register_settings(): void {
        register_setting("zolm_booster_whatsapp_settings_group", "zolm_wa_booster_zolm_url", [
            "type" => "string",
            "sanitize_callback" => "esc_url_raw",
            "default" => "",
        ]);
        register_setting("zolm_booster_whatsapp_settings_group", "zolm_wa_booster_webhook_secret", [
            "type" => "string",
            "sanitize_callback" => "sanitize_text_field",
            "default" => "",
        ]);
        register_setting("zolm_booster_whatsapp_settings_group", "zolm_wa_booster_store_id", [
            "type" => "integer",
            "sanitize_callback" => "absint",
            "default" => 0,
        ]);
        register_setting("zolm_booster_whatsapp_settings_group", "zolm_wa_booster_test_mode", [
            "type" => "string",
            "sanitize_callback" => [__CLASS__, "sanitize_checkbox"],
            "default" => "no",
        ]);
    }

    public static function sanitize_checkbox($value): string {
        return in_array($value, ["1", 1, "yes", true], true) ? "yes" : "no";
    }

    public static function migrate_legacy_options(): void {
        $legacyKeys = [
            "zolm_wa_booster_zolm_url",
            "zolm_wa_booster_webhook_secret",
            "zolm_wa_booster_store_id",
            "zolm_wa_booster_test_mode",
        ];

        foreach ($legacyKeys as $key) {
            $value = get_option($key, null);
            if ($value !== null) update_option($key, $value);
        }
    }

    private function __construct() {
        add_action("rest_api_init", [$this, "register_rest_routes"]);
        add_action("wp_ajax_zolm_wa_test_connection", [$this, "ajax_test_connection"]);

        if (! class_exists("WooCommerce")) return;

        add_action("woocommerce_created_customer", [$this, "on_customer_created"], 10, 3);
        add_action("woocommerce_order_status_changed", [$this, "on_order_status_changed"], 10, 4);
        add_action("woocommerce_checkout_order_processed", [$this, "on_checkout_order_processed"], 10, 3);
        add_action("woocommerce_cart_updated", [$this, "on_cart_updated"], 10);
        add_action("woocommerce_before_checkout_process", [$this, "on_checkout_contact_captured"], 5);
        add_action("woocommerce_review_order_before_submit", [$this, "add_checkout_consent_checkboxes"]);
        add_action("woocommerce_checkout_process", [$this, "validate_checkout_consent"]);
        add_action("woocommerce_checkout_update_order_meta", [$this, "save_checkout_consent"]);
        add_action("woocommerce_register_form", [$this, "add_registration_consent_checkbox"]);
        add_action("woocommerce_created_customer", [$this, "save_registration_consent"], 20, 3);
        add_action("woocommerce_account_dashboard", [$this, "show_consent_management"]);
        add_action("woocommerce_save_account_details", [$this, "save_account_consent"]);
    }

    public function register_rest_routes(): void {
        register_rest_route("zolm-wa-booster/v1", "/webhook", [
            "methods" => "POST",
            "callback" => [$this, "handle_zolm_webhook"],
            "permission_callback" => [$this, "verify_zolm_signature"],
        ]);

        register_rest_route("zolm-wa-booster/v1", "/stock-notify/(?P<product_id>\d+)", [
            "methods" => "POST",
            "callback" => [$this, "handle_stock_notify_request"],
            "permission_callback" => "__return_true",
        ]);
    }

    public function verify_zolm_signature(WP_REST_Request $request): bool {
        $signature = $request->get_header("X-ZOLM-Signature");
        $timestamp = $request->get_header("X-ZOLM-Timestamp");
        $secret = get_option("zolm_wa_booster_webhook_secret", "");

        if (empty($signature) || empty($timestamp) || empty($secret)) return false;
        if (abs(time() - (int) $timestamp) > 300) return false;

        $expected = "sha256=" . hash_hmac("sha256", $request->get_body(), $secret);
        return hash_equals($expected, $signature);
    }

    public function handle_zolm_webhook(WP_REST_Request $request): WP_REST_Response {
        $payload = $request->get_json_params();
        $eventType = $payload["event_type"] ?? "";

        switch ($eventType) {
            case "coupon.create":
                $result = $this->create_coupon($payload);
                break;
            case "coupon.invalidate":
                $result = $this->invalidate_coupon($payload);
                break;
            case "consent.sync":
                $result = $this->sync_consent($payload);
                break;
            default:
                $result = ["status" => "unknown_event"];
        }

        return new WP_REST_Response($result, 200);
    }

    public function send_signal(string $eventType, array $data): bool {
        $zolmUrl = get_option("zolm_wa_booster_zolm_url", "");
        $secret = get_option("zolm_wa_booster_webhook_secret", "");
        $storeId = (int) get_option("zolm_wa_booster_store_id", 0);

        if (empty($zolmUrl) || empty($secret)) return false;

        $payload = array_merge([
            "event_type" => $eventType,
            "store_id" => $storeId,
            "timestamp" => time(),
        ], $data);

        $body = wp_json_encode($payload);
        $timestamp = time();
        $signature = "sha256=" . hash_hmac("sha256", $body, $secret);
        $eventId = wp_generate_uuid4();

        if (get_option("zolm_wa_booster_test_mode", "no") === "yes") {
            error_log("ZOLM Booster WhatsApp test mode signal: " . $eventType);
            return true;
        }

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $response = wp_remote_post($zolmUrl, [
                "headers" => [
                    "Content-Type" => "application/json",
                    "X-ZOLM-Event-ID" => $eventId,
                    "X-ZOLM-Event-Type" => $eventType,
                    "X-ZOLM-Timestamp" => (string) $timestamp,
                    "X-ZOLM-Signature" => $signature,
                    "X-ZOLM-Store-ID" => (string) $storeId,
                    "X-ZOLM-Version" => ZOLM_BOOSTER_VERSION,
                    "X-ZOLM-Retry" => (string) $attempt,
                ],
                "body" => $body,
                "timeout" => 15,
            ]);

            if (! is_wp_error($response)) {
                $statusCode = wp_remote_retrieve_response_code($response);
                if ($statusCode >= 200 && $statusCode < 300) return true;

                if ($statusCode === 429) {
                    $retryAfter = (int) wp_remote_retrieve_header($response, "retry-after");
                    sleep($retryAfter > 0 ? $retryAfter : 5 * $attempt);
                    continue;
                }
            }

            if ($attempt < 3) sleep(5 * $attempt);
        }

        error_log("ZOLM Booster WhatsApp signal failed after retries: " . $eventType);
        return false;
    }

    public function on_checkout_order_processed($orderId, $data, $order): void {
        if (! $order) return;

        $purposes = [];
        if ($order->get_meta("_wa_consent_order_updates") === "yes") $purposes["order_updates"] = "granted";
        if ($order->get_meta("_wa_consent_marketing") === "yes") $purposes["marketing"] = "granted";

        $this->send_signal("order.created", [
            "wc_customer_id" => (string) $order->get_customer_id(),
            "order_id" => $orderId,
            "order_number" => $order->get_order_number(),
            "order_status" => $order->get_status(),
            "payment_status" => $order->get_status("pay"),
            "order_total" => (float) $order->get_total(),
            "currency" => $order->get_currency(),
            "order_date" => $order->get_date_created() ? $order->get_date_created()->format("Y-m-d H:i:s") : "",
            "customer_phone" => $order->get_billing_phone(),
            "customer_name" => trim($order->get_billing_first_name() . " " . $order->get_billing_last_name()),
            "communication_purposes" => $purposes,
            "guest_checkout" => $order->get_customer_id() === 0,
        ]);
    }

    public function on_order_status_changed($orderId, $from, $to, $order): void {
        if (! $order) return;

        $this->send_signal("order.status_changed", [
            "order_id" => $orderId,
            "order_number" => $order->get_order_number(),
            "old_status" => $from,
            "new_status" => $to,
            "order_status" => $order->get_status(),
            "order_total" => (float) $order->get_total(),
            "currency" => $order->get_currency(),
            "customer_phone" => $order->get_billing_phone(),
        ]);
    }

    public function on_cart_updated(): void {
        if (is_admin() && ! wp_doing_ajax()) return;
        if (! function_exists("WC") || ! WC()->cart || WC()->cart->is_empty()) return;

        $cartItems = [];
        foreach (WC()->cart->get_cart() as $cartItem) {
            $cartItems[] = [
                "product_id" => $cartItem["product_id"],
                "variation_id" => $cartItem["variation_id"] ?? 0,
                "quantity" => $cartItem["quantity"],
                "product_url" => get_permalink($cartItem["product_id"]),
            ];
        }

        $userId = get_current_user_id();
        $user = $userId ? get_userdata($userId) : null;
        $checkout = WC()->checkout();
        $email = $user ? $user->user_email : ($checkout ? $checkout->get_value("billing_email") : "");
        $phone = $userId ? get_user_meta($userId, "billing_phone", true) : "";
        $cartRecoveryConsent = $userId ? (get_user_meta($userId, "_wa_consent_order_updates", true) ?: "no") : "no";

        $this->send_signal("cart.updated", [
            "wc_customer_id" => $userId ? (string) $userId : null,
            "guest_checkout" => $userId === 0,
            "phone" => $phone,
            "billing_email" => $email,
            "cart_key" => WC()->cart->get_cart_hash(),
            "cart_items" => $cartItems,
            "cart_total" => (float) WC()->cart->get_cart_contents_total(),
            "currency" => get_woocommerce_currency(),
            "cart_recovery_consent" => $cartRecoveryConsent === "yes" ? "granted" : "withdrawn",
            "consent_source" => "checkout",
            "consent_timestamp" => current_time("mysql"),
        ]);
    }

    public function on_checkout_contact_captured(): void {
        if (! function_exists("WC") || ! WC()->checkout()) return;

        $email = WC()->checkout()->get_value("billing_email");
        $phone = WC()->checkout()->get_value("billing_phone");
        if (empty($email) && empty($phone)) return;

        $userId = get_current_user_id();
        $this->send_signal("cart.contact_captured", [
            "wc_customer_id" => $userId ? (string) $userId : null,
            "phone" => $phone,
            "billing_email" => $email,
            "cart_key" => WC()->cart ? WC()->cart->get_cart_hash() : "",
            "guest_checkout" => $userId === 0,
        ]);
    }

    public function on_customer_created($customerId, $newCustomerData, $passwordGenerated): void {
        $user = get_user_by("id", $customerId);
        if (! $user) return;

        $purposes = [];
        if (get_user_meta($customerId, "_wa_consent_order_updates", true) === "yes") $purposes["order_updates"] = "granted";
        if (get_user_meta($customerId, "_wa_consent_marketing", true) === "yes") $purposes["marketing"] = "granted";

        $this->send_signal("customer.created", [
            "wc_customer_id" => (string) $customerId,
            "customer_phone" => get_user_meta($customerId, "billing_phone", true),
            "customer_name" => $user->display_name,
            "communication_purposes" => $purposes,
            "guest_checkout" => false,
        ]);
    }

    public function add_checkout_consent_checkboxes(): void {
        echo '<div id="wa-consent-checkboxes" style="margin-bottom:15px;padding:15px;border:1px solid #ddd;border-radius:4px;">';
        echo '<h3>' . esc_html__("İletişim Tercihleri", "zolm-booster") . '</h3>';
        woocommerce_form_field("wa_consent_order_updates", [
            "type" => "checkbox",
            "class" => ["form-row-wide"],
            "label" => __("Kargo ve sipariş durumundan WhatsApp ile haberdar olmak istiyorum.", "zolm-booster"),
            "required" => false,
        ]);
        woocommerce_form_field("wa_consent_marketing", [
            "type" => "checkbox",
            "class" => ["form-row-wide"],
            "label" => __("Kampanya, indirim ve yeni ürünlerden WhatsApp ile haberdar olmak istiyorum.", "zolm-booster"),
            "required" => false,
        ]);
        echo '</div>';
    }

    public function validate_checkout_consent(): void {
    }

    public function save_checkout_consent($orderId): void {
        update_post_meta($orderId, "_wa_consent_order_updates", isset($_POST["wa_consent_order_updates"]) ? "yes" : "no");
        update_post_meta($orderId, "_wa_consent_marketing", isset($_POST["wa_consent_marketing"]) ? "yes" : "no");
    }

    public function add_registration_consent_checkbox(): void {
        woocommerce_form_field("wa_consent_order_updates_reg", [
            "type" => "checkbox",
            "class" => ["form-row-wide"],
            "label" => __("Kargo ve sipariş durumundan WhatsApp ile haberdar olmak istiyorum.", "zolm-booster"),
            "required" => false,
        ]);
        woocommerce_form_field("wa_consent_marketing_reg", [
            "type" => "checkbox",
            "class" => ["form-row-wide"],
            "label" => __("Kampanya, indirim ve yeni ürünlerden WhatsApp ile haberdar olmak istiyorum.", "zolm-booster"),
            "required" => false,
        ]);
    }

    public function save_registration_consent($customerId, $newCustomerData, $passwordGenerated): void {
        update_user_meta($customerId, "_wa_consent_order_updates", isset($_POST["wa_consent_order_updates_reg"]) ? "yes" : "no");
        update_user_meta($customerId, "_wa_consent_marketing", isset($_POST["wa_consent_marketing_reg"]) ? "yes" : "no");
    }

    public function show_consent_management(): void {
        $userId = get_current_user_id();
        $orderUpdates = get_user_meta($userId, "_wa_consent_order_updates", true) ?: "no";
        $marketing = get_user_meta($userId, "_wa_consent_marketing", true) ?: "no";
        ?>
        <h3><?php esc_html_e("WhatsApp İletişim Tercihleri", "zolm-booster"); ?></h3>
        <form method="post" action="">
            <?php wp_nonce_field("zolm_wa_consent_save", "zolm_wa_consent_nonce"); ?>
            <p><label><input type="checkbox" name="wa_consent_order_updates" value="yes" <?php checked($orderUpdates, "yes"); ?>> <?php esc_html_e("Kargo ve sipariş durumundan WhatsApp ile haberdar olmak istiyorum.", "zolm-booster"); ?></label></p>
            <p><label><input type="checkbox" name="wa_consent_marketing" value="yes" <?php checked($marketing, "yes"); ?>> <?php esc_html_e("Kampanya, indirim ve yeni ürünlerden WhatsApp ile haberdar olmak istiyorum.", "zolm-booster"); ?></label></p>
            <p><button type="submit" class="woocommerce-button button" name="zolm_wa_consent_update"><?php esc_html_e("Tercihlerimi Güncelle", "zolm-booster"); ?></button></p>
        </form>
        <?php
    }

    public function save_account_consent($userId): void {
        if (! isset($_POST["zolm_wa_consent_update"])) return;
        if (! wp_verify_nonce($_POST["zolm_wa_consent_nonce"] ?? "", "zolm_wa_consent_save")) return;

        $orderUpdates = isset($_POST["wa_consent_order_updates"]) ? "yes" : "no";
        $marketing = isset($_POST["wa_consent_marketing"]) ? "yes" : "no";
        $oldOrderUpdates = get_user_meta($userId, "_wa_consent_order_updates", true) ?: "no";
        $oldMarketing = get_user_meta($userId, "_wa_consent_marketing", true) ?: "no";

        update_user_meta($userId, "_wa_consent_order_updates", $orderUpdates);
        update_user_meta($userId, "_wa_consent_marketing", $marketing);

        if ($orderUpdates !== $oldOrderUpdates || $marketing !== $oldMarketing) {
            $this->send_signal("order.communication_preferences_synced", [
                "wc_customer_id" => (string) $userId,
                "phone" => get_user_meta($userId, "billing_phone", true),
                "order_updates_consent" => $orderUpdates === "yes" ? "granted" : "withdrawn",
            ]);
        }
    }

    public function handle_stock_notify_request(WP_REST_Request $request): WP_REST_Response {
        $honeypot = sanitize_text_field($request->get_param("website") ?? "");
        if (! empty($honeypot)) return new WP_REST_Response(["status" => "ok"], 200);

        $phone = sanitize_text_field($request->get_param("phone") ?? "");
        $consent = sanitize_text_field($request->get_param("stock_alert_consent") ?? "");
        if (empty($phone)) return new WP_REST_Response(["status" => "error", "message" => "phone required"], 400);
        if ($consent !== "yes") return new WP_REST_Response(["status" => "error", "message" => "consent required"], 400);

        $this->send_signal("stock.waitlist.created", [
            "product_id" => (int) $request->get_param("product_id"),
            "variation_id" => (int) ($request->get_param("variation_id") ?? 0),
            "phone" => $phone,
            "stock_alert_consent" => "granted",
            "consent_source" => "stock_notify_form",
            "consent_timestamp" => current_time("mysql"),
        ]);

        return new WP_REST_Response(["status" => "ok"], 200);
    }

    public function ajax_test_connection(): void {
        if (! current_user_can("manage_woocommerce") && ! current_user_can("manage_options")) {
            wp_send_json_error(["message" => "Yetkiniz yok."], 403);
        }

        if (! wp_verify_nonce($_POST["nonce"] ?? "", "zolm_wa_test_connection")) {
            wp_send_json_error(["message" => "Nonce doğrulaması başarısız."], 403);
        }

        $zolmUrl = get_option("zolm_wa_booster_zolm_url", "");
        $secret = get_option("zolm_wa_booster_webhook_secret", "");
        $storeId = (int) get_option("zolm_wa_booster_store_id", 0);

        if (empty($zolmUrl) || empty($secret) || $storeId <= 0) {
            wp_send_json_error(["message" => "ZOLM URL, Webhook Secret ve Store ID girilmeli."]);
        }

        $payload = wp_json_encode(["event_type" => "health.check", "store_id" => $storeId]);
        $timestamp = (string) time();
        $response = wp_remote_post($zolmUrl, [
            "headers" => [
                "Content-Type" => "application/json",
                "X-ZOLM-Event-ID" => wp_generate_uuid4(),
                "X-ZOLM-Event-Type" => "health.check",
                "X-ZOLM-Timestamp" => $timestamp,
                "X-ZOLM-Signature" => "sha256=" . hash_hmac("sha256", $payload, $secret),
                "X-ZOLM-Store-ID" => (string) $storeId,
                "X-ZOLM-Version" => ZOLM_BOOSTER_VERSION,
            ],
            "body" => $payload,
            "timeout" => 15,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(["message" => "Bağlantı kurulamadı: " . $response->get_error_message()]);
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode === 200 && ! empty($body["ok"])) {
            wp_send_json_success(["message" => "Bağlantı başarılı! Store ID: " . ($body["store_id"] ?? $storeId)]);
        }

        wp_send_json_error(["message" => ($body["error"] ?? "Bilinmeyen hata (HTTP " . $statusCode . ")")]);
    }

    private function create_coupon(array $payload): array {
        if (empty($payload["coupon_code"])) return ["status" => "error", "message" => "coupon_code required"];
        if (! class_exists("WC_Coupon")) return ["status" => "error", "message" => "WooCommerce not available"];

        $coupon = new WC_Coupon();
        $coupon->set_code($payload["coupon_code"]);
        $coupon->set_discount_type($payload["discount_type"] ?? "percent");
        $coupon->set_amount($payload["discount_value"] ?? 0);
        $coupon->set_minimum_amount($payload["minimum_spend"] ?? 0);
        $coupon->set_usage_limit($payload["usage_limit"] ?? 1);
        $coupon->set_individual_use(true);
        if (! empty($payload["expires_at"])) $coupon->set_date_expires(strtotime($payload["expires_at"]));

        return ["status" => "ok", "coupon_id" => $coupon->save(), "coupon_code" => $payload["coupon_code"]];
    }

    private function invalidate_coupon(array $payload): array {
        if (empty($payload["coupon_id"])) return ["status" => "error", "message" => "coupon_id required"];
        if (! class_exists("WC_Coupon")) return ["status" => "error", "message" => "WooCommerce not available"];

        $coupon = new WC_Coupon((int) $payload["coupon_id"]);
        $coupon->set_date_expires(time() - 3600);
        $coupon->save();
        return ["status" => "ok"];
    }

    private function sync_consent(array $payload): array {
        $wcCustomerId = (int) ($payload["wc_customer_id"] ?? 0);
        $purposes = $payload["purposes"] ?? [];
        if (! $wcCustomerId || empty($purposes) || ! is_array($purposes)) {
            return ["status" => "error", "message" => "invalid payload"];
        }

        foreach ($purposes as $purpose => $status) {
            update_user_meta($wcCustomerId, "_wa_consent_" . str_replace("-", "_", $purpose), $status === "granted" ? "yes" : "no");
        }

        return ["status" => "ok"];
    }
}
