# ZOLM AI Müşteri İletişim Merkezi — Dalga Y/Z/AA Kabul Kararı

**Tarih:** 2026-07-13  
**İnceleyen:** Codex — Baş mühendis  
**Karar:** ✅ **Dalga Y/Z/AA Kalite Kapısı 01 revizyonu kabul edildi**

Bu karar, `docs/customer-care/dalga-yzaa-kalite-kapisi-01.md` kapsamındaki P0/P1/P2 maddelerinin Antigravity tarafından revize edilmesi sonrası yapılan bağımsız baş mühendis incelemesini kayıt altına alır.

Dalga Y/Z/AA; onboarding wizard, usage metering ve admin/launch reporting katmanları için pilot öncesi kabul seviyesine ulaşmıştır.

> Not: Bu kabul yalnız Y/Z/AA kapsamı içindir. Daha önce açılan `docs/customer-care/dalga-stu-vwx-kalite-kapisi-01.md` içindeki public kanal / fake-success blokajları ayrıca kapanmadan genel pilot onayı verilmez.

---

## Doğrulama Kanıtları

Çalıştırılan komutlar:

```bash
git diff --check
npm run build
./vendor/bin/sail artisan route:list --name=customer-care
./vendor/bin/sail artisan list customer-care --raw
./vendor/bin/sail artisan schedule:list
./vendor/bin/sail artisan test tests/Feature/CustomerCare --no-coverage --compact
./vendor/bin/sail artisan test --no-coverage --compact
```

Sonuçlar:

- `git diff --check`: ✅ temiz
- `npm run build`: ✅ başarılı
- Customer Care testleri: ✅ `210 passed / 806 assertions`
- Full test suite: ✅ `1669 passed / 6867 assertions`
- Route listesi: ✅ `customer-care/onboarding` ve `customer-care/admin` dahil 8 customer-care route görünüyor
- Command listesi: ✅ `customer-care:usage-report` ve `customer-care:pilot-launch-report` dahil customer-care komutları görünüyor
- Scheduler: ✅ `customer-care-pilot-monitor` mevcut

---

## Kabul Edilen Revizyon Maddeleri

### 1. P0-1 — Launch report PII / XML / Markdown koruması kapandı

`app/Console/Commands/CustomerCarePilotLaunchReportCommand.php` içinde:

- `last_error` rapor satırları `PiiRedactor` ile maskeleniyor.
- XML kontrol karakterleri temizleniyor.
- Markdown yapısını bozabilecek pipe ve newline karakterleri normalize ediliyor.
- Hata detayları kısa, güvenli özet olarak rapora yazılıyor.

Test kanıtı:

- `CustomerCareAdminCenterTest::test_pilot_launch_report_command_creates_valid_markdown`

### 2. P1-1 — Admin audit CSV store izolasyonu güçlendirildi

`app/Livewire/CustomerCare/AdminCenter.php` içinde:

- `conversation_id = null` olan global action kayıtları artık seçili store ile deterministik ilişki taşımıyorsa export’a dahil edilmiyor.
- İlişki `details_json->store_id` veya seçili store’a ait `details_json->channel_id` üzerinden kuruluyor.

Test kanıtı:

- `CustomerCareAdminCenterTest::test_audit_csv_export_isolates_global_actions_between_stores`

### 3. P1-2 — Usage metric allowlist ve fail-closed davranışı eklendi

`app/Services/Support/CustomerCareUsageService.php` içinde:

- Metrikler explicit allowlist ile sınırlandı.
- Bilinmeyen/typo metrikler `InvalidArgumentException` ile fail-closed kapanıyor.
- `agent_replies` explicit olarak limitsiz metrik şeklinde tanımlandı.
- `99999` sihirli fallback kaldırıldı.

Test kanıtları:

- `CustomerCareUsageTest::test_invalid_metric_throws_exception`
- `CustomerCareUsageTest::test_agent_replies_writes_event_and_is_unlimited`

### 4. P1-3 — Append-only usage event ledger eklendi

Eklenen dosyalar:

- `database/migrations/2026_07_28_120000_create_support_usage_events_table.php`
- `app/Models/SupportUsageEvent.php`

Davranış:

- Başarılı usage increment için `support_usage_events` kaydı yazılıyor.
- Bloke/başarısız auto reply event yazmıyor.
- Event detayları PII içermeyen kullanım metadatası ile sınırlı.

Test kanıtları:

- `CustomerCareUsageTest::test_successful_quota_usage_writes_append_only_event`
- `CustomerCareUsageTest::test_blocked_auto_reply_does_not_write_event`

### 5. P1-4 — Onboarding automation mode kanal config’iyle senkronize edildi

`app/Livewire/CustomerCare/Onboarding.php` içinde:

- Onboarding tamamlanırken seçilen `recommendedMode`, mağazaya ait support channel kayıtlarının `config_json.automation_settings.ai_mode` alanına yazılıyor.
- `automatic` seçimi hâlâ readiness gate’e bağlı.

Test kanıtı:

- `CustomerCareOnboardingTest::test_readiness_pass_allows_automatic_mode_selection`

### 6. P2-1 — Launch report kapsamı genişletildi

Pilot launch report içine eklendi:

- `Route & Command Inventory`
- `Golden Evaluation Summary`
- Eval score, run date, pass/fail ve stale bilgisi

---

## Kalan Notlar / Sonraki Hardening

1. `CustomerCarePilotLaunchReportCommand` içinde en güvenli sıra, serbest hata metnini önce tamamen sanitize edip sonra 150 karaktere kısaltmaktır. Mevcut davranış pratik testleri geçiyor; fakat gelecekte çok uzun hata mesajlarında PII token boundary riskini azaltmak için sıra değiştirilebilir.
2. `support_usage_events` MVP için yeterli; ileride faturalama itirazı / plan denetimi büyürse event satırına `source_type`, `source_id`, `actor_id` ve idempotency anahtarı eklenmesi değerlendirilmeli.
3. Onboarding otomasyon senkronizasyonu tüm kanallara aynı modu yazıyor. Çok kanallı firmalarda kanal bazlı başlangıç önerisi ihtiyacı doğarsa bu davranış Settings ekranındaki kanal bazlı kontrolle genişletilmeli.

---

## Sonuç

Dalga Y/Z/AA revizyonu kabul edilmiştir. Bu dalga; onboarding, kullanım kotası ve admin launch reporting katmanları için pilot öncesi yeterli güvenlik ve doğruluk seviyesine ulaşmıştır.
