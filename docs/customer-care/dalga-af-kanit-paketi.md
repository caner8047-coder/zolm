# Dalga AF — Kanıt Paketi
## Enterprise Integration Hub

**Tarih:** 2026-07-13  
**Durum:** Kalite Kapısı 01 Revizyonu Tamamlandı

---

## 1. Amaç

Dalga AF; dış sistemlere (CRM/ERP/Helpdesk) olay bazlı veri transferi sağlayan, signed webhook imzalama, retry kuyruğu, dead-letter kuyruğu ve DB-seviyesi idempotency garantisi sunan entegrasyon merkezidir.

---

## 2. Değişen Dosyalar

| Dosya | Değişiklik |
|---|---|
| [`CustomerCareIntegrationHubService.php`](../../../app/Services/Support/Integration/CustomerCareIntegrationHubService.php) | P0-2: Webhook secret şifreli saklama/çözme, boş secret durumunda fail-closed gönderim engeli, log hata maskeleme |
| [`Integrations.php`](../../../app/Livewire/CustomerCare/Integrations.php) | P1-1: `retryDelivery()` store boundary guard; Webhook secret şifreleme ve kaydetme |
| [`CustomerCareIntegrationHubTest.php`](../../../tests/Feature/CustomerCare/CustomerCareIntegrationHubTest.php) | 7 test (HMAC, retry/DLQ, route, encrypted secret, empty secret fail-closed, retry store limit, unique index) |
| [`routes/web.php`](../../../routes/web.php) | P0-1: `/customer-care/integrations` rotası `customer-care.feature:integration_hub_enabled` middleware'ine bağlandı |
| [`config/customer-care.php`](../../../config/customer-care.php) | `integration_hub_enabled` özellik bayrağı |

---

## 3. Migration / Rollback Kanıtı

Bu dalga için `2026_07_30_110000_create_integration_hub_tables.php` tablosu oluşturulmuştur.

### Migration Up:
```bash
php artisan migrate
# support_integration_events ve support_integration_deliveries tabloları oluşturuldu.
# support_integration_events üzerinde (store_id, idempotency_key) unique index eklendi.
```

### Rollback:
```bash
php artisan migrate:rollback --path=database/migrations/2026_07_30_110000_create_integration_hub_tables.php
# Tablolar başarıyla kaldırıldı.
```

---

## 4. Test İsimleri (7 test / 22 assertion)

```
✓ webhook_dispatches_with_hmac_and_pii_redacted
✓ webhook_retries_and_falls_to_dead_letter
✓ integration_route_blocks_when_flag_off
✓ webhook_secret_is_stored_encrypted_in_channel_config
✓ empty_secret_fails_closed_without_http_call
✓ retry_delivery_enforces_store_boundaries
✓ db_idempotency_unique_index
```

---

## 5. Feature Flag Varsayılanları

| Flag | ENV | Varsayılan |
|---|---|---|
| `integration_hub_enabled` | `CUSTOMER_CARE_INTEGRATION_HUB_ENABLED` | `false` |

---

## 6. Güvenlik ve Kalite Kapısı Düzeltmeleri (P0-2, P1-1)

### P0-2 ✅ — Webhook Secret Şifreleme ve Boş Secret Engeli
- **Şifreli Saklama:** Webhook secret verileri veritabanına yazılmadan önce `Crypt::encryptString()` ile şifrelenir ve okurken `Crypt::decryptString()` ile çözülür. Geriye uyumluluk için çözülemezse düz metin fallback korunmaktadır.
- **Empty Secret Fail-Closed:** Webhook secret boş/eksik ise outbound HTTP isteği kesinlikle yapılmaz, gönderim fail-closed olarak doğrudan `failed` durumuna çekilir ve hata sebebi kaydedilir.
- **Log / Hata Maskeleme:** Hatalar veritabanına veya loglara yazılırken `PiiRedactor` ile maskelenerek sızıntı riski önlenmiştir.

### P1-1 ✅ — Retry Delivery Store İzolasyonu ve DB Idempotency
- **Retry İzolasyonu:** `retryDelivery()` metodu çalıştırılan delivery kaydının bağlı olduğu event'in `selectedStoreId` ile eşleştiğini doğrular. Mağazalar arası yetkisiz retry tetiklenmesi engellenmiştir.
- **DB-level Idempotency:** `support_integration_events` tablosunda `(store_id, idempotency_key)` alanlarına unique constraint eklenerek race condition durumlarında çift olay kaydı engellenmiştir.
