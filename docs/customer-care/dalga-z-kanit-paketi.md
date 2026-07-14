# ZOLM AI Müşteri İletişim Merkezi — Dalga Z Kanıt Paketi (Revizyon 01)

**Tarih:** 2026-07-13  
**Uygulanan Modül:** Plan, Limit, Kota ve Usage Metering  
**Durum:** 🚀 TAMAMLANDI (Revize Edildi, Test Edildi ve Doğrulandı)

## 1. Eklenen & Değiştirilen Dosyalar
- [CustomerCareUsageService (Service)](file:///Volumes/TWINMOS/zolm/app/Services/Support/CustomerCareUsageService.php)
- [CustomerCareUsageReportCommand (Artisan Command)](file:///Volumes/TWINMOS/zolm/app/Console/Commands/CustomerCareUsageReportCommand.php)
- [support_usage_events (Migration)](file:///Volumes/TWINMOS/zolm/database/migrations/2026_07_28_120000_create_support_usage_events_table.php)
- [SupportUsageEvent (Model)](file:///Volumes/TWINMOS/zolm/app/Models/SupportUsageEvent.php)
- [SupportUsage (Model)](file:///Volumes/TWINMOS/zolm/app/Models/SupportUsage.php)
- [support_usages (Migration)](file:///Volumes/TWINMOS/zolm/database/migrations/2026_07_28_110000_create_support_usages_table.php)
- [SupportReplyService (AI Draft Limit)](file:///Volumes/TWINMOS/zolm/app/Services/Support/SupportReplyService.php)
- [SupportOutboxService (Auto Reply Limit)](file:///Volumes/TWINMOS/zolm/app/Services/Support/SupportOutboxService.php)
- [CustomerCareSuggestionService (Suggestions Limit)](file:///Volumes/TWINMOS/zolm/app/Services/Support/CustomerCareSuggestionService.php)
- [GenerateKnowledgeSuggestionsCommand (Suggestions Command Integration)](file:///Volumes/TWINMOS/zolm/app/Console/Commands/GenerateKnowledgeSuggestionsCommand.php)

## 2. Revizyon Detayları (P1-2 & P1-3 Düzeltmesi)
- **Metrik İzin Listesi (Allowlist):** Kota sisteminde yalnızca izin verilen metrikler (`ai_drafts`, `auto_replies`, `agent_replies`, `knowledge_suggestions`, `connected_channels`) sorgulanabilir. Bilinmeyen/typo içeren metriklerde kota sistemi bypass edilemez, fail-closed `InvalidArgumentException` fırlatılır.
- **Append-Only Event Ledger:** Kota kullanım artışları `support_usage_events` tablosunda kalıcı olay günlükleri olarak saklanır. Her başarılı kota harcamasında event logu atılır, engellenen veya başarısız gönderimlerde olay kaydedilmez.
- **Temsilci Yanıtları:** `agent_replies` metriği limitsiz (`PHP_INT_MAX`) olarak tanımlanmıştır. Raporda "Sınırsız" olarak gösterilir ve kotalardan etkilenmeden çalışmaya devam eder.

## 3. Test Sonuçları (Feature Tests)
- `tests/Feature/CustomerCare/CustomerCareUsageTest.php` altındaki testler:
  - `test_usage_increments_on_ai_draft_success` (PASS)
  - `test_usage_increments_on_auto_reply_success_only` (PASS - Sadece başarılı gönderimler sayılır)
  - `test_blocked_auto_reply_does_not_increment_usage` (PASS - Reddedilen / Başarısızlar sayılmaz)
  - `test_ai_draft_blocked_when_quota_limit_reached` (PASS - Limit engelleyici mekanizma)
  - `test_manual_reply_not_blocked_by_auto_reply_quota` (PASS - Temsilci yanıtı kota kısıtlamasına takılmaz)
  - `test_cross_store_usage_isolation` (PASS - Mağazalar arası veri/kota izolasyonu)
  - `test_invalid_metric_throws_exception` (PASS - Typo veya bilinmeyen metrik bypass koruması)
  - `test_successful_quota_usage_writes_append_only_event` (PASS - Olay kaydı denetimi)
  - `test_blocked_auto_reply_does_not_write_event` (PASS - Başarısız durumlarda event yazılmama garantisi)
  - `test_agent_replies_writes_event_and_is_unlimited` (PASS - Temsilci log kaydı ve sınırsız kota)
  - `test_usage_report_command_prints_table` (PASS - Artisan rapor çıktısı)

## 4. Artisan Komutu ve Çıktısı
```bash
./vendor/bin/sail artisan customer-care:usage-report --store=1
```
Çıktı:
```text
=== Müşteri İletişim Merkezi Kullanım Raporu ===
Mağaza: Trendyol Store (ID: 1)
Dönem: 2026-07
------------------------------------------------
Ai drafts             : 1 / 500 (%0.2) - Yeterli Kota
Auto replies          : 0 / 200 (%0) - Yeterli Kota
Agent replies         : 0 / Sınırsız (-) - Yeterli Kota
Knowledge suggestions : 0 / 20 (%0) - Yeterli Kota
Connected channels    : 1 / 5 (%20) - Yeterli Kota
```
