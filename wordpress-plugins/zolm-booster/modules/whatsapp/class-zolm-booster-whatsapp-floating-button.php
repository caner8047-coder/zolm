<?php

if (! defined("ABSPATH")) exit;

class ZOLM_Booster_WhatsApp_Floating_Button {
    private static $instance = null;

    public static function instance(): self {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public static function register_settings(): void {
        $settings = [
            "zolm_wa_fb_enabled" => ["type" => "string", "sanitize_callback" => [__CLASS__, "sanitize_checkbox"], "default" => "yes"],
            "zolm_wa_fb_phone" => ["type" => "string", "sanitize_callback" => "sanitize_text_field", "default" => ""],
            "zolm_wa_fb_message" => ["type" => "string", "sanitize_callback" => "sanitize_textarea_field", "default" => "Merhaba! Size nasıl yardımcı olabilirim?"],
            "zolm_wa_fb_position" => ["type" => "string", "sanitize_callback" => [__CLASS__, "sanitize_position"], "default" => "bottom-right"],
            "zolm_wa_fb_color" => ["type" => "string", "sanitize_callback" => "sanitize_hex_color", "default" => "#25D366"],
            "zolm_wa_fb_icon_size" => ["type" => "integer", "sanitize_callback" => "absint", "default" => 60],
            "zolm_wa_fb_show_on_mobile" => ["type" => "string", "sanitize_callback" => [__CLASS__, "sanitize_checkbox"], "default" => "yes"],
            "zolm_wa_fb_show_on_desktop" => ["type" => "string", "sanitize_callback" => [__CLASS__, "sanitize_checkbox"], "default" => "yes"],
            "zolm_wa_fb_tooltip" => ["type" => "string", "sanitize_callback" => "sanitize_text_field", "default" => "Bize ulaşın!"],
            "zolm_wa_fb_animation" => ["type" => "string", "sanitize_callback" => [__CLASS__, "sanitize_animation"], "default" => "pulse"],
            "zolm_wa_fb_delay" => ["type" => "integer", "sanitize_callback" => "absint", "default" => 0],
        ];

        foreach ($settings as $key => $args) {
            register_setting("zolm_booster_whatsapp_settings_group", $key, $args);
        }
    }

    public static function sanitize_checkbox($value): string {
        return in_array($value, ["1", 1, "yes", true], true) ? "yes" : "no";
    }

    public static function sanitize_position($value): string {
        return in_array($value, ["bottom-left", "bottom-right"], true) ? $value : "bottom-right";
    }

    public static function sanitize_animation($value): string {
        return in_array($value, ["none", "pulse", "bounce", "shake"], true) ? $value : "pulse";
    }

    private function __construct() {
        add_action("wp_enqueue_scripts", [$this, "enqueue"]);
        add_action("wp_footer", [$this, "render"]);
    }

    public function enqueue(): void {
        if (is_admin()) return;
        if (get_option("zolm_wa_fb_enabled", "yes") !== "yes") return;

        $phone = get_option("zolm_wa_fb_phone", "");
        if (empty($phone)) return;

        $position = get_option("zolm_wa_fb_position", "bottom-right");
        $color = sanitize_hex_color(get_option("zolm_wa_fb_color", "#25D366")) ?: "#25D366";
        $iconSize = max(44, min(96, (int) get_option("zolm_wa_fb_icon_size", 60)));
        $showMobile = get_option("zolm_wa_fb_show_on_mobile", "yes") === "yes";
        $showDesktop = get_option("zolm_wa_fb_show_on_desktop", "yes") === "yes";
        $animation = get_option("zolm_wa_fb_animation", "pulse");
        $delay = max(0, (int) get_option("zolm_wa_fb_delay", 0));
        $positionCss = $position === "bottom-left" ? "left:20px;" : "right:20px;";
        $iconInner = round($iconSize * 0.55);
        $tooltipBottom = $iconSize + 25;
        $delayStyle = $delay > 0 ? "opacity:0;transition:opacity .3s ease;" : "";

        $displayCss = "";
        if (! $showMobile) $displayCss .= "@media(max-width:768px){#zolm-wa-float-btn{display:none!important}}";
        if (! $showDesktop) $displayCss .= "@media(min-width:769px){#zolm-wa-float-btn{display:none!important}}";

        $animationCss = "";
        if ($animation === "pulse") $animationCss = "@keyframes waPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.05)}}#zolm-wa-float-btn:hover{animation:waPulse 1s infinite}";
        if ($animation === "bounce") $animationCss = "@keyframes waBounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-5px)}}#zolm-wa-float-btn:hover{animation:waBounce .5s infinite}";
        if ($animation === "shake") $animationCss = "@keyframes waShake{0%,100%{transform:translateX(0)}25%{transform:translateX(-3px)}75%{transform:translateX(3px)}}#zolm-wa-float-btn:hover{animation:waShake .3s infinite}";

        $css = "#zolm-wa-float-btn{position:fixed;bottom:20px;{$positionCss}width:{$iconSize}px;height:{$iconSize}px;background-color:{$color};border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,.3);transition:transform .2s ease,box-shadow .2s ease;{$delayStyle}text-decoration:none}#zolm-wa-float-btn:hover{transform:scale(1.1);box-shadow:0 6px 20px rgba(0,0,0,.4)}{$animationCss}#zolm-wa-float-btn svg{width:{$iconInner}px;height:{$iconInner}px;fill:white}#zolm-wa-float-tooltip{position:fixed;bottom:{$tooltipBottom}px;{$positionCss}background:#333;color:#fff;padding:8px 14px;border-radius:6px;font-size:13px;white-space:nowrap;pointer-events:none;opacity:0;transition:opacity .3s ease;z-index:9998}#zolm-wa-float-btn:hover+#zolm-wa-float-tooltip,#zolm-wa-float-tooltip.show{opacity:1}{$displayCss}";

        wp_register_style("zolm-wa-float", false);
        wp_enqueue_style("zolm-wa-float");
        wp_add_inline_style("zolm-wa-float", $css);

        $delayMs = $delay * 1000;
        $js = "document.addEventListener('DOMContentLoaded',function(){var b=document.getElementById('zolm-wa-float-btn');var t=document.getElementById('zolm-wa-float-tooltip');if(!b)return;var d={$delayMs};if(d>0){setTimeout(function(){b.style.opacity='1';if(t)t.classList.add('show');},d)}b.addEventListener('click',function(e){e.preventDefault();window.open(this.href,'_blank');});});";
        wp_register_script("zolm-wa-float", false);
        wp_enqueue_script("zolm-wa-float");
        wp_add_inline_script("zolm-wa-float", $js);
    }

    public function render(): void {
        if (is_admin()) return;
        if (get_option("zolm_wa_fb_enabled", "yes") !== "yes") return;

        $phone = preg_replace("/\D+/", "", (string) get_option("zolm_wa_fb_phone", ""));
        if (empty($phone)) return;

        $message = get_option("zolm_wa_fb_message", "Merhaba! Size nasıl yardımcı olabilirim?");
        $tooltip = get_option("zolm_wa_fb_tooltip", "Bize ulaşın!");
        $whatsappUrl = "https://wa.me/{$phone}?text=" . urlencode($message);
        ?>
        <a href="<?php echo esc_url($whatsappUrl); ?>" id="zolm-wa-float-btn" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e("WhatsApp ile iletişime geçin", "zolm-booster"); ?>" title="<?php echo esc_attr($tooltip); ?>">
            <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                <path d="M16.004 0h-.008C7.174 0 0 7.176 0 16.004c0 2.832.736 5.556 2.14 7.956L.776 31.468a.5.5 0 0 0 .612.612l6.688-2.024A15.89 15.89 0 0 0 16.004 32C24.826 32 32 24.822 32 16.004S24.826 0 16.004 0zm9.212 22.588c-.384 1.084-1.904 1.988-3.108 2.256-.82.18-1.888.324-5.468-1.176-4.584-1.8-7.52-6.168-7.748-6.492-.22-.324-1.8-2.396-1.8-4.572 0-2.176 1.14-3.24 1.54-3.684.4-.444.872-.556 1.164-.556.292 0 .58.004.832.016.268.012.624-.1.972.744.36.864 1.236 2.996 1.344 3.22.108.224.18.484.036.78-.144.296-.216.48-.432.776-.216.296-.456.664-.648.888-.204.24-.42.444-.12.828.304.384 1.064 1.56 2.172 2.352 2.564.792.392 1.38.324 1.896.192.516-.132.876-.696 1.356-1.824.384-.896 1.064-2.32 1.448-3.116.384-.796 1.064-1.536 1.356-2.052.292-.516.58-1.064.972-1.248z"/>
            </svg>
        </a>
        <div id="zolm-wa-float-tooltip"><?php echo esc_html($tooltip); ?></div>
        <?php
    }
}
