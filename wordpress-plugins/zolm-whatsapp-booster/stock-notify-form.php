<?php
/**
 * ZOLM WhatsApp Booster — Stok Bildirim Shortcode
 *
 * Kullanım: [zolm_stock_notify product_id="123"]
 * Veya otomatik: WooCommerce ürün sayfalarında stoğu tükenen ürünlerde gösterilir.
 */

defined('ABSPATH') || exit;

/**
 * Stok bildirim formu shortcode'u
 */
function zolm_stock_notify_shortcode($atts) {
    $atts = shortcode_atts([
        'product_id' => get_the_ID(),
    ], $atts, 'zolm_stock_notify');

    $productId = (int) $atts['product_id'];
    $product = wc_get_product($productId);

    if (!$product) {
        return '';
    }

    // Ürün stoğu varsa formu gösterme
    if ($product->is_in_stock()) {
        return '';
    }

    // Varyasyon seçimi (variable ürün için)
    $variationId = 0;
    $variations = [];
    if ($product->is_type('variable')) {
        $variations = $product->get_available_variations();
    }

    $ajaxUrl = rest_url('zolm-wa-booster/v1/stock-notify/' . $productId);
    $nonce = wp_create_nonce('wp_rest');

    ob_start();
    ?>
    <div class="zolm-stock-notify-form" style="margin-top: 15px; padding: 15px; border: 1px solid #e0e0e0; border-radius: 8px; background: #f9f9f9;">
        <p style="margin: 0 0 10px; font-weight: 600;">
            <?php esc_html_e('Bu ürün stoğa geldiğinde haber verilsin mi?', 'zolm-whatsapp-booster'); ?>
        </p>
        <form class="zolm-stock-notify-form-inner" data-ajax-url="<?php echo esc_url($ajaxUrl); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
            <?php if (!empty($variations)) : ?>
            <div style="margin-bottom: 8px;">
                <label for="zolm-stock-variation" style="display: block; font-size: 13px; margin-bottom: 4px;">
                    <?php esc_html_e('Varyasyon Seçin', 'zolm-whatsapp-booster'); ?>
                </label>
                <select name="variation_id" id="zolm-stock-variation"
                    required
                    style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    <option value=""><?php esc_html_e('— Seçiniz —', 'zolm-whatsapp-booster'); ?></option>
                    <?php foreach ($variations as $var) :
                        $attrStr = wc_get_formatted_variation($var, true, false, true);
                    ?>
                        <option value="<?php echo esc_attr($var['variation_id']); ?>"
                            <?php echo $var['is_in_stock'] ? '' : 'disabled'; ?>>
                            <?php echo esc_html($attrStr); ?>
                            <?php echo $var['is_in_stock'] ? '' : ' (' . esc_html__('Stokta yok', 'zolm-whatsapp-booster') . ')'; ?>
                        </option>
                    <?php endforeach; ?>
                    </select>
            </div>
            <?php endif; ?>
            <div style="display: flex; gap: 8px; align-items: end;">
                <div style="flex: 1;">
                    <label for="zolm-stock-phone" style="display: block; font-size: 13px; margin-bottom: 4px;">
                        <?php esc_html_e('WhatsApp Numaranız', 'zolm-whatsapp-booster'); ?>
                    </label>
                    <input type="tel" id="zolm-stock-phone" name="phone"
                           placeholder="+90 5XX XXX XX XX"
                           required
                           style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
                <button type="submit"
                        style="padding: 8px 20px; background: #25D366; color: #fff; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; white-space: nowrap;">
                    <?php esc_html_e('Bildir', 'zolm-whatsapp-booster'); ?>
                </button>
            </div>
            <!-- Honeypot: bot'lara görünür, insanlara gizli -->
            <div style="position: absolute; left: -9999px;" aria-hidden="true">
                <input type="text" name="website" tabindex="-1" autocomplete="off" value="">
            </div>
            <div class="zolm-stock-notify-message" style="margin-top: 8px; font-size: 13px; display: none;"></div>
        </form>
    </div>
    <script>
    document.querySelectorAll('.zolm-stock-notify-form-inner').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // Honeypot kontrolü
            if (form.querySelector('input[name="website"]').value !== '') {
                return;
            }

            var btn = form.querySelector('button[type="submit"]');
            var msg = form.querySelector('.zolm-stock-notify-message');
            var phone = form.querySelector('input[name="phone"]').value;
            var variationSelect = form.querySelector('select[name="variation_id"]');
            var variationId = variationSelect ? variationSelect.value : '0';

            btn.disabled = true;
            btn.textContent = '<?php echo esc_js(__('Gönderiliyor...', 'zolm-whatsapp-booster')); ?>';
            msg.style.display = 'none';

            fetch(form.dataset.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': form.dataset.nonce,
                },
                body: JSON.stringify({
                    phone: phone,
                    variation_id: parseInt(variationId) || 0
                }),
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.status === 'ok') {
                    msg.textContent = '<?php echo esc_js(__('Kaydınız alındı. Stoğa geldiğinde haber vereceğiz!', 'zolm-whatsapp-booster')); ?>';
                    msg.style.color = '#25D366';
                    msg.style.display = 'block';
                    form.querySelector('input[name="phone"]').value = '';
                } else {
                    msg.textContent = data.message || '<?php echo esc_js(__('Bir hata oluştu.', 'zolm-whatsapp-booster')); ?>';
                    msg.style.color = '#e74c3c';
                    msg.style.display = 'block';
                }
            })
            .catch(function() {
                msg.textContent = '<?php echo esc_js(__('Bağlantı hatası. Lütfen tekrar deneyin.', 'zolm-whatsapp-booster')); ?>';
                msg.style.color = '#e74c3c';
                msg.style.display = 'block';
            })
            .finally(function() {
                btn.disabled = false;
                btn.textContent = '<?php echo esc_js(__('Bildir', 'zolm-whatsapp-booster')); ?>';
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('zolm_stock_notify', 'zolm_stock_notify_shortcode');

/**
 * Stoğu tükenen ürünlerde otomatik göster
 */
function zolm_auto_stock_notify_on_product() {
    global $product;

    if (!$product || $product->is_in_stock()) {
        return;
    }

    echo do_shortcode('[zolm_stock_notify product_id="' . $product->get_id() . '"]');
}
add_action('woocommerce_single_product_summary', 'zolm_auto_stock_notify_on_product', 35);
