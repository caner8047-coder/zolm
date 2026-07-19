<?php

if (! defined("ABSPATH")) exit;

class ZOLM_Booster_WhatsApp_Stock_Notify {
    private static $instance = null;

    public static function instance(): self {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_shortcode("zolm_stock_notify", [$this, "render_shortcode"]);
        add_action("woocommerce_single_product_summary", [$this, "render_on_product"], 35);
    }

    public function render_shortcode($atts): string {
        if (! function_exists("wc_get_product")) return "";

        $atts = shortcode_atts(["product_id" => get_the_ID()], $atts, "zolm_stock_notify");
        $productId = (int) $atts["product_id"];
        $product = wc_get_product($productId);
        if (! $product || $product->is_in_stock()) return "";

        $variations = $product->is_type("variable") ? $product->get_available_variations() : [];
        $ajaxUrl = rest_url("zolm-wa-booster/v1/stock-notify/" . $productId);
        $nonce = wp_create_nonce("wp_rest");

        ob_start();
        ?>
        <div class="zolm-stock-notify-form" style="margin-top:15px;padding:15px;border:1px solid #e0e0e0;border-radius:8px;background:#f9f9f9;">
            <p style="margin:0 0 10px;font-weight:600;"><?php esc_html_e("Bu ürün stoğa geldiğinde haber verilsin mi?", "zolm-booster"); ?></p>
            <form class="zolm-stock-notify-form-inner" data-ajax-url="<?php echo esc_url($ajaxUrl); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
                <?php if (! empty($variations)) : ?>
                    <div style="margin-bottom:8px;">
                        <label for="zolm-stock-variation" style="display:block;font-size:13px;margin-bottom:4px;"><?php esc_html_e("Varyasyon Seçin", "zolm-booster"); ?></label>
                        <select name="variation_id" id="zolm-stock-variation" required style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:4px;font-size:14px;">
                            <option value=""><?php esc_html_e("— Seçiniz —", "zolm-booster"); ?></option>
                            <?php foreach ($variations as $variation) : ?>
                                <option value="<?php echo esc_attr($variation["variation_id"]); ?>" <?php echo $variation["is_in_stock"] ? "" : "disabled"; ?>>
                                    <?php echo esc_html(wc_get_formatted_variation($variation, true, false, true)); ?>
                                    <?php echo $variation["is_in_stock"] ? "" : " (" . esc_html__("Stokta yok", "zolm-booster") . ")"; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div style="display:flex;gap:8px;align-items:end;">
                    <div style="flex:1;">
                        <label for="zolm-stock-phone" style="display:block;font-size:13px;margin-bottom:4px;"><?php esc_html_e("WhatsApp Numaranız", "zolm-booster"); ?></label>
                        <input type="tel" id="zolm-stock-phone" name="phone" placeholder="+90 5XX XXX XX XX" required style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:4px;font-size:14px;">
                    </div>
                    <button type="submit" style="padding:8px 20px;background:#25D366;color:#fff;border:none;border-radius:4px;font-weight:600;cursor:pointer;white-space:nowrap;"><?php esc_html_e("Bildir", "zolm-booster"); ?></button>
                </div>
                <label style="display:flex;gap:8px;align-items:flex-start;margin-top:10px;font-size:12px;line-height:1.4;color:#555;">
                    <input type="checkbox" name="stock_alert_consent" value="yes" required style="margin-top:2px;">
                    <span><?php esc_html_e("Stoğa geldiğinde WhatsApp üzerinden bilgilendirilmeyi kabul ediyorum.", "zolm-booster"); ?></span>
                </label>
                <div style="position:absolute;left:-9999px;" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off" value=""></div>
                <div class="zolm-stock-notify-message" style="margin-top:8px;font-size:13px;display:none;"></div>
            </form>
        </div>
        <script>
        document.querySelectorAll('.zolm-stock-notify-form-inner').forEach(function(form) {
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                if (form.querySelector('input[name="website"]').value !== '') return;

                var button = form.querySelector('button[type="submit"]');
                var message = form.querySelector('.zolm-stock-notify-message');
                var variationSelect = form.querySelector('select[name="variation_id"]');
                button.disabled = true;
                button.textContent = '<?php echo esc_js(__("Gönderiliyor...", "zolm-booster")); ?>';
                message.style.display = 'none';

                fetch(form.dataset.ajaxUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-WP-Nonce': form.dataset.nonce},
                    body: JSON.stringify({
                        phone: form.querySelector('input[name="phone"]').value,
                        variation_id: variationSelect ? parseInt(variationSelect.value, 10) || 0 : 0,
                        stock_alert_consent: form.querySelector('input[name="stock_alert_consent"]').checked ? 'yes' : 'no'
                    })
                }).then(function(response) {
                    return response.json();
                }).then(function(data) {
                    if (data.status === 'ok') {
                        message.textContent = '<?php echo esc_js(__("Kaydınız alındı. Stoğa geldiğinde haber vereceğiz!", "zolm-booster")); ?>';
                        message.style.color = '#25D366';
                        form.querySelector('input[name="phone"]').value = '';
                    } else {
                        message.textContent = data.message || '<?php echo esc_js(__("Bir hata oluştu.", "zolm-booster")); ?>';
                        message.style.color = '#e74c3c';
                    }
                    message.style.display = 'block';
                }).catch(function() {
                    message.textContent = '<?php echo esc_js(__("Bağlantı hatası. Lütfen tekrar deneyin.", "zolm-booster")); ?>';
                    message.style.color = '#e74c3c';
                    message.style.display = 'block';
                }).finally(function() {
                    button.disabled = false;
                    button.textContent = '<?php echo esc_js(__("Bildir", "zolm-booster")); ?>';
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    public function render_on_product(): void {
        global $product;
        if (! $product || $product->is_in_stock()) return;
        echo do_shortcode('[zolm_stock_notify product_id="' . (int) $product->get_id() . '"]');
    }
}
