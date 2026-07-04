<?php
/**
 * Plugin Name: ZOLM WhatsApp Booster
 * Description: ZOLM WhatsApp iletişim modülü için WooCommerce köprüsü. Sipariş, müşteri ve izin sinyallerini ZOLM'a iletir.
 * Version: 1.0.0
 * Author: ZOLM
 * Text Domain: zolm-whatsapp-booster
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

defined('ABSPATH') || exit;

define('ZOLM_WHATSAPP_BOOSTER_VERSION', '1.0.0');
define('ZOLM_WHATSAPP_BOOSTER_FILE', __FILE__);
define('ZOLM_WHATSAPP_BOOSTER_PATH', plugin_dir_path(__FILE__));
define('ZOLM_WHATSAPP_BOOSTER_URL', plugin_dir_url(__FILE__));

/**
 * WooCommerce bağımlılık kontrolü
 */
function zolm_whatsapp_booster_check_wc() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>ZOLM WhatsApp Booster</strong> eklentisi çalışması için ';
            echo '<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a> eklentisinin kurulu ve aktif olması gerekir.';
            echo '</p></div>';
        });
        return false;
    }
    return true;
}

/**
 * WooCommerce ayarları sayfası — lazy loading
 */
function zolm_whatsapp_register_settings_page($settings) {
    if (!class_exists('WC_Settings_Page')) {
        return $settings;
    }

    require_once ZOLM_WHATSAPP_BOOSTER_PATH . 'includes/class-zolm-whatsapp-settings.php';

    if (class_exists('ZOLM_WhatsApp_Settings_Page', false)) {
        $settings[] = new ZOLM_WhatsApp_Settings_Page();
    }

    return $settings;
}

/**
 * Ana plugin sınıfı
 */
final class ZOLM_WhatsApp_Booster {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('init', [$this, 'init']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // WC bağımlılığı yoksa sadece admin notice ve settings hook'u ekle
        if (!zolm_whatsapp_booster_check_wc()) {
            add_filter('woocommerce_get_settings_pages', 'zolm_whatsapp_register_settings_page', 20);
            return;
        }

        // WooCommerce event hooks
        add_action('woocommerce_created_customer', [$this, 'on_customer_created'], 10, 3);
        add_action('woocommerce_order_status_changed', [$this, 'on_order_status_changed'], 10, 4);
        add_action('woocommerce_checkout_order_processed', [$this, 'on_checkout_order_processed'], 10, 3);

        // Sepet event hooks
        add_action('woocommerce_cart_updated', [$this, 'on_cart_updated'], 10);
        add_action('woocommerce_before_checkout_process', [$this, 'on_checkout_contact_captured'], 5);

        // Consent checkbox'ları
        add_action('woocommerce_review_order_before_submit', [$this, 'add_checkout_consent_checkboxes']);
        add_action('woocommerce_checkout_process', [$this, 'validate_checkout_consent']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_checkout_consent']);

        // Registration form consent
        add_action('woocommerce_register_form', [$this, 'add_registration_consent_checkbox']);
        add_action('woocommerce_created_customer', [$this, 'save_registration_consent'], 20, 3);

        // My Account consent management
        add_action('woocommerce_account_dashboard', [$this, 'show_consent_management']);
        add_action('woocommerce_save_account_details', [$this, 'save_account_consent']);

        // Admin settings (lazy load)
        add_filter('woocommerce_get_settings_pages', 'zolm_whatsapp_register_settings_page', 20);

        // Health check AJAX
        add_action('wp_ajax_zolm_wa_test_connection', [$this, 'ajax_test_connection']);
    }

    public function activate() {
        update_option('zolm_whatsapp_booster_version', ZOLM_WHATSAPP_BOOSTER_VERSION);
    }

    public function deactivate() {
        // Cleanup
    }

    public function init() {
        load_plugin_textdomain('zolm-whatsapp-booster', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * REST API rotaları
     */
    public function register_rest_routes() {
        register_rest_route('zolm-wa-booster/v1', '/webhook', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_zolm_webhook'],
            'permission_callback' => [$this, 'verify_zolm_signature'],
        ]);

        register_rest_route('zolm-wa-booster/v1', '/stock-notify/(?P<product_id>\d+)', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_stock_notify_request'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function verify_zolm_signature($request) {
        $signature = $request->get_header('X-ZOLM-Signature');
        $timestamp = $request->get_header('X-ZOLM-Timestamp');
        $secret = get_option('zolm_wa_booster_webhook_secret', '');

        if (empty($signature) || empty($timestamp) || empty($secret)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $body = $request->get_body();
        $expected = 'sha256=' . hash_hmac('sha256', $body, $secret);

        return hash_equals($expected, $signature);
    }

    public function handle_zolm_webhook($request) {
        $payload = $request->get_json_params();
        $eventType = $payload['event_type'] ?? '';

        switch ($eventType) {
            case 'coupon.create':
                $result = $this->create_coupon($payload);
                break;
            case 'coupon.invalidate':
                $result = $this->invalidate_coupon($payload);
                break;
            case 'consent.sync':
                $result = $this->sync_consent($payload);
                break;
            default:
                $result = ['status' => 'unknown_event'];
        }

        return rest_ensure_response($result);
    }

    public function send_signal($eventType, $data) {
        $zolmUrl = get_option('zolm_wa_booster_zolm_url', '');
        $secret = get_option('zolm_wa_booster_webhook_secret', '');
        $storeId = get_option('zolm_wa_booster_store_id', 0);

        if (empty($zolmUrl) || empty($secret)) {
            return false;
        }

        $payload = array_merge([
            'event_type' => $eventType,
            'store_id' => (int) $storeId,
            'timestamp' => time(),
        ], $data);

        $body = wp_json_encode($payload);
        $timestamp = time();
        $signature = 'sha256=' . hash_hmac('sha256', $body, $secret);

        $response = wp_remote_post($zolmUrl, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-ZOLM-Event-ID' => wp_generate_uuid4(),
                'X-ZOLM-Event-Type' => $eventType,
                'X-ZOLM-Timestamp' => (string) $timestamp,
                'X-ZOLM-Signature' => $signature,
                'X-ZOLM-Store-ID' => (string) $storeId,
                'X-ZOLM-Version' => '1.0',
            ],
            'body' => $body,
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            error_log('ZOLM Booster signal error: ' . $response->get_error_message());
            return false;
        }

        return true;
    }

    // ── Sipariş Olayları ──────────────────────────────────────

    public function on_checkout_order_processed($orderId, $data, $order) {
        if (!$order) {
            return;
        }

        $phone = $order->get_billing_phone();
        $consentOrderUpdates = $order->get_meta('_wa_consent_order_updates');
        $consentMarketing = $order->get_meta('_wa_consent_marketing');

        $purposes = [];
        if ($consentOrderUpdates === 'yes') {
            $purposes['order_updates'] = 'granted';
        }
        if ($consentMarketing === 'yes') {
            $purposes['marketing'] = 'granted';
        }

        $this->send_signal('order.created', [
            'wc_customer_id' => (string) $order->get_customer_id(),
            'order_id' => $orderId,
            'order_number' => $order->get_order_number(),
            'order_status' => $order->get_status(),
            'payment_status' => $order->get_status('pay'),
            'order_total' => (float) $order->get_total(),
            'currency' => $order->get_currency(),
            'order_date' => $order->get_date_created() ? $order->get_date_created()->format('Y-m-d H:i:s') : '',
            'customer_phone' => $phone,
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'communication_purposes' => $purposes,
            'guest_checkout' => $order->get_customer_id() === 0,
        ]);
    }

    public function on_order_status_changed($orderId, $from, $to, $order) {
        $this->send_signal('order.status_changed', [
            'order_id' => $orderId,
            'order_number' => $order->get_order_number(),
            'old_status' => $from,
            'new_status' => $to,
            'order_status' => $order->get_status(),
            'order_total' => (float) $order->get_total(),
            'currency' => $order->get_currency(),
            'customer_phone' => $order->get_billing_phone(),
        ]);
    }

    // ── Sepet Olayları ──────────────────────────────────────

    public function on_cart_updated() {
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return;
        }

        $cartItems = [];
        foreach ($cart->get_cart() as $cartItem) {
            $cartItems[] = [
                'product_id' => $cartItem['product_id'],
                'variation_id' => $cartItem['variation_id'] ?? 0,
                'quantity' => $cartItem['quantity'],
                'product_url' => get_permalink($cartItem['product_id']),
            ];
        }

        $userId = get_current_user_id();
        $phone = $userId ? get_user_meta($userId, 'billing_phone', true) : '';
        $email = $userId ? get_userdata($userId)->user_email : WC()->checkout->get_value('billing_email');
        $cartRecoveryConsent = $userId ? (get_user_meta($userId, '_wa_consent_order_updates', true) ?: 'no') : 'no';

        $this->send_signal('cart.updated', [
            'wc_customer_id' => $userId ? (string) $userId : null,
            'guest_checkout' => $userId === 0,
            'phone' => $phone,
            'billing_email' => $email,
            'cart_key' => $cart->get_cart_hash(),
            'cart_items' => $cartItems,
            'cart_total' => (float) $cart->get_cart_contents_total(),
            'currency' => get_woocommerce_currency(),
            'cart_recovery_consent' => $cartRecoveryConsent === 'yes' ? 'granted' : 'withdrawn',
            'consent_source' => 'checkout',
            'consent_timestamp' => current_time('mysql'),
        ]);
    }

    public function on_checkout_contact_captured() {
        if (!WC()->checkout) {
            return;
        }

        $email = WC()->checkout->get_value('billing_email');
        $phone = WC()->checkout->get_value('billing_phone');

        if (empty($email) && empty($phone)) {
            return;
        }

        $userId = get_current_user_id();

        $this->send_signal('cart.contact_captured', [
            'wc_customer_id' => $userId ? (string) $userId : null,
            'phone' => $phone,
            'billing_email' => $email,
            'cart_key' => WC()->cart ? WC()->cart->get_cart_hash() : '',
            'guest_checkout' => $userId === 0,
        ]);
    }

    // ── Müşteri Olayları ──────────────────────────────────────

    public function on_customer_created($customerId, $new_customer_data, $password_generated) {
        $user = get_user_by('id', $customerId);
        if (!$user) {
            return;
        }

        $phone = get_user_meta($customerId, 'billing_phone', true);
        $consentOrderUpdates = get_user_meta($customerId, '_wa_consent_order_updates', true);
        $consentMarketing = get_user_meta($customerId, '_wa_consent_marketing', true);

        $purposes = [];
        if ($consentOrderUpdates === 'yes') {
            $purposes['order_updates'] = 'granted';
        }
        if ($consentMarketing === 'yes') {
            $purposes['marketing'] = 'granted';
        }

        $this->send_signal('customer.created', [
            'wc_customer_id' => (string) $customerId,
            'customer_phone' => $phone,
            'customer_name' => $user->display_name,
            'communication_purposes' => $purposes,
            'guest_checkout' => false,
        ]);
    }

    // ── Consent Checkbox'ları ─────────────────────────────────

    public function add_checkout_consent_checkboxes() {
        echo '<div id="wa-consent-checkboxes" style="margin-bottom: 15px; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">';
        echo '<h3>' . esc_html__('İletişim Tercihleri', 'zolm-whatsapp-booster') . '</h3>';

        woocommerce_form_field('wa_consent_order_updates', [
            'type' => 'checkbox',
            'class' => ['form-row-wide'],
            'label' => __('Kargo ve sipariş durumundan WhatsApp ile haberdar olmak istiyorum.', 'zolm-whatsapp-booster'),
            'required' => false,
        ]);

        woocommerce_form_field('wa_consent_marketing', [
            'type' => 'checkbox',
            'class' => ['form-row-wide'],
            'label' => __('Kampanya, indirim ve yeni ürünlerden WhatsApp ile haberdar olmak istiyorum.', 'zolm-whatsapp-booster'),
            'required' => false,
        ]);

        echo '</div>';
    }

    public function validate_checkout_consent() {
        // Consent zorunlu değildir
    }

    public function save_checkout_consent($orderId) {
        $orderUpdates = isset($_POST['wa_consent_order_updates']) ? 'yes' : 'no';
        $marketing = isset($_POST['wa_consent_marketing']) ? 'yes' : 'no';

        update_post_meta($orderId, '_wa_consent_order_updates', $orderUpdates);
        update_post_meta($orderId, '_wa_consent_marketing', $marketing);
    }

    // ── Registration Consent ──────────────────────────────────

    public function add_registration_consent_checkbox() {
        woocommerce_form_field('wa_consent_order_updates_reg', [
            'type' => 'checkbox',
            'class' => ['form-row-wide'],
            'label' => __('Kargo ve sipariş durumundan WhatsApp ile haberdar olmak istiyorum.', 'zolm-whatsapp-booster'),
            'required' => false,
        ]);

        woocommerce_form_field('wa_consent_marketing_reg', [
            'type' => 'checkbox',
            'class' => ['form-row-wide'],
            'label' => __('Kampanya, indirim ve yeni ürünlerden WhatsApp ile haberdar olmak istiyorum.', 'zolm-whatsapp-booster'),
            'required' => false,
        ]);
    }

    public function save_registration_consent($customerId, $new_customer_data, $password_generated) {
        $orderUpdates = isset($_POST['wa_consent_order_updates_reg']) ? 'yes' : 'no';
        $marketing = isset($_POST['wa_consent_marketing_reg']) ? 'yes' : 'no';

        update_user_meta($customerId, '_wa_consent_order_updates', $orderUpdates);
        update_user_meta($customerId, '_wa_consent_marketing', $marketing);
    }

    // ── My Account Consent Management ─────────────────────────

    public function show_consent_management() {
        $userId = get_current_user_id();
        $orderUpdates = get_user_meta($userId, '_wa_consent_order_updates', true) ?: 'no';
        $marketing = get_user_meta($userId, '_wa_consent_marketing', true) ?: 'no';
        ?>
        <h3><?php esc_html_e('WhatsApp İletişim Tercihleri', 'zolm-whatsapp-booster'); ?></h3>
        <form method="post" action="">
            <?php wp_nonce_field('zolm_wa_consent_save', 'zolm_wa_consent_nonce'); ?>
            <p>
                <label>
                    <input type="checkbox" name="wa_consent_order_updates" value="yes" <?php checked($orderUpdates, 'yes'); ?>>
                    <?php esc_html_e('Kargo ve sipariş durumundan WhatsApp ile haberdar olmak istiyorum.', 'zolm-whatsapp-booster'); ?>
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="wa_consent_marketing" value="yes" <?php checked($marketing, 'yes'); ?>>
                    <?php esc_html_e('Kampanya, indirim ve yeni ürünlerden WhatsApp ile haberdar olmak istiyorum.', 'zolm-whatsapp-booster'); ?>
                </label>
            </p>
            <p>
                <button type="submit" class="woocommerce-button button" name="zolm_wa_consent_update">
                    <?php esc_html_e('Tercihlerimi Güncelle', 'zolm-whatsapp-booster'); ?>
                </button>
            </p>
        </form>
        <?php
    }

    public function save_account_consent($userId) {
        if (!isset($_POST['zolm_wa_consent_update'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['zolm_wa_consent_nonce'] ?? '', 'zolm_wa_consent_save')) {
            return;
        }

        $orderUpdates = isset($_POST['wa_consent_order_updates']) ? 'yes' : 'no';
        $marketing = isset($_POST['wa_consent_marketing']) ? 'yes' : 'no';

        $oldOrderUpdates = get_user_meta($userId, '_wa_consent_order_updates', true) ?: 'no';
        $oldMarketing = get_user_meta($userId, '_wa_consent_marketing', true) ?: 'no';

        update_user_meta($userId, '_wa_consent_order_updates', $orderUpdates);
        update_user_meta($userId, '_wa_consent_marketing', $marketing);

        $phone = get_user_meta($userId, 'billing_phone', true);

        if ($orderUpdates !== $oldOrderUpdates || $marketing !== $oldMarketing) {
            $purposes = [];
            if ($orderUpdates !== $oldOrderUpdates) {
                $purposes['order_updates'] = $orderUpdates === 'yes' ? 'granted' : 'withdrawn';
            }
            if ($marketing !== $oldMarketing) {
                $purposes['marketing'] = $marketing === 'yes' ? 'granted' : 'withdrawn';
            }

            $this->send_signal('order.communication_preferences_synced', [
                'wc_customer_id' => (string) $userId,
                'phone' => $phone,
                'order_updates_consent' => $orderUpdates === 'yes' ? 'granted' : 'withdrawn',
            ]);
        }
    }

    // ── ZOLM Komutları ───────────────────────────────────────

    private function create_coupon($payload) {
        $code = $payload['coupon_code'] ?? '';
        $discountType = $payload['discount_type'] ?? 'percent';
        $discountValue = $payload['discount_value'] ?? 0;
        $minimumSpend = $payload['minimum_spend'] ?? 0;
        $expiryDate = $payload['expires_at'] ?? '';
        $usageLimit = $payload['usage_limit'] ?? 1;

        if (empty($code)) {
            return ['status' => 'error', 'message' => 'coupon_code required'];
        }

        $coupon = new WC_Coupon();
        $coupon->set_code($code);
        $coupon->set_discount_type($discountType);
        $coupon->set_amount($discountValue);
        $coupon->set_minimum_amount($minimumSpend);
        $coupon->set_usage_limit($usageLimit);
        $coupon->set_individual_use(true);

        if ($expiryDate) {
            $coupon->set_date_expires(strtotime($expiryDate));
        }

        $couponId = $coupon->save();

        return [
            'status' => 'ok',
            'coupon_id' => $couponId,
            'coupon_code' => $code,
        ];
    }

    private function invalidate_coupon($payload) {
        $couponId = $payload['coupon_id'] ?? 0;

        if (!$couponId) {
            return ['status' => 'error', 'message' => 'coupon_id required'];
        }

        $coupon = new WC_Coupon($couponId);
        $coupon->set_date_expires(time() - 3600);
        $coupon->save();

        return ['status' => 'ok'];
    }

    private function sync_consent($payload) {
        $wcCustomerId = $payload['wc_customer_id'] ?? 0;
        $purposes = $payload['purposes'] ?? [];

        if (!$wcCustomerId || empty($purposes)) {
            return ['status' => 'error', 'message' => 'invalid payload'];
        }

        foreach ($purposes as $purpose => $status) {
            $metaKey = '_wa_consent_' . str_replace('-', '_', $purpose);
            update_user_meta($wcCustomerId, $metaKey, $status === 'granted' ? 'yes' : 'no');
        }

        return ['status' => 'ok'];
    }

    // ── Stok Bildirim Formu ──────────────────────────────────

    public function handle_stock_notify_request($request) {
        $productId = $request->get_param('product_id');
        $phone = sanitize_text_field($request->get_param('phone') ?? '');
        $variationId = (int) ($request->get_param('variation_id') ?? 0);
        $honeypot = sanitize_text_field($request->get_param('website') ?? '');

        // Honeypot
        if (!empty($honeypot)) {
            return rest_ensure_response(['status' => 'ok']);
        }

        if (empty($phone)) {
            return rest_ensure_response(['status' => 'error', 'message' => 'phone required'], 400);
        }

        $this->send_signal('stock.waitlist.created', [
            'product_id' => (int) $productId,
            'variation_id' => $variationId,
            'phone' => $phone,
        ]);

        return rest_ensure_response(['status' => 'ok']);
    }

    // ── Bağlantı Testi ───────────────────────────────────────

    public function ajax_test_connection() {
        // Yetki kontrolü
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Yetkiniz yok.'], 403);
        }

        // Nonce doğrulama
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'zolm_wa_test_connection')) {
            wp_send_json_error(['message' => 'Nonce doğrulaması başarısız.'], 403);
        }

        $zolmUrl = get_option('zolm_wa_booster_zolm_url', '');
        $secret = get_option('zolm_wa_booster_webhook_secret', '');
        $storeId = (int) get_option('zolm_wa_booster_store_id', 0);

        if (empty($zolmUrl) || empty($secret) || $storeId <= 0) {
            wp_send_json_error(['message' => 'ZOLM URL, Webhook Secret ve Store ID girilmeli.']);
        }

        // Health check payload'ı
        $payload = wp_json_encode(['event_type' => 'health.check', 'store_id' => $storeId]);
        $timestamp = (string) time();
        $signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        $eventId = wp_generate_uuid4();

        $response = wp_remote_post($zolmUrl, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-ZOLM-Event-ID' => $eventId,
                'X-ZOLM-Event-Type' => 'health.check',
                'X-ZOLM-Timestamp' => $timestamp,
                'X-ZOLM-Signature' => $signature,
                'X-ZOLM-Store-ID' => (string) $storeId,
                'X-ZOLM-Version' => '1.0',
            ],
            'body' => $payload,
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Bağlantı kurulamadı: ' . $response->get_error_message()]);
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode === 200 && !empty($body['ok'])) {
            wp_send_json_success(['message' => 'Bağlantı başarılı! Store ID: ' . $body['store_id']]);
        } else {
            $safeMsg = $body['error'] ?? 'Bilinmeyen hata (HTTP ' . $statusCode . ')';
            wp_send_json_error(['message' => $safeMsg]);
        }
    }
}

// Plugin'i başlat
ZOLM_WhatsApp_Booster::instance();

// Stok bildirim formu shortcode'u (WC bağımlılığı yok, her zaman yükle)
require_once ZOLM_WHATSAPP_BOOSTER_PATH . 'stock-notify-form.php';

// Yüzen WhatsApp butonu widget'ı
require_once ZOLM_WHATSAPP_BOOSTER_PATH . 'whatsapp-floating-button.php';
