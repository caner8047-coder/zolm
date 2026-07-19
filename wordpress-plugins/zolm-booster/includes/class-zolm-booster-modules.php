<?php

if (! defined("ABSPATH")) exit;

class ZOLM_Booster_Modules {
    public const OPTION = "zolm_booster_enabled_modules";

    private static $instance = null;

    private array $modules = [
        "trendyol_reviews" => [
            "label" => "Trendyol Yorumları",
            "description" => "ZOLM panelden gelen Trendyol yorumlarını WooCommerce ürünlerinde gösterir.",
            "default" => true,
        ],
        "whatsapp" => [
            "label" => "WhatsApp Köprüsü",
            "description" => "WooCommerce sipariş, müşteri, sepet, izin ve stok bildirim sinyallerini ZOLM WhatsApp modülüne iletir.",
            "default" => true,
        ],
    ];

    public static function instance(): self {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public static function activate_defaults(): void {
        $instance = self::instance();
        $current = get_option(self::OPTION, null);
        if (is_array($current)) return;

        $defaults = [];
        foreach ($instance->all() as $key => $module) {
            $defaults[$key] = ! empty($module["default"]) ? 1 : 0;
        }
        update_option(self::OPTION, $defaults);
    }

    public function all(): array {
        return $this->modules;
    }

    public function is_enabled(string $key): bool {
        if (! isset($this->modules[$key])) return false;

        $settings = get_option(self::OPTION, []);
        if (is_array($settings) && array_key_exists($key, $settings)) {
            return (bool) $settings[$key];
        }

        return (bool) ($this->modules[$key]["default"] ?? false);
    }

    public function legacy_whatsapp_plugin_active(): bool {
        $legacy = "zolm-whatsapp-booster/zolm-whatsapp-booster.php";
        $active = (array) get_option("active_plugins", []);
        if (in_array($legacy, $active, true)) return true;

        $network = (array) get_site_option("active_sitewide_plugins", []);
        return isset($network[$legacy]);
    }
}
