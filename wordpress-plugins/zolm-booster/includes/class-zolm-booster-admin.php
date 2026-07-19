<?php

if (! defined("ABSPATH")) exit;

class ZOLM_Booster_Admin {
    private static $instance = null;

    public static function instance(): self {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action("admin_menu", [$this, "add_menu"]);
        add_action("admin_init", [$this, "handle_actions"]);
        add_action("admin_init", [$this, "register_settings"]);
        add_action("admin_notices", [$this, "admin_notices"]);
    }

    public function add_menu(): void {
        add_menu_page("ZOLM Booster", "ZOLM Booster", "manage_options", "zolm-booster", [$this, "render_modules_page"], "dashicons-star-filled", 56);
        add_submenu_page("zolm-booster", "Modüller", "Modüller", "manage_options", "zolm-booster", [$this, "render_modules_page"]);
        add_submenu_page("zolm-booster", "Trendyol Yorumları", "Trendyol Yorumları", "manage_options", "zolm-booster-reviews", [$this, "render_reviews_page"]);
        add_submenu_page("zolm-booster", "WhatsApp", "WhatsApp", "manage_options", "zolm-booster-whatsapp", [$this, "render_whatsapp_page"]);
        add_submenu_page("zolm-booster", "Trendyol Ayarları", "Trendyol Ayarları", "manage_options", "zolm-booster-settings", [$this, "render_settings_page"]);
    }

    public function register_settings(): void {
        register_setting("zolm_booster_modules_group", ZOLM_Booster_Modules::OPTION, [$this, "sanitize_modules"]);
        register_setting("zolm_booster_settings_group", ZOLM_BOOSTER_SETTINGS);
        register_setting("zolm_booster_settings_group", ZOLM_BOOSTER_OPTION_KEY);
        ZOLM_Booster_WhatsApp_Module::register_settings();
        ZOLM_Booster_WhatsApp_Floating_Button::register_settings();
    }

    public function sanitize_modules($value): array {
        $sanitized = [];
        foreach (ZOLM_Booster_Modules::instance()->all() as $key => $module) {
            $sanitized[$key] = ! empty($value[$key]) ? 1 : 0;
        }
        return $sanitized;
    }

    public function handle_actions(): void {
        if (! current_user_can("manage_options")) return;

        $action = $_REQUEST["zolm_action"] ?? "";
        $id = (int) ($_REQUEST["review_id"] ?? 0);
        if (! $action || ! $id) return;

        check_admin_referer("zolm_booster_" . $action . "_" . $id);

        switch ($action) {
            case "approve":
                ZOLM_Booster_DB::instance()->update_status($id, "approved");
                break;
            case "reject":
                ZOLM_Booster_DB::instance()->update_status($id, "rejected");
                break;
            case "delete":
                ZOLM_Booster_DB::instance()->delete_review($id);
                break;
            case "feature":
                ZOLM_Booster_DB::instance()->toggle_featured($id);
                break;
        }

        wp_safe_redirect(add_query_arg(["page" => "zolm-booster-reviews", "msg" => $action], admin_url("admin.php")));
        exit;
    }

    public function admin_notices(): void {
        $msg = $_GET["msg"] ?? "";
        if (! $msg) return;

        $messages = [
            "approve" => "Yorum onaylandı.",
            "reject" => "Yorum reddedildi.",
            "delete" => "Yorum silindi.",
            "feature" => "Yorum öne çıkarma durumu değişti.",
            "key" => "API anahtarı oluşturuldu.",
        ];
        $text = $messages[$msg] ?? "";
        if ($text) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($text) . '</p></div>';
    }

    public function render_modules_page(): void {
        $modules = ZOLM_Booster_Modules::instance();
        ?>
        <div class="wrap zolm-booster-admin">
            <h1>ZOLM Booster</h1>
            <p class="description">WooCommerce tarafındaki tüm ZOLM geliştirmeleri tek eklenti altında, ayrı modüller olarak yönetilir.</p>

            <?php if ($modules->legacy_whatsapp_plugin_active()) : ?>
                <div class="notice notice-warning">
                    <p><strong>ZOLM WhatsApp Booster</strong> ayrı eklentisi hâlâ aktif görünüyor. Çift sinyal oluşmaması için birleşik eklentideki WhatsApp modülü otomatik olarak pasif çalışır. Eski eklentiyi etkisizleştirdikten sonra bu modül devreye girer.</p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields("zolm_booster_modules_group"); ?>
                <div class="zolm-module-grid">
                    <?php foreach ($modules->all() as $key => $module) : ?>
                        <div class="zolm-module-card">
                            <div>
                                <h2><?php echo esc_html($module["label"]); ?></h2>
                                <p><?php echo esc_html($module["description"]); ?></p>
                            </div>
                            <label class="zolm-toggle">
                                <input type="hidden" name="<?php echo esc_attr(ZOLM_Booster_Modules::OPTION); ?>[<?php echo esc_attr($key); ?>]" value="0">
                                <input type="checkbox" name="<?php echo esc_attr(ZOLM_Booster_Modules::OPTION); ?>[<?php echo esc_attr($key); ?>]" value="1" <?php checked($modules->is_enabled($key)); ?>>
                                <span>Aktif</span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php submit_button("Modülleri Kaydet"); ?>
            </form>

            <div class="zolm-admin-links">
                <a class="button button-primary" href="<?php echo esc_url(admin_url("admin.php?page=zolm-booster-reviews")); ?>">Trendyol Yorumları</a>
                <a class="button" href="<?php echo esc_url(admin_url("admin.php?page=zolm-booster-whatsapp")); ?>">WhatsApp Ayarları</a>
                <a class="button" href="<?php echo esc_url(admin_url("admin.php?page=zolm-booster-settings")); ?>">Trendyol Ayarları</a>
            </div>
        </div>
        <?php $this->render_admin_styles(); ?>
        <?php
    }

    public function render_reviews_page(): void {
        if (! ZOLM_Booster_Modules::instance()->is_enabled("trendyol_reviews")) {
            $this->render_disabled_notice("Trendyol Yorumları");
            return;
        }

        global $wpdb;
        $table = ZOLM_Booster_DB::instance()->table();
        $status = sanitize_text_field($_GET["status"] ?? "all");
        $where = $status !== "all" ? $wpdb->prepare(" WHERE status = %s", $status) : "";
        $reviews = $wpdb->get_results("SELECT * FROM {$table}{$where} ORDER BY created_at DESC LIMIT 200");
        $counts = $wpdb->get_results("SELECT status, COUNT(*) as cnt FROM {$table} GROUP BY status", ARRAY_A);
        $countsArr = array_column($counts, "cnt", "status");
        $baseUrl = admin_url("admin.php?page=zolm-booster-reviews");
        ?>
        <div class="wrap">
            <h1>ZOLM Booster - Trendyol Yorumları</h1>
            <ul class="subsubsub">
                <li><a href="<?php echo esc_url($baseUrl . "&status=all"); ?>" class="<?php echo $status === "all" ? "current" : ""; ?>">Tümü (<?php echo esc_html(array_sum($countsArr)); ?>)</a> |</li>
                <li><a href="<?php echo esc_url($baseUrl . "&status=pending"); ?>" class="<?php echo $status === "pending" ? "current" : ""; ?>">Bekleyen (<?php echo esc_html($countsArr["pending"] ?? 0); ?>)</a> |</li>
                <li><a href="<?php echo esc_url($baseUrl . "&status=approved"); ?>" class="<?php echo $status === "approved" ? "current" : ""; ?>">Onaylı (<?php echo esc_html($countsArr["approved"] ?? 0); ?>)</a> |</li>
                <li><a href="<?php echo esc_url($baseUrl . "&status=rejected"); ?>" class="<?php echo $status === "rejected" ? "current" : ""; ?>">Reddedilen (<?php echo esc_html($countsArr["rejected"] ?? 0); ?>)</a></li>
            </ul>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>ID</th><th>Ürün</th><th>Yorumcu</th><th>Puan</th><th>Yorum</th><th>Durum</th><th>Tarih</th><th>İşlem</th></tr></thead>
                <tbody>
                <?php foreach ($reviews as $review) : ?>
                    <?php $product = function_exists("wc_get_product") ? wc_get_product($review->product_id) : null; ?>
                    <tr>
                        <td><?php echo esc_html($review->id); ?></td>
                        <td><?php echo $product ? esc_html($product->get_name()) : "(#" . esc_html($review->trendyol_product_id) . ")"; ?></td>
                        <td><?php echo esc_html($review->reviewer_name); ?></td>
                        <td><?php echo esc_html($review->rating); ?> yıldız</td>
                        <td><?php echo esc_html(mb_substr($review->comment, 0, 80)); ?><?php echo mb_strlen($review->comment) > 80 ? "..." : ""; ?></td>
                        <td><span class="badge status-<?php echo esc_attr($review->status); ?>"><?php echo esc_html($review->status); ?></span></td>
                        <td><?php echo esc_html($review->reviewed_at); ?></td>
                        <td>
                            <?php if ($review->status !== "approved") : ?>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=zolm-booster-reviews&zolm_action=approve&review_id=" . $review->id), "zolm_booster_approve_" . $review->id)); ?>" class="button button-small button-primary">Onayla</a>
                            <?php endif; ?>
                            <?php if ($review->status !== "rejected") : ?>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=zolm-booster-reviews&zolm_action=reject&review_id=" . $review->id), "zolm_booster_reject_" . $review->id)); ?>" class="button button-small">Reddet</a>
                            <?php endif; ?>
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=zolm-booster-reviews&zolm_action=feature&review_id=" . $review->id), "zolm_booster_feature_" . $review->id)); ?>" class="button button-small"><?php echo $review->is_featured ? "Öne çıkarma" : "Öne çıkar"; ?></a>
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url("admin.php?page=zolm-booster-reviews&zolm_action=delete&review_id=" . $review->id), "zolm_booster_delete_" . $review->id)); ?>" class="button button-small button-link-delete" onclick="return confirm('Silmek istediğinize emin misiniz?')">Sil</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <style>.badge{padding:2px 8px;border-radius:3px;font-size:11px}.status-approved{background:#d4edda;color:#155724}.status-pending{background:#fff3cd;color:#856404}.status-rejected{background:#f8d7da;color:#721c24}.status-deleted{background:#e2e3e5;color:#383d41}</style>
        <?php
    }

    public function render_settings_page(): void {
        $apiKey = get_option(ZOLM_BOOSTER_OPTION_KEY);
        $settings = get_option(ZOLM_BOOSTER_SETTINGS, []);
        $restUrl = rest_url("zolm-booster/v1");
        ?>
        <div class="wrap">
            <h1>ZOLM Booster - Trendyol Yorum Ayarları</h1>
            <form method="post" action="options.php">
                <?php settings_fields("zolm_booster_settings_group"); ?>
                <table class="form-table">
                    <tr>
                        <th>API Anahtarı</th>
                        <td>
                            <input type="text" name="<?php echo esc_attr(ZOLM_BOOSTER_OPTION_KEY); ?>" value="<?php echo esc_attr($apiKey); ?>" class="regular-text" readonly />
                            <button type="button" class="button" onclick="if(confirm('Yeni API anahtarı oluşturulsun mu? Mevcut anahtar geçersiz olacak.')){var k='';var c='abcdefghijklmnopqrstuvwxyz0123456789';for(var i=0;i<48;i++){k+=c.charAt(Math.floor(Math.random()*c.length));}document.querySelector('input[name=<?php echo esc_js(ZOLM_BOOSTER_OPTION_KEY); ?>]').value=k;this.form.submit();}">Yeni Anahtar Oluştur</button>
                            <p class="description">Bu anahtarı ZOLM panelinde WooCommerce entegrasyon ayarlarına girin.</p>
                        </td>
                    </tr>
                    <tr><th>Minimum Rating</th><td><select name="<?php echo esc_attr(ZOLM_BOOSTER_SETTINGS); ?>[min_rating]"><?php for ($i = 0; $i <= 5; $i++) : ?><option value="<?php echo esc_attr($i); ?>" <?php selected(($settings["min_rating"] ?? 0), $i); ?>><?php echo esc_html($i === 0 ? "Tümü" : $i . " yıldız ve üzeri"); ?></option><?php endfor; ?></select><p class="description">Bu puanın altındaki yorumlar otomatik gizlenir.</p></td></tr>
                    <tr><th>Maksimum Yorum Sayısı</th><td><input type="number" name="<?php echo esc_attr(ZOLM_BOOSTER_SETTINGS); ?>[max_reviews]" value="<?php echo esc_attr($settings["max_reviews"] ?? 20); ?>" min="1" max="100" /></td></tr>
                    <tr><th>Widget Gösterim Yeri</th><td><select name="<?php echo esc_attr(ZOLM_BOOSTER_SETTINGS); ?>[widget_position]"><?php $pos = $settings["widget_position"] ?? "after_summary"; ?><option value="after_summary" <?php selected($pos, "after_summary"); ?>>Ürün özeti sonrası</option><option value="tab" <?php selected($pos, "tab"); ?>>Tab olarak</option><option value="manual" <?php selected($pos, "manual"); ?>>Manuel shortcode [zolm_reviews]</option></select></td></tr>
                    <tr><th>Trendyol Rozeti Göster</th><td><label><input type="checkbox" name="<?php echo esc_attr(ZOLM_BOOSTER_SETTINGS); ?>[show_trendyol_badge]" value="1" <?php checked(($settings["show_trendyol_badge"] ?? 1), 1); ?> /> Yorumlarda "Trendyol doğrulanmış" rozetini göster</label></td></tr>
                    <tr><th>Avatar Göster (KVKK)</th><td><label><input type="checkbox" name="<?php echo esc_attr(ZOLM_BOOSTER_SETTINGS); ?>[show_avatars]" value="1" <?php checked(($settings["show_avatars"] ?? 0), 1); ?> /> Yorum yapan kişinin avatarını göster</label></td></tr>
                    <tr><th>Kategori/Shop Badge</th><td><label><input type="checkbox" name="<?php echo esc_attr(ZOLM_BOOSTER_SETTINGS); ?>[enable_category_badge]" value="1" <?php checked(($settings["enable_category_badge"] ?? 1), 1); ?> /> Ürün kartlarında mini rating badge göster</label><br><label><input type="checkbox" name="<?php echo esc_attr(ZOLM_BOOSTER_SETTINGS); ?>[enable_shop_trust]" value="1" <?php checked(($settings["enable_shop_trust"] ?? 1), 1); ?> /> Shop sayfasında güven rozeti göster</label></td></tr>
                    <tr><th>Cache Süresi (saniye)</th><td><input type="number" name="<?php echo esc_attr(ZOLM_BOOSTER_SETTINGS); ?>[cache_ttl_seconds]" value="<?php echo esc_attr($settings["cache_ttl_seconds"] ?? 3600); ?>" min="60" max="86400" /></td></tr>
                    <tr><th>Canary Deploy (%)</th><td><input type="number" name="<?php echo esc_attr(ZOLM_BOOSTER_SETTINGS); ?>[canary_percentage]" value="<?php echo esc_attr($settings["canary_percentage"] ?? 100); ?>" min="0" max="100" /><p class="description">0 tamamen kapalı, 100 tüm ürünler.</p></td></tr>
                </table>
                <?php submit_button("Kaydet"); ?>
            </form>
            <hr>
            <h3>REST API Endpoint'leri</h3>
            <table class="form-table">
                <tr><th>Batch Yorum Gönderimi</th><td><code>POST <?php echo esc_html($restUrl); ?>/reviews/batch</code></td></tr>
                <tr><th>Tek Yorum</th><td><code>POST <?php echo esc_html($restUrl); ?>/reviews</code></td></tr>
                <tr><th>Ürün Yorumları</th><td><code>GET <?php echo esc_html($restUrl); ?>/products/{id}/reviews</code></td></tr>
                <tr><th>Ürün Rating</th><td><code>GET <?php echo esc_html($restUrl); ?>/products/{id}/rating</code></td></tr>
                <tr><th>İstatistikler</th><td><code>GET <?php echo esc_html($restUrl); ?>/stats</code></td></tr>
                <tr><th>Ürün Eşleştir</th><td><code>POST <?php echo esc_html($restUrl); ?>/products/match</code></td></tr>
                <tr><th>Auth Header</th><td><code>X-ZOLM-API-Key: [api_anahtarınız]</code></td></tr>
            </table>
        </div>
        <?php
    }

    public function render_whatsapp_page(): void {
        $legacyActive = ZOLM_Booster_Modules::instance()->legacy_whatsapp_plugin_active();
        $nonce = wp_create_nonce("zolm_wa_test_connection");
        ?>
        <div class="wrap">
            <h1>ZOLM Booster - WhatsApp Modülü</h1>
            <?php if ($legacyActive) : ?>
                <div class="notice notice-warning"><p>Ayrı <strong>ZOLM WhatsApp Booster</strong> eklentisi aktif. Çift webhook oluşmaması için birleşik modül beklemede. Eski eklentiyi etkisizleştirince buradaki ayarlar kullanılacak.</p></div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields("zolm_booster_whatsapp_settings_group"); ?>
                <h2>Webhook Bağlantısı</h2>
                <table class="form-table">
                    <tr><th>ZOLM URL</th><td><input type="url" name="zolm_wa_booster_zolm_url" value="<?php echo esc_attr(get_option("zolm_wa_booster_zolm_url", "")); ?>" class="regular-text" placeholder="https://panel.example.com/api/whatsapp/booster/event"><p class="description">ZOLM WhatsApp Booster webhook endpoint URL'i.</p></td></tr>
                    <tr><th>Webhook Secret</th><td><input type="password" name="zolm_wa_booster_webhook_secret" value="<?php echo esc_attr(get_option("zolm_wa_booster_webhook_secret", "")); ?>" class="regular-text"><p class="description">HMAC imza doğrulama anahtarı.</p></td></tr>
                    <tr><th>Store ID</th><td><input type="number" name="zolm_wa_booster_store_id" value="<?php echo esc_attr((int) get_option("zolm_wa_booster_store_id", 0)); ?>" min="0"></td></tr>
                    <tr><th>Test Modu</th><td><input type="hidden" name="zolm_wa_booster_test_mode" value="no"><label><input type="checkbox" name="zolm_wa_booster_test_mode" value="yes" <?php checked(get_option("zolm_wa_booster_test_mode", "no"), "yes"); ?>> Sadece log yaz, gerçek webhook gönderme</label></td></tr>
                </table>
                <h2>Yüzen WhatsApp Butonu</h2>
                <table class="form-table">
                    <tr><th>Aktif</th><td><input type="hidden" name="zolm_wa_fb_enabled" value="no"><label><input type="checkbox" name="zolm_wa_fb_enabled" value="yes" <?php checked(get_option("zolm_wa_fb_enabled", "yes"), "yes"); ?>> Yüzen butonu göster</label></td></tr>
                    <tr><th>WhatsApp Numarası</th><td><input type="text" name="zolm_wa_fb_phone" value="<?php echo esc_attr(get_option("zolm_wa_fb_phone", "")); ?>" class="regular-text" placeholder="905551234567"></td></tr>
                    <tr><th>Ön Mesaj</th><td><textarea name="zolm_wa_fb_message" class="large-text" rows="3"><?php echo esc_textarea(get_option("zolm_wa_fb_message", "Merhaba! Size nasıl yardımcı olabilirim?")); ?></textarea></td></tr>
                    <tr><th>Pozisyon</th><td><select name="zolm_wa_fb_position"><option value="bottom-right" <?php selected(get_option("zolm_wa_fb_position", "bottom-right"), "bottom-right"); ?>>Sağ Alt</option><option value="bottom-left" <?php selected(get_option("zolm_wa_fb_position", "bottom-right"), "bottom-left"); ?>>Sol Alt</option></select></td></tr>
                    <tr><th>Buton Rengi</th><td><input type="text" name="zolm_wa_fb_color" value="<?php echo esc_attr(get_option("zolm_wa_fb_color", "#25D366")); ?>" class="regular-text" placeholder="#25D366"></td></tr>
                    <tr><th>Buton Boyutu</th><td><input type="number" name="zolm_wa_fb_icon_size" value="<?php echo esc_attr((int) get_option("zolm_wa_fb_icon_size", 60)); ?>" min="44" max="96"> px</td></tr>
                    <tr><th>Görünürlük</th><td><input type="hidden" name="zolm_wa_fb_show_on_mobile" value="no"><label><input type="checkbox" name="zolm_wa_fb_show_on_mobile" value="yes" <?php checked(get_option("zolm_wa_fb_show_on_mobile", "yes"), "yes"); ?>> Mobilde göster</label><br><input type="hidden" name="zolm_wa_fb_show_on_desktop" value="no"><label><input type="checkbox" name="zolm_wa_fb_show_on_desktop" value="yes" <?php checked(get_option("zolm_wa_fb_show_on_desktop", "yes"), "yes"); ?>> Masaüstünde göster</label></td></tr>
                    <tr><th>Tooltip</th><td><input type="text" name="zolm_wa_fb_tooltip" value="<?php echo esc_attr(get_option("zolm_wa_fb_tooltip", "Bize ulaşın!")); ?>" class="regular-text"></td></tr>
                    <tr><th>Animasyon</th><td><select name="zolm_wa_fb_animation"><?php foreach (["none" => "Yok", "pulse" => "Nabız", "bounce" => "Zıplama", "shake" => "Sallanma"] as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected(get_option("zolm_wa_fb_animation", "pulse"), $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></td></tr>
                    <tr><th>Gecikme</th><td><input type="number" name="zolm_wa_fb_delay" value="<?php echo esc_attr((int) get_option("zolm_wa_fb_delay", 0)); ?>" min="0"> saniye</td></tr>
                </table>
                <?php submit_button("WhatsApp Ayarlarını Kaydet"); ?>
            </form>
            <hr>
            <h2>Bağlantı Testi</h2>
            <p>ZOLM Booster webhook bağlantısını test eder. Hiçbir müşteri kaydı oluşturmaz.</p>
            <button type="button" id="zolm-wa-test-btn" class="button button-secondary">Bağlantıyı Test Et</button>
            <div id="zolm-wa-test-result" style="display:none;margin-top:10px;padding:10px;border-radius:4px;font-size:13px;"></div>
            <script>
            jQuery(function($){$('#zolm-wa-test-btn').on('click',function(){var btn=$(this);var result=$('#zolm-wa-test-result');btn.prop('disabled',true).text('Test ediliyor...');result.hide().removeClass('notice-success notice-error').addClass('notice');$.post(ajaxurl,{action:'zolm_wa_test_connection',nonce:'<?php echo esc_js($nonce); ?>'},function(response){if(response.success){result.addClass('notice-success').text(response.data.message).show();}else{result.addClass('notice-error').text(response.data.message).show();}}).fail(function(){result.addClass('notice-error').text('Bağlantı hatası. Lütfen tekrar deneyin.').show();}).always(function(){btn.prop('disabled',false).text('Bağlantıyı Test Et');});});});
            </script>
            <h2>Endpoint'ler</h2>
            <table class="form-table">
                <tr><th>WooCommerce → ZOLM</th><td><code><?php echo esc_html(get_option("zolm_wa_booster_zolm_url", "")); ?></code></td></tr>
                <tr><th>ZOLM → WooCommerce</th><td><code>POST <?php echo esc_html(rest_url("zolm-wa-booster/v1/webhook")); ?></code></td></tr>
                <tr><th>Stok Bildirimi</th><td><code>POST <?php echo esc_html(rest_url("zolm-wa-booster/v1/stock-notify/{product_id}")); ?></code></td></tr>
                <tr><th>Shortcode</th><td><code>[zolm_stock_notify product_id="123"]</code></td></tr>
            </table>
        </div>
        <?php
    }

    private function render_disabled_notice(string $moduleName): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($moduleName); ?></h1>
            <div class="notice notice-warning"><p>Bu modül şu anda kapalı. <a href="<?php echo esc_url(admin_url("admin.php?page=zolm-booster")); ?>">Modüller</a> ekranından aktif edebilirsiniz.</p></div>
        </div>
        <?php
    }

    private function render_admin_styles(): void {
        ?>
        <style>
            .zolm-module-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin:20px 0}
            .zolm-module-card{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
            .zolm-module-card h2{margin:0 0 8px;font-size:18px}.zolm-module-card p{margin:0;color:#646970}.zolm-toggle{display:flex;gap:8px;align-items:center;font-weight:600;white-space:nowrap}.zolm-admin-links{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
        </style>
        <?php
    }
}
