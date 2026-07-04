# WhatsApp Modülü — Production Kontrol Listesi

**Commit:** `51d1667` | **Tarih:** 2026-07-04

---

## A. Deploy Öncesi

- [ ] DB yedeği alındı (mysqldump) ve doğrulandı
- [ ] `git pull` ile son commit çekildi
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `npm run build`
- [ ] `.env`'de `APP_DEBUG=false`, `APP_ENV=production`, `QUEUE_CONNECTION` sync değil

---

## B. WhatsApp Env Tanımları

- [ ] `WHATSAPP_ENABLED=false` (ilk aşamada kapalı)
- [ ] `WHATSAPP_TEST_MODE=true`
- [ ] `WHATSAPP_TEST_PHONE_NUMBERS=<test numarası>`
- [ ] `WHATSAPP_META_APP_SECRET=<Meta app secret>`
- [ ] `WHATSAPP_META_GRAPH_VERSION=v25.0`
- [ ] `WHATSAPP_WEBHOOK_VERIFY_TOKEN=<rastgele string>`

---

## C. Migration

- [ ] `php artisan migrate:status | grep whatsapp` → Pending görünüyor
- [ ] `php artisan migrate --force` çalıştırıldı
- [ ] 13 tablo oluştu doğrulandı (wa_accounts ... wa_audit_logs)

---

## D. Cache ve Queue

- [ ] `config:clear && cache:clear && route:clear && view:clear`
- [ ] `config:cache && route:cache && view:cache`
- [ ] `queue:restart`
- [ ] `schedule:list | grep whatsapp` → 3 schedule görünüyor
- [ ] Queue worker aktif

---

## E. Feature Flag Durumu

| Flag | Değer |
|------|-------|
| `WHATSAPP_ENABLED` | `false` |
| `WHATSAPP_TEST_MODE` | `true` |
| `shipping.enabled` (wa_settings) | `false` |

---

## F. WordPress Plugin

- [ ] `zolm-whatsapp-booster` ZIP olarak sıkıştırıldı
- [ ] WordPress admin → Eklentiler → Yükle → Etkinleştir
- [ ] WooCommerce → Ayarlar → WhatsApp (ZOLM) sekmesi görünüyor
- [ ] ZOLM URL, Webhook Secret, Store ID dolduruldu
- [ ] "Bağlantıyı Test Et" → yeşil "Bağlantı başarılı"
- [ ] Checkout'ta consent checkbox'ları görünüyor
- [ ] My Account'ta tercih yönetimi görünüyor

---

## G. Meta Ön Koşulları

- [ ] Meta Business Manager'da WABA hesabı bağlı
- [ ] Phone Number ID doğrulandı
- [ ] Test amaçlı utility template onaylandı (status: APPROVED)
- [ ] Access Token production'a girildi
- [ ] Webhook URL Meta'ya tanıtıldı: `https://m.zolm.com.tr/api/whatsapp/webhook`
- [ ] Webhook verify token Meta'ya girildi

---

## H. ZOLM Hesap Bağlantısı

- [ ] WhatsApp → Hesap Ayarları → WC mağazası seçildi
- [ ] WABA ID, Phone Number ID girildi
- [ ] "Bağlantıyı Test Et" → başarılı (display_phone_number otomatik geldi)
- [ ] Kaydedildi

---

## I. Şablon Senkronizasyonu

- [ ] WhatsApp → Şablonlar → "Senkronize Et" tıklandı
- [ ] Onaylı utility şablonları listelendi

---

## J. Tek Numara Testi

- [ ] `WHATSAPP_ENABLED=true` yapıldı
- [ ] ZOLM → Hesap Ayarları → test numarası `WHATSAPP_TEST_PHONE_NUMBERS`'da
- [ ] Kargo Bildirimleri → enabled, aşama seçildi, template atandı
- [ ] Test numarasına WC siparişi + kargo bilgisi girildi
- [ ] `wa_outbox`'ta mesaj kaydı oluştu (status: sent/delivered)
- [ ] `wa_message_deliveries`'de teslimat kaydı oluştu
- [ ] Test numarasına WhatsApp mesajı ulaştı

---

## K. Canlıya Alma

- [ ] `WHATSAPP_TEST_MODE=false` yapıldı
- [ ] Kargo bildirimi açıldı (`shipping.enabled = true`)
- [ ] Tüm WooCommerce müşterilerine yönelik test (kendi numaranızla)

---

## L. Rollback Noktaları

| Nokta | İşlem |
|-------|-------|
| Hızlı | `WHATSAPP_ENABLED=false` |
| Orta | `WHATSAPP_ENABLED=false` + `shipping.enabled=false` |
| Tam | `migrate:rollback --step=1` + dosya geri alma + `queue:restart` |
| WordPress | Eklentiyi kaldır (veritabanı temizliği gerekmez) |

---

## Acil Durum

- Backend sorun → `WHATSAPP_ENABLED=false`
- Meta bağlantısı koptu → Hesap Ayarları → token güncelle → Bağlantıyı Test Et
- Toplu hata → `queue:restart` + `php artisan whatsapp:retry-failed`
