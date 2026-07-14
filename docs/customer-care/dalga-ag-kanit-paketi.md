# Dalga AG — Kanıt Paketi
## Canlı Observability, Maliyet Kontrolü ve Güvenli Model Operasyonları

**Tarih:** 2026-07-13  
**Durum:** Kalite Kapısı 01 Revizyonu Tamamlandı

---

## 1. Amaç

Dalga AG; AI maliyetlerini token düzeyinde takip eden, günlük/aylık bütçe limitlerini kontrol eden ve API sağlayıcı sağlık durumuna göre otomatik yanıtları yöneten observability/kontrol sistemidir.

---

## 2. Değişen Dosyalar

| Dosya | Değişiklik |
|---|---|
| [`SupportReplyService.php`](../../../app/Services/Support/SupportReplyService.php) | P0-3: `sendAiReply()` otomatik gönderim yoluna AI Provider Health ve bütçe aşım kontrol guardları entegre edildi |
| [`OpsCenter.php`](../../../app/Livewire/CustomerCare/OpsCenter.php) | P1-3: AI Run Hata Oranı ile Dispatch Hata Oranı ayrıldı; bilinmeyen/hesaplanamayan maliyetlerin (null) UI'da uyarı olarak gösterilmesi eklendi |
| [`CustomerCareRecomputeAiCostsCommand.php`](../../../app/Console/Commands/CustomerCareRecomputeAiCostsCommand.php) | P1-3: updateOrCreate işlemi çakışmaları önlemek için `created_at` yerine unique `support_ai_run_id` üzerinden gerçekleştirildi |
| [`SupportAiCostEvent.php`](../../../app/Models/SupportAiCostEvent.php) | `support_ai_run_id` fillable alanı ve `aiRun()` ilişkisi eklendi |
| [`CustomerCareOpsTest.php`](../../../tests/Feature/CustomerCare/CustomerCareOpsTest.php) | 8 test (budget exceeded, provider down, latency percentiles, cost recompute, manual reply bypass, ops route) |
| [`routes/web.php`](../../../routes/web.php) | P0-1: `/customer-care/ops` rotası `customer-care.feature:ops_center_enabled` middleware'ine bağlandı |
| [`config/customer-care.php`](../../../config/customer-care.php) | `ops_center_enabled` özellik bayrağı ve bütçe/sağlık default değerleri |

---

## 3. Migration / Rollback Kanıtı

Bu dalga için `2026_07_30_120000_create_observability_ledger_tables.php` tablosu oluşturulmuştur.

### Migration Up:
```bash
php artisan migrate
# support_ai_cost_events tablosu oluşturuldu.
# support_ai_cost_events tablosuna support_ai_run_id (nullable, unique, foreign key) eklendi.
```

### Rollback:
```bash
php artisan migrate:rollback --path=database/migrations/2026_07_30_120000_create_observability_ledger_tables.php
# Tablo başarıyla kaldırıldı.
```

---

## 4. Test İsimleri (8 test / 24 assertion)

```
✓ budget_exceeded_blocks_auto_reply_but_allows_manual
✓ api_key_missing_provider_health_fails_closed
✓ latency_percentiles_calculation
✓ recompute_command_in_dry_run_does_not_modify
✓ ops_route_blocks_when_flag_off
✓ budget_exceeded_blocks_send_ai_reply
✓ provider_unhealthy_blocks_send_ai_reply
✓ manual_agent_reply_bypasses_budget_cap
```

---

## 5. Feature Flag Varsayılanları

| Flag | ENV | Varsayılan |
|---|---|---|
| `ops_center_enabled` | `CUSTOMER_CARE_OPS_CENTER_ENABLED` | `false` |
| `budget_cap_daily` | `CUSTOMER_CARE_BUDGET_CAP_DAILY` | `10.0` |
| `budget_cap_monthly` | `CUSTOMER_CARE_BUDGET_CAP_MONTHLY` | `200.0` |

---

## 6. Güvenlik ve Kalite Kapısı Düzeltmeleri (P0-3, P1-3)

### P0-3 ✅ — Bütçe ve API Sağlık Kontrollerinin Canlı Gönderim Yoluna Bağlanması
- **sendAiReply Entegrasyonu:** Gerçek otomatik yanıt gönderim patikası olan `SupportReplyService::sendAiReply()` içinde, mesaj dispatche edilmeden hemen önce `isProviderHealthy()` ve `hasExceededBudget()` kontrolleri gerçekleştirilir. Limit aşımında veya servis kesintisinde gönderim fail-closed olarak iptal edilir.
- **Temsilci Yanıt Bypassi:** Temsilci tarafından manuel gönderilen mesajlar (`sendAgentReply()`) bütçe limitlerinden etkilenmez ve her koşulda iletilir.

### P1-3 ✅ — Metrik Doğruluğu ve Cost Ledger Determinizmi
- **Metrik Ayrımı:** Ops Center ekranında "Dispatch Hata Oranı" gerçek `support_dispatches` tablosundan, "AI Run Hata Oranı" ise `support_ai_runs` tablosundan hesaplanacak şekilde ayrıştırılmıştır.
- **Sahte Sıfır Engeli:** Maliyet tahmini hesaplanamayan (null) kayıtlar toplam maliyette sıfır olarak yansıtılmaz; UI'da ayrı bir uyarı mesajı ile operatör bilgilendirilir.
- **Deterministik Cost Ledger:** Maliyet yeniden hesaplama komutu `created_at` timestamp'ini değil, unique `support_ai_run_id` alanını kullanarak updateOrCreate yapar; böylece aynı timestamp'e sahip kayıtların birbirini ezmesi engellenmiştir.
