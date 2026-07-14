# Dalga AJ — Kanıt Paketi
## Production Reliability, Queue Backpressure ve Dead-Letter Operasyonları

**Tarih:** 2026-07-13  
**Durum:** Kalite Kapısı 01 Revizyonu Tamamlandı

---

## 1. Amaç

Dalga AJ; kuyruk hata oranları, backpressure (aşırı yük) koruma mekanizmaları, rate limiters ve başarısız (dead-letter) webhook veya giden mesajların güvenli ve yetkili biçimde yeniden tetiklenmesi süreçlerini yönetir.

---

## 2. Değişen Dosyalar

| Dosya | Değişiklik |
|---|---|
| [`CustomerCareReplayDeadlettersCommand.php`](../../../app/Console/Commands/CustomerCareReplayDeadlettersCommand.php) | P0-6: Replay komutu default dry-run olarak çalışacak şekilde değiştirildi (`--execute` zorunludur). Sistem aktörü yetki ve onay kontrolleri eklendi. P0-7: Plaintext webhook secret fallback kaldırıldı; geçersiz/çözülemeyen secret durumunda replay fail-closed atlanır. |
| [`CustomerCareRateLimiter.php`](../../../app/Services/Support/Reliability/CustomerCareRateLimiter.php) | P1-1: Kanal key/type canonical mapping yapısı kuruldu. Bilinmeyen/konfigüre edilmemiş kanallar için fail-closed (false) dönüşü sağlandı. |
| [`CustomerCareQueueHealthService.php`](../../../app/Services/Support/Reliability/CustomerCareQueueHealthService.php) | P1-2: Hiç kuyruk verisi yoksa durum `unknown` olarak dönülerek healthy durumundan net olarak ayrıştırıldı. |
| [`Reliability.php`](../../../app/Livewire/CustomerCare/Reliability.php) | Livewire action'ları üzerinden tetiklenen `Artisan::call` komutlarına `--execute` parametresi dahil edildi. |
| [`reliability.blade.php`](../../../resources/views/livewire/customer-care/reliability.blade.php) | `unknown` (veri yok) durumu için nötr (gri) görsel banner tasarımı uygulandı. |
| [`CustomerCareReliabilityTest.php`](../../../tests/Feature/CustomerCare/CustomerCareReliabilityTest.php) | Bilinmeyen kanal rate limit engeli, unknown backpressure durumu ve dry-run/execute yetki CLI testleri eklendi. |
| [`routes/web.php`](../../../routes/web.php) | `/customer-care/reliability` rotası `reliability_enabled` bayrağı ile korunur. |

---

## 3. Migration / Rollback Kanıtı

Bu dalga için herhangi bir ek veritabanı şeması veya yeni migration dosyası gerekmemiştir. Mevcut güvenilirlik tablolaları (`support_dispatches`, `support_dispatch_attempts` ve `support_integration_deliveries`) kullanılmaktadır.

---

## 4. Test İsimleri (7 test / 22 assertion)

```
✓ reliability route blocks when flag off
✓ backpressure blocks auto reply but allows manual
✓ rate limit blocks outbound send
✓ db unique constraint blocks duplicate integration event
✓ unknown channel fail closed rate limit
✓ unknown backpressure status when no data
✓ dead letter replay cli defaults to dry run and enforces execute and approval
```

---

## 5. Feature Flag Varsayılanları

| Flag | ENV | Varsayılan |
|---|---|---|
| `reliability_enabled` | `CUSTOMER_CARE_RELIABILITY_ENABLED` | `false` |

---

## 6. Güvenlik ve Kalite Kapısı Düzeltmeleri (P0-6 & P0-7)

### P0-6 ✅ — Güvenli CLI Safe Defaults ve Onay Mekanizması
- `customer-care:replay-deadletters` komutu varsayılan olarak dry-run modunda çalışır. Veritabanını mutate etmek için explicit `--execute` parametresi gönderilmesi zorunludur.
- `--execute` parametresi verildiğinde, komut sistem aktörü üzerinden service-level yetki (`run_compliance`) ve `replay_deadletters` işlem onayını (`enforceApproval()`) denetler. Onaylanmamış bir talep var ise işlem fail-closed durdurulur ve bekleyen talep oluşturulur.

### P0-7 ✅ — Plaintext Secret Replay Fallback Kaldırılması
- CLI entegrasyon replay işleminde plaintext şifre çözme fallback'i tamamen kaldırılmıştır. Webhook secret alanı boşsa veya Laravel `Crypt` tarafından çözülemiyorsa replay işlemi fail-closed olarak atlanır ve hata kaydedilir.
