# WhatsApp Modülü — Production Release Runbook

**Sürüm:** 1.0.0 (Sprint 1A/1B/1C)
**Tarih:** 2026-07-04
**Sorumlu:** ZOLM Operasyon

---

## 1. Laravel Deploy Öncesi Kontrol Listesi

### 1.1 Ortam Hazırlığı

- [ ] `git pull` ile son commit çekildi (`51d1667`)
- [ ] `composer install --no-dev --optimize-autoloader` çalıştırıldı
- [ ] `npm run build` ile Vite assets üretildi
- [ ] `.env` dosyası production ile uyumlu (DB, mail, queue driver)
- [ ] `APP_DEBUG=false` ve `APP_ENV=production` doğrulandı
- [ ] `QUEUE_CONNECTION` `sync` DEĞİL, `database` veya `redis` olarak ayarlı

### 1.2 WhatsApp Env Değerleri

Aşağıdaki değişkenler `.env`'de tanımlı olmalı:

```
WHATSAPP_ENABLED=false          ← İlk aşamada KAPALI
WHATSAPP_TEST_MODE=true
WHATSAPP_TEST_PHONE_NUMBERS=    ← Test numarası eklenecek
WHATSAPP_META_APP_SECRET=
WHATSAPP_META_GRAPH_VERSION=v25.0
WHATSAPP_WEBHOOK_VERIFY_TOKEN=  ← Rastgele üretilmiş string
WHATSAPP_OUTBOX_QUEUE=default
WHATSAPP_WEBHOOK_QUEUE=default
```

> **NOT:** `WHATSAPP_ENABLED=false` olarak bırakılacak. Canlıya alma sonrası admin panelinden açılacak.

---

## 2. DB Yedeği Doğrulaması

### 2.1 Yedek Alma

```bash
mysqldump -h DB_HOST -u DB_USER -p DB_NAME > zolm-before-whatsapp-$(date +%Y%m%d).sql
```

### 2.2 Doğrulama

```bash
# Satır sayısını kontrol et
wc -l zolm-before-whatsapp-*.sql

# Tablo sayısını kontrol et
grep -c "CREATE TABLE" zolm-before-whatsapp-*.sql
```

- [ ] Yedek dosyası mevcut ve büyüklüğü makul
- [ ] Yedek dizine kopyalandı (sunucu dışı yedekleme)

---

## 3. Migration Kontrolü

### 3.1 Pending Migration Kontrolü

```bash
php artisan migrate:status | grep -i whatsapp
```

Beklenen çıktı:
```
2026_07_04_100000_create_whatsapp_module_tables .... Pending
```

### 3.2 Migration Çalıştırma

```bash
php artisan migrate --force
```

### 3.3 Doğrulama

```bash
# 13 tablonun oluştuğunu kontrol et
php artisan tinker --execute="
\$tables = ['wa_accounts','wa_settings','wa_contacts','wa_contact_preferences','wa_consent_events','wa_suppressions','wa_outbox','wa_message_deliveries','wa_webhook_events','wa_templates','wa_conversations','wa_inbound_messages','wa_audit_logs'];
foreach (\$tables as \$t) { echo (\Illuminate\Support\Facades\Schema::hasTable(\$t) ? 'OK' : 'MISSING') . ' ' . \$t . PHP_EOL; }
"
```

Beklenen çıktı: 13 tablonun tamamı `OK`

---

## 4. Queue Worker ve Scheduler Sağlık Kontrolü

### 4.1 Queue Worker

```bash
# Worker çalışıyor mu?
ps aux | grep "queue:work"

# Kuyrukta bekleyen job var mı?
php artisan tinker --execute="echo 'Jobs: ' . Illuminate\Support\Facades\DB::table('jobs')->count();"
```

- [ ] Queue worker aktif
- [ ] Kuyrukta bekleyen eski job yok (son 10 dk içinde)

### 4.2 Scheduler

```bash
php artisan schedule:list | grep -i whatsapp
```

Beklenen çıktı:
```
whatsapp-process-outbox    * * * * *    *
whatsapp-retry-failed     */5 * * * *  *
whatsapp-retention-cleanup 0 4 * * *   *
```

### 4.3 Cache Temizliği

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

---

## 5. Feature Flag Başlangıç Durumu

| Flag | Başlangıç Değeri | Açıklama |
|------|------------------|----------|
| `WHATSAPP_ENABLED` | `false` | Tüm WhatsApp UI ve modülünü devre dışı bırakır |
| `WHATSAPP_TEST_MODE` | `true` | Sadece test numaralarına mesaj gider |
| Meta webhook endpoint | Aktif | Feature flag'den bağımsız olarak çalışır |
| Kargo bildirimi | Kapalı | `wa_settings.shipping.enabled = false` |

### Canlıya Alma Sırası

1. Meta hesap bağlanır (Hesap Ayarları → Kaydet → Bağlantıyı Test Et)
2. Şablonlar senkronize edilir (Şablonlar → Senkronize Et)
3. `WHATSAPP_ENABLED=true` yapılır
4. Test modu aktif kalır — test numarasına kargo bildirimi gönderilir
5. Doğrulama: test numarasına WhatsApp mesajı ulaşır
6. `WHATSAPP_TEST_MODE=false` yapılır
7. Kargo bildirimi açılır (`wa_settings.shipping.enabled = true`)

---

## 6. WordPress Plugin Yükleme

### 6.1 Dosya Yapısı

```
zolm-whatsapp-booster/
├── zolm-whatsapp-booster.php     (ana plugin)
├── includes/
│   └── class-zolm-whatsapp-settings.php  (WC ayarları)
└── stock-notify-form.php          (stok bildirim shortcode)
```

### 6.2 Yükleme Adımları

1. `wordpress-plugins/zolm-whatsapp-booster/` klasörünü ZIP olarak sıkıştır
2. WordPress admin → Eklentiler → Yeni Ekle → Yükle
3. ZIP dosyasını seç → "Şimdi Kur" tıkla
4. "Eklentiyi Etkinleştir" tıkla

### 6.3 Yapılandırma

1. WooCommerce → Ayarlar → WhatsApp (ZOLM) sekmesi
2. ZOLM URL: `https://m.zolm.com.tr/api/whatsapp/booster/event`
3. Webhook Secret: production app key ile aynı değer
4. Store ID: ZOLM'deki WooCommerce mağaza ID'si
5. "Bağlantıyı Test Et" butonuna bas → yeşil "Bağlantı başarılı" beklenir
6. "Ayarları Kaydet"

### 6.4 Doğrulama

- [ ] WooCommerce → Ayarlar altında "WhatsApp (ZOLM)" sekmesi görünüyor
- [ ] "Bağlantıyı Test Et" yeşil döndü
- [ ] Checkout'ta "İletişim Tercihleri" checkbox'ları görünüyor
- [ ] Üye kayıt formunda consent checkbox'ları görünüyor
- [ ] My Account sayfasında "WhatsApp İletişim Tercihleri" bölümü var

---

## 7. WordPress → ZOLM Health.check Bağlantı Testi

### 7.1 Manuel Test

WordPress admin → WooCommerce → Ayarlar → WhatsApp (ZOLM) → "Bağlantıyı Test Et"

Beklenen sonuç: "Bağlantı başarılı! Store ID: X"

### 7.2 Hata Durumları

| Hata Mesajı | Sebep | Çözüm |
|-------------|-------|-------|
| "ZOLM URL, Webhook Secret ve Store ID girilmeli" | Eksik alan | Tüm alanları doldur |
| "Bağlantı kurulamadı: ..." | URL yanlış veya sunucu erişilemez | URL'yi ve HTTPS'i kontrol et |
| "Invalid signature" | Secret eşleşmiyor | ZOLM app key ile aynı secret olmalı |
| "WooCommerce store not found" | Store ID yanlış veya pasif | ZOLM'de store ID'yi doğrula |

---

## 8. Meta Test Modu ile Tek Numara Testi

### 8.1 Hazırlık

1. Meta Business Manager'dan test amaçlı bir template onaylanmalı
2. `WHATSAPP_TEST_PHONE_NUMBERS`'a test numarası eklenmeli
3. `WHATSAPP_TEST_MODE=true` olmalı

### 8.2 Test Akışı

1. ZOLM admin → WhatsApp → Hesap Ayarları → hesap kaydedildi
2. Meta Business Manager'dan WABA ID, Phone Number ID, Access Token girildi
3. "Bağlantıyı Test Et" → başarılı
4. Şablonlar → "Senkronize Et" → onaylı şablonlar listelendi
5. Kargo Bildirimleri → enabled, aşama seçildi, template atandı
6. `WHATSAPP_ENABLED=true` yapıldı
7. Test numarasına gerçek bir WC siparişi oluşturuldu
8. Kargo bilgisi girildi → outbox'a mesaj yazıldı
9. Meta webhook ile `delivered` durumu alındı

### 8.3 Doğrulama

- [ ] `wa_outbox` tablosunda mesaj kaydı var (status=sent veya delivered)
- [ ] `wa_message_deliveries` tablosunda teslimat kaydı var
- [ ] `wa_audit_logs`'da `shipping_notification_queued` kaydı var
- [ ] Test numarasına WhatsApp mesajı ulaştı

---

## 9. Rollback Adımları

### 9.1 Hızlı Geri Alma (Feature Flag)

```bash
# WhatsApp modülünü devre dışı bırak
# .env'de:
WHATSAPP_ENABLED=false
```

Bu işlem:
- Tüm WhatsApp admin ekranlarını kapatır (404)
- Meta webhook endpoint'i **çalışmaya devam eder** (veri kaybı olmaz)
- Kargo bildirimleri durur
- Mevcut outbox mesajları kuyrukta kalır

### 9.2 Tam Geri Alma (Migration Rollback)

```bash
php artisan migrate:rollback --step=1
```

> **DİKKAT:** `wa_outbox` tablosunda gönderilmemiş mesajlar varsa önce temizlenmeli.

```bash
# Outbox temizliği (opsiyonel)
php artisan tinker --execute="Illuminate\Support\Facades\DB::table('wa_outbox')->truncate();"
```

### 9.3 Dosya Geri Alma

```bash
git checkout HEAD~1 -- \
  config/whatsapp.php \
  app/Models/Wa*.php \
  app/Services/WhatsApp/ \
  app/Http/Controllers/WhatsApp/ \
  app/Jobs/WhatsApp/ \
  app/Livewire/WhatsApp/ \
  resources/views/livewire/whatsapp/ \
  routes/api.php \
  routes/web.php \
  routes/console.php
```

### 9.4 WordPress Plugin Kaldırma

1. WordPress admin → Eklentiler
2. "ZOLM WhatsApp Booster" → "Kaldır"
3. Veritabanı temizliği gerekmez (wp_options'daki ayarlar kalır)

### 9.5 Rollback Sonrası Kontrol

- [ ] `php artisan route:list | grep whatsapp` → route'lar silinmeli veya 404 dönmeli
- [ ] `php artisan migrate:status | grep whatsapp` → migration'lar hâlâ Ran olarak kalabilir
- [ ] Queue worker yeniden başlatıldı (`queue:restart`)
- [ ] Cache temizlendi

---

## Acil Durum Kontakt

| Rol | Kişi | İletişim |
|-----|------|---------|
| Backend | — | — |
| WordPress | — | — |
| Meta/WhatsApp | — | — |

---

## Değişiklik Günlüğü

| Tarih | Değişiklik | Sorumlu |
|-------|-----------|---------|
| 2026-07-04 | Sprint 1A/1B/1C release | ZOLM |
