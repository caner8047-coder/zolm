<?php
/**
 * ZOLM WhatsApp Booster — WooCommerce Ayarları Sayfası
 *
 * Bu dosya yalnızca WC_Settings_Page yüklendikten sonra require edilir.
 */

defined('ABSPATH') || exit;

if (!class_exists('WC_Settings_Page')) {
    return;
}

if (!class_exists('ZOLM_WhatsApp_Settings_Page', false)) {

    class ZOLM_WhatsApp_Settings_Page extends WC_Settings_Page
    {
        public function __construct()
        {
            $this->id = 'zolm_whatsapp';
            $this->label = __('WhatsApp (ZOLM)', 'zolm-whatsapp-booster');
            parent::__construct();
        }

        /**
         * Settings tablosundan sonra test butonunu ekle
         */
        public function output($data = []) {
            parent::output($data);

            if (!current_user_can('manage_woocommerce')) {
                return;
            }

            $nonce = wp_create_nonce('zolm_wa_test_connection');
            ?>
            <div id="zolm-wa-test-connection" style="margin-top: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;">
                <h3><?php esc_html_e('Bağlantı Testi', 'zolm-whatsapp-booster'); ?></h3>
                <p class="description">
                    <?php esc_html_e('ZOLM Booster webhook bağlantısını test eder. Hiçbir mesaj veya müşteri kaydı oluşturmaz.', 'zolm-whatsapp-booster'); ?>
                </p>
                <p>
                    <button type="button" id="zolm-wa-test-btn" class="button button-secondary">
                        <?php esc_html_e('Bağlantıyı Test Et', 'zolm-whatsapp-booster'); ?>
                    </button>
                </p>
                <div id="zolm-wa-test-result" style="display: none; margin-top: 10px; padding: 10px; border-radius: 4px; font-size: 13px;"></div>
            </div>
            <script>
            jQuery(function($) {
                $('#zolm-wa-test-btn').on('click', function() {
                    var $btn = $(this);
                    var $result = $('#zolm-wa-test-result');

                    $btn.prop('disabled', true).text('<?php echo esc_js(__('Test ediliyor...', 'zolm-whatsapp-booster')); ?>');
                    $result.hide().removeClass('notice-success notice-error').addClass('notice');

                    $.post(ajaxurl, {
                        action: 'zolm_wa_test_connection',
                        nonce: '<?php echo esc_js($nonce); ?>'
                    }, function(response) {
                        if (response.success) {
                            $result.addClass('notice-success').text(response.data.message).show();
                        } else {
                            $result.addClass('notice-error').text(response.data.message).show();
                        }
                    }).fail(function() {
                        $result.addClass('notice-error').text('Bağlantı hatası. Lütfen tekrar deneyin.').show();
                    }).always(function() {
                        $btn.prop('disabled', false).text('<?php echo esc_js(__('Bağlantıyı Test Et', 'zolm-whatsapp-booster')); ?>');
                    });
                });
            });
            </script>
            <?php
        }

        public function get_settings()
        {
            return [
                [
                    'title' => __('WhatsApp Ayarları', 'zolm-whatsapp-booster'),
                    'type' => 'title',
                    'desc' => __('ZOLM WhatsApp modülü köprü ayarları', 'zolm-whatsapp-booster'),
                    'id' => 'zolm_wa_section',
                ],
                [
                    'title' => __('ZOLM URL', 'zolm-whatsapp-booster'),
                    'desc' => __('ZOLM Booster webhook endpoint URL\'i', 'zolm-whatsapp-booster'),
                    'id' => 'zolm_wa_booster_zolm_url',
                    'type' => 'url',
                    'default' => '',
                    'css' => 'width: 400px;',
                ],
                [
                    'title' => __('Webhook Secret', 'zolm-whatsapp-booster'),
                    'desc' => __('HMAC imza doğrulama anahtarı', 'zolm-whatsapp-booster'),
                    'id' => 'zolm_wa_booster_webhook_secret',
                    'type' => 'password',
                    'default' => '',
                    'css' => 'width: 400px;',
                ],
                [
                    'title' => __('Store ID', 'zolm-whatsapp-booster'),
                    'desc' => __('ZOLM\'daki mağaza ID\'si', 'zolm-whatsapp-booster'),
                    'id' => 'zolm_wa_booster_store_id',
                    'type' => 'number',
                    'default' => 0,
                ],
                [
                    'title' => __('Test Modu', 'zolm-whatsapp-booster'),
                    'desc' => __('Aktif edildiğinde sadece log yazılır, gerçek webhook gönderilmez', 'zolm-whatsapp-booster'),
                    'id' => 'zolm_wa_booster_test_mode',
                    'type' => 'checkbox',
                    'default' => 'no',
                ],
                [
                    'type' => 'sectionend',
                    'id' => 'zolm_wa_section',
                ],
            ];
        }
    }

}
