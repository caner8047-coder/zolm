<?php
/**
 * ZOLM WhatsApp Booster — Yüzen WhatsApp Butonu
 *
 * Sitede görünen yüzen WhatsApp butonu widget'ı.
 * Admin panelinden özelleştirilebilir.
 */

defined('ABSPATH') || exit;

/**
 * Widget ayarlarını kaydet
 */
function zolm_wa_floating_button_register_settings() {
    register_setting('zolm_wa_floating_button', 'zolm_wa_fb_enabled', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ]);
    register_setting('zolm_wa_floating_button', 'zolm_wa_fb_phone', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '',
    ]);
    register_setting('zolm_wa_floating_button', 'zolm_wa_fb_message', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_textarea_field',
        'default' => 'Merhaba! Size nasıl yardımcı olabilirim?',
    ]);
    register_setting('zolm_wa_floating_button', 'zolm_wa_fb_position', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'bottom-right',
    ]);
    register_setting('zolm_wa_floating_button', 'zolm_wa_fb_color', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_hex_color',
        'default' => '#25D366',
    ]);
    register_setting('zolm_wa_floating_button', 'zolm_wa_fb_icon_size', [
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 60,
    ]);
    register_setting('zolm_wa_floating_button', 'zolm_wa_fb_show_on_mobile', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ]);
    register_setting('zolm_wa_floating_button', 'zolm_wa_fb_show_on_desktop', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ]);
    register_setting('zolm_wa_floating_button', 'zolm_wa_fb_tooltip', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'Bize ulaşın!',
    ]);
    register_setting('zolm_wa_floating_button', 'zolm_wa_fb_animation', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'pulse',
    ]);
    register_setting('zolm_wa_floating_button', 'zolm_wa_fb_delay', [
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 0,
    ]);
}
add_action('admin_init', 'zolm_wa_floating_button_register_settings');

/**
 * WooCommerce ayarlarına sekme ekle
 */
function zolm_wa_floating_button_wc_settings($settings) {
    $settings[] = [
        'title' => 'WhatsApp Buton',
        'type' => 'title',
        'desc' => 'Yüzen WhatsApp butonu ayarları',
        'id' => 'zolm_wa_fb_section',
    ];
    $settings[] = [
        'title' => 'Aktif',
        'desc' => 'Yüzen butonu göster',
        'id' => 'zolm_wa_fb_enabled',
        'type' => 'checkbox',
        'default' => 'yes',
    ];
    $settings[] = [
        'title' => 'WhatsApp Numarası',
        'desc' => 'Ülke koduyla birlikte (ör: 905551234567)',
        'id' => 'zolm_wa_fb_phone',
        'type' => 'text',
        'default' => '',
        'css' => 'width: 300px;',
    ];
    $settings[] = [
        'title' => 'Ön Mesaj',
        'desc' => 'WhatsApp açıldığında otomatik doldurulacak mesaj',
        'id' => 'zolm_wa_fb_message',
        'type' => 'textarea',
        'default' => 'Merhaba! Size nasıl yardımcı olabilirim?',
        'css' => 'width: 400px; height: 80px;',
    ];
    $settings[] = [
        'title' => 'Pozisyon',
        'id' => 'zolm_wa_fb_position',
        'type' => 'select',
        'default' => 'bottom-right',
        'options' => [
            'bottom-right' => 'Sağ Alt',
            'bottom-left' => 'Sol Alt',
        ],
    ];
    $settings[] = [
        'title' => 'Buton Rengi',
        'desc' => 'Hex renk kodu (ör: #25D366)',
        'id' => 'zolm_wa_fb_color',
        'type' => 'color',
        'default' => '#25D366',
    ];
    $settings[] = [
        'title' => 'Buton Boyutu (px)',
        'id' => 'zolm_wa_fb_icon_size',
        'type' => 'number',
        'default' => 60,
        'css' => 'width: 80px;',
    ];
    $settings[] = [
        'title' => 'Mobilde Göster',
        'id' => 'zolm_wa_fb_show_on_mobile',
        'type' => 'checkbox',
        'default' => 'yes',
    ];
    $settings[] = [
        'title' => 'Masaüstünde Göster',
        'id' => 'zolm_wa_fb_show_on_desktop',
        'type' => 'checkbox',
        'default' => 'yes',
    ];
    $settings[] = [
        'title' => 'Tooltip Yazısı',
        'desc' => 'Buton üzerinde görünen kısa bilgi',
        'id' => 'zolm_wa_fb_tooltip',
        'type' => 'text',
        'default' => 'Bize ulaşın!',
        'css' => 'width: 300px;',
    ];
    $settings[] = [
        'title' => 'Animasyon',
        'id' => 'zolm_wa_fb_animation',
        'type' => 'select',
        'default' => 'pulse',
        'options' => [
            'none' => 'Yok',
            'pulse' => 'Nabız',
            'bounce' => 'Zıplama',
            'shake' => 'Sallanma',
        ],
    ];
    $settings[] = [
        'title' => 'Gecikme (saniye)',
        'desc' => 'Sayfa yüklendikten sonra ne kadar süre sonra görünsün',
        'id' => 'zolm_wa_fb_delay',
        'type' => 'number',
        'default' => 0,
        'css' => 'width: 80px;',
    ];
    $settings[] = [
        'type' => 'sectionend',
        'id' => 'zolm_wa_fb_section',
    ];
    return $settings;
}
add_filter('woocommerce_get_settings_pages', 'zolm_wa_floating_button_wc_settings');

/**
 * CSS ve JS yükle
 */
function zolm_wa_floating_button_enqueue() {
    if (is_admin()) {
        return;
    }

    $enabled = get_option('zolm_wa_fb_enabled', true);
    if (!$enabled) {
        return;
    }

    $phone = get_option('zolm_wa_fb_phone', '');
    if (empty($phone)) {
        return;
    }

    $message = get_option('zolm_wa_fb_message', 'Merhaba! Size nasıl yardımcı olabilirim?');
    $position = get_option('zolm_wa_fb_position', 'bottom-right');
    $color = get_option('zolm_wa_fb_color', '#25D366');
    $iconSize = (int) get_option('zolm_wa_fb_icon_size', 60);
    $showMobile = get_option('zolm_wa_fb_show_on_mobile', 'yes') === 'yes';
    $showDesktop = get_option('zolm_wa_fb_show_on_desktop', 'yes') === 'yes';
    $tooltip = get_option('zolm_wa_fb_tooltip', 'Bize ulaşın!');
    $animation = get_option('zolm_wa_fb_animation', 'pulse');
    $delay = (int) get_option('zolm_wa_fb_delay', 0);

    $encodedMessage = urlencode($message);
    $whatsappUrl = "https://wa.me/{$phone}?text={$encodedMessage}";

    $positionCSS = $position === 'bottom-left' ? 'left: 20px;' : 'right: 20px;';

    $displayCSS = '';
    if (!$showMobile) {
        $displayCSS .= '@media (max-width: 768px) { #zolm-wa-float-btn { display: none !important; } }';
    }
    if (!$showDesktop) {
        $displayCSS .= '@media (min-width: 769px) { #zolm-wa-float-btn { display: none !important; } }';
    }

    $animationCSS = '';
    switch ($animation) {
        case 'pulse':
            $animationCSS = '@keyframes waPulse { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } } #zolm-wa-float-btn:hover { animation: waPulse 1s infinite; }';
            break;
        case 'bounce':
            $animationCSS = '@keyframes waBounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } } #zolm-wa-float-btn:hover { animation: waBounce 0.5s infinite; }';
            break;
        case 'shake':
            $animationCSS = '@keyframes waShake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-3px); } 75% { transform: translateX(3px); } } #zolm-wa-float-btn:hover { animation: waShake 0.3s infinite; }';
            break;
    }

    $delayStyle = $delay > 0 ? "opacity: 0; transition: opacity 0.3s ease;" : '';

    $iconWidth = round($iconSize * 0.55);
    $iconHeight = round($iconSize * 0.55);
    $tooltipBottom = $iconSize + 25;

    $css = "#zolm-wa-float-btn {
    position: fixed;
    bottom: 20px;
    {$positionCSS}
    width: {$iconSize}px;
    height: {$iconSize}px;
    background-color: {$color};
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 9999;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    {$delayStyle}
    text-decoration: none;
}
#zolm-wa-float-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(0,0,0,0.4);
}
{$animationCSS}
#zolm-wa-float-btn svg {
    width: {$iconWidth}px;
    height: {$iconHeight}px;
    fill: white;
}
#zolm-wa-float-tooltip {
    position: fixed;
    bottom: {$tooltipBottom}px;
    {$positionCSS}
    background: #333;
    color: white;
    padding: 8px 14px;
    border-radius: 6px;
    font-size: 13px;
    white-space: nowrap;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: 9998;
}
#zolm-wa-float-btn:hover + #zolm-wa-float-tooltip,
#zolm-wa-float-tooltip.show {
    opacity: 1;
}
{$displayCSS}";

    wp_register_style('zolm-wa-float', false);
    wp_enqueue_style('zolm-wa-float');
    wp_add_inline_style('zolm-wa-float', $css);

    $delayMs = $delay * 1000;
    $js = "document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('zolm-wa-float-btn');
    var tooltip = document.getElementById('zolm-wa-float-tooltip');
    if (!btn) return;

    var delay = {$delayMs};
    if (delay > 0) {
        setTimeout(function() {
            btn.style.opacity = '1';
            if (tooltip) tooltip.classList.add('show');
        }, delay);
    }

    btn.addEventListener('click', function(e) {
        e.preventDefault();
        window.open(this.href, '_blank');
    });
});";

    wp_register_script('zolm-wa-float', false);
    wp_enqueue_script('zolm-wa-float');
    wp_add_inline_script('zolm-wa-float', $js);
}
add_action('wp_enqueue_scripts', 'zolm_wa_floating_button_enqueue');

/**
 * Butonu sayfaya ekle
 */
function zolm_wa_floating_button_render() {
    if (is_admin()) {
        return;
    }

    $enabled = get_option('zolm_wa_fb_enabled', true);
    if (!$enabled) {
        return;
    }

    $phone = get_option('zolm_wa_fb_phone', '');
    if (empty($phone)) {
        return;
    }

    $message = get_option('zolm_wa_fb_message', 'Merhaba! Size nasıl yardımcı olabilirim?');
    $tooltip = get_option('zolm_wa_fb_tooltip', 'Bize ulaşın!');
    $whatsappUrl = "https://wa.me/{$phone}?text=" . urlencode($message);

    ?>
    <a href="<?php echo esc_url($whatsappUrl); ?>"
       id="zolm-wa-float-btn"
       target="_blank"
       rel="noopener noreferrer"
       aria-label="WhatsApp ile iletişime geçin"
       title="<?php echo esc_attr($tooltip); ?>">
        <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
            <path d="M16.004 0h-.008C7.174 0 0 7.176 0 16.004c0 2.832.736 5.556 2.14 7.956L.776 31.468a.5.5 0 0 0 .612.612l6.688-2.024A15.89 15.89 0 0 0 16.004 32C24.826 32 32 24.822 32 16.004S24.826 0 16.004 0zm9.212 22.588c-.384 1.084-1.904 1.988-3.108 2.256-.82.18-1.888.324-5.468-1.176-4.584-1.8-7.52-6.168-7.748-6.492-.22-.324-1.8-2.396-1.8-4.572 0-2.176 1.14-3.24 1.54-3.684.4-.444.872-.556 1.164-.556.292 0 .58.004.832.016.268.012.624-.1.972.744.36.864 1.236 2.996 1.344 3.22.108.224.18.484.036.78-.144.296-.216.48-.432.776-.216.296-.456.664-.648.888-.204.24-.42.444-.12.828.304.384 1.064 1.56 2.172 2.352 2.564.792.392 1.38.324 1.896.192.516-.132.876-.696 1.356-1.824.384-.896 1.064-2.32 1.448-3.116.384-.796 1.064-1.536 1.356-2.052.292-.516.58-1.064.972-1.248z"/>
        </svg>
    </a>
    <div id="zolm-wa-float-tooltip"><?php echo esc_html($tooltip); ?></div>
    <?php
}
add_action('wp_footer', 'zolm_wa_floating_button_render');
