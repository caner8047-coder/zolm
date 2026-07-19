<?php
/**
 * Plugin Name: ZOLM Booster
 * Description: ZOLM WooCommerce köprüsü. Trendyol yorum widget'ı, WhatsApp otomasyonları ve yeni modülleri tek çatı altında yönetir.
 * Version: 1.2.0
 * Author: ZOLM
 * License: proprietary
 * WC_requires_at_least: 8.0
 * Requires at least: 6.0
 */

if (! defined("ABSPATH")) exit;

define("ZOLM_BOOSTER_VERSION", "1.2.0");
define("ZOLM_BOOSTER_PATH", plugin_dir_path(__FILE__));
define("ZOLM_BOOSTER_URL", plugin_dir_url(__FILE__));
define("ZOLM_BOOSTER_DB_TABLE", "zolm_booster_reviews");
define("ZOLM_BOOSTER_OPTION_KEY", "zolm_booster_api_key");
define("ZOLM_BOOSTER_SETTINGS", "zolm_booster_settings");

require_once ZOLM_BOOSTER_PATH . "includes/class-zolm-booster-modules.php";
require_once ZOLM_BOOSTER_PATH . "includes/class-zolm-booster-db.php";
require_once ZOLM_BOOSTER_PATH . "includes/class-zolm-booster-api.php";
require_once ZOLM_BOOSTER_PATH . "includes/class-zolm-booster-widget.php";
require_once ZOLM_BOOSTER_PATH . "includes/class-zolm-booster-badge.php";
require_once ZOLM_BOOSTER_PATH . "includes/class-zolm-booster-admin.php";
require_once ZOLM_BOOSTER_PATH . "modules/whatsapp/class-zolm-booster-whatsapp-module.php";
require_once ZOLM_BOOSTER_PATH . "modules/whatsapp/class-zolm-booster-whatsapp-stock-notify.php";
require_once ZOLM_BOOSTER_PATH . "modules/whatsapp/class-zolm-booster-whatsapp-floating-button.php";

register_activation_hook(__FILE__, function () {
    ZOLM_Booster_DB::create_table();
    ZOLM_Booster_Modules::activate_defaults();
    ZOLM_Booster_WhatsApp_Module::migrate_legacy_options();
});

add_action("init", function () {
    $modules = ZOLM_Booster_Modules::instance();

    if ($modules->is_enabled("trendyol_reviews")) {
        ZOLM_Booster_DB::instance();
        ZOLM_Booster_API::instance();
        ZOLM_Booster_Widget::instance();
        ZOLM_Booster_Badge::instance();
    }

    if ($modules->is_enabled("whatsapp") && ! $modules->legacy_whatsapp_plugin_active()) {
        ZOLM_Booster_WhatsApp_Module::instance();
        ZOLM_Booster_WhatsApp_Stock_Notify::instance();
        ZOLM_Booster_WhatsApp_Floating_Button::instance();
    }

    ZOLM_Booster_Admin::instance();
});

// Mevcut kurulumlarda yeni kolonlar eksikse ekle (v1.0.0 → v1.1.0 migration)
add_action("admin_init", function () {
    $db_version = get_option("zolm_booster_db_version", "0.0.0");
    if (version_compare($db_version, "1.1.0", "<")) {
        ZOLM_Booster_DB::maybe_migrate();
        update_option("zolm_booster_db_version", ZOLM_BOOSTER_VERSION);
    }
});

add_action("wp_enqueue_scripts", function () {
    wp_register_style("zolm-booster", ZOLM_BOOSTER_URL . "assets/css/zolm-booster.css", [], ZOLM_BOOSTER_VERSION);
    wp_register_script("zolm-booster", ZOLM_BOOSTER_URL . "assets/js/zolm-booster.js", ["jquery"], ZOLM_BOOSTER_VERSION, true);
    wp_localize_script("zolm-booster", "zolmBooster", [
        "restUrl" => esc_url_raw(rest_url("zolm-booster/v1")),
        "nonce" => wp_create_nonce("wp_rest"),
    ]);
});
