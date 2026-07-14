# ZOLM AI Müşteri İletişim Merkezi — Dalga F Kalite Kapısı 01

Tarih: 2026-07-11  
Karar: **Revizyon gerekli — Dalga F otomatik pilot kabul edilmedi**

## Baş mühendis özeti

Dalga F kapsamında shadow mode, golden eval, pilot dashboard ve PII maskeleme çalışmaları uygulanmış görünüyor. Hedef Customer Care test paketi yeşil ve build başarılıdır.

Ancak otomatik yanıt güvenlik kapısı (`CustomerCareAutomationGate`) şu an gerçek otomatik gönderim yoluna bağlanmamıştır. Bu nedenle `SupportReplyService::sendAiReply()` çağrısı, pilot allowlist ve golden eval kapısından geçmeden dış kanala gönderim başlatabilir. Bu durum, Dalga F'nin “Kontrollü Pilot / Otomasyon Kapısı” hedefi için kabul engelidir.

## Doğrulanan kanıtlar

### Başarılı kontroller

- `git diff --check`: temiz.
- `npm run build`: başarılı.
- Hedef regresyon paketi:

```text
./vendor/bin/sail artisan test tests/Feature/CustomerCare tests/Feature/WhatsApp/SupportChannelTest.php tests/Feature/MarketplaceQuestionsTest.php --no-coverage --compact

Tests: 81 passed (280 assertions)
Duration: 2.65s
```

### Full suite durumu

Full suite yeniden çalıştırıldığında Antigravity raporundaki “1517 passed” sonucu birebir doğrulanamadı:

```text
Tests: 1 failed, 1516 passed (6260 assertions)
```

Fail olan test:

```text
Tests\Feature\MarketplaceProfitSnapshotServiceTest
SQLSTATE[23000]: Duplicate entry 'trendyol-S180349'
```

Bu hata Customer Care modülünden değil, marketplace test verisi üretimindeki random `seller_id` çakışmasından kaynaklanan flaky/full-suite izolasyon problemi gibi görünmektedir. Aynı test tek başına çalıştırıldığında geçmiştir:

```text
./vendor/bin/sail artisan test tests/Feature/MarketplaceProfitSnapshotServiceTest.php --filter=extended_cost_categories --no-coverage --compact

Tests: 1 passed (6 assertions)
```

Bu nedenle Dalga F hedef paketinin yeşil olduğu kabul edilir; fakat “full suite tamamen yeşil” kanıtı bu koşuda doğrulanmış sayılmaz.

## P0 — Kabul engeli

### P0-1: CustomerCareAutomationGate gerçek gönderim yoluna bağlanmamış

Bulgu:

```text
rg -n "CustomerCareAutomationGate|canAutomate" app config routes tests docs/customer-care/dalga-f-kanit-paketi.md
```

Uygulama kodunda yalnızca sınıf tanımı bulunmaktadır:

```text
app/Services/Support/AI/CustomerCareAutomationGate.php
```

Gerçek otomatik gönderim yolu olan:

```text
app/Services/Support/SupportReplyService.php::sendAiReply()
```

şu kontrolleri yapıyor:

- `customer-care.enabled`
- `customer-care.auto_reply_enabled`
- kanal `is_enabled`
- `ownership_status !== human`
- `ai_mode === automatic`
- `status` açık olmalı

Fakat şu Dalga F pilot kapılarını uygulamıyor:

- `pilot_store_allowlist`
- golden dataset eval gate
- confidence gate
- gate kararının audit/log çıktısı

Sonuç: `auto_reply_enabled=true`, `ai_mode=automatic`, `ownership_status=ai` olduğunda mağaza pilot allowlist dışında olsa bile `sendAiReply()` outbox'a AI mesajı alabilir.

### Zorunlu düzeltme

`SupportReplyService::sendAiReply()` içinde `CustomerCareAutomationGate::canAutomate()` çağrılmalı veya bu metodun yalnızca gate'ten geçmiş karar nesnesiyle çağrılabildiği açık ve testli bir akış kurulmalıdır.

Önerilen minimal sözleşme:

- `sendAiReply(SupportConversation $conversation, string $message, ?int $confidenceScore = null)`
- Confidence bilinmiyorsa fail-closed davranmalı veya çağrı yapan orchestration katmanı confidence'ı zorunlu sağlamalı.
- Gate reddederse `SupportMessage` ve `support_dispatches` kaydı oluşmamalı.
- Red nedeni response içinde dönmeli ve gerekiyorsa append-only audit/ledger'a yazılmalı.

### Kabul testi zorunluluğu

Sadece `CustomerCareAutomationGateTest` yeterli değildir. `SupportReplyService` üzerinden gerçek gönderim yolunu kanıtlayan testler eklenmelidir:

1. Store allowlist dışında `sendAiReply()` çağrıldığında:
   - `success=false`
   - `support_messages` oluşmaz
   - `support_dispatches` oluşmaz
2. Golden eval başarısızken `sendAiReply()` çağrıldığında:
   - outbound AI mesajı oluşmaz
   - dispatch oluşmaz
3. Confidence threshold altındayken:
   - outbound AI mesajı oluşmaz
   - dispatch oluşmaz
4. Tüm kapılar geçtiğinde:
   - mevcut başarılı otomatik gönderim davranışı korunur.

## P1 — Pilot öncesi sertleştirme

### P1-1: `pilot_store_allowlist` config/env anahtarı açık tanımlı değil

`CustomerCareAutomationGate` şu key'i kullanıyor:

```php
Config::get('customer-care.pilot_store_allowlist', [])
```

Fakat `config/customer-care.php` ve `.env.example` içinde explicit tanım bulunmuyor. Varsayılan `[]` fail-closed olduğu için güvenli, ancak operasyonel pilot açılışı belirsiz kalır.

Zorunlu düzeltme:

- `CUSTOMER_CARE_PILOT_STORE_ALLOWLIST=` `.env.example` içine eklenmeli.
- `config/customer-care.php` içinde comma-separated env güvenli parse edilmeli.
- Boş değer `[]` kalmalı.

### P1-2: PII maskeleme merkezi servis olmalı

`PilotDashboard::maskPii()` e-posta, telefon ve TCKN için iyi bir başlangıçtır. Ancak pilot dashboard dışında ledger, audit veya export yüzeyleri genişledikçe aynı algoritmanın dağılmaması gerekir.

Öneri:

- `App\Services\Support\Security\PiiRedactor` gibi küçük, testli bir servis oluşturulsun.
- Dashboard bu servisi kullansın.
- İleride ledger/export/rapor yüzeyleri aynı servisle maskelensin.

Bu P1, otomatik pilot kabulünü tek başına engellemez; ancak üretim pilotu öncesi önerilir.

## Antigravity'ye verilecek revizyon talimatı

```text
/Volumes/TWINMOS/zolm reposunda Dalga F Kalite Kapısı 01 revizyonunu uygula.

Önce şu dosyayı tamamen oku:
/Volumes/TWINMOS/zolm/docs/customer-care/dalga-f-kalite-kapisi-01.md

Kapsam:
1. CustomerCareAutomationGate'i gerçek otomatik gönderim yolu olan SupportReplyService::sendAiReply() içine fail-closed şekilde bağla.
2. Allowlist, golden eval ve confidence gate başarısız olduğunda sendAiReply() hiçbir SupportMessage ve support_dispatch kaydı oluşturmamalı.
3. Bu davranışı SupportReplyService üzerinden test eden entegrasyon testleri ekle.
4. pilot_store_allowlist için config/customer-care.php ve .env.example içine explicit, güvenli, boşta fail-closed config ekle.
5. Mümkünse PII masking algoritmasını küçük bir merkezi redaction servisine taşı ve mevcut PilotDashboard testini koru/genişlet.

Yasaklar:
- Commit/push/branch değiştirme.
- Dalga G'ye geçme.
- Otomatik yanıtı varsayılan açık yapma.
- Kapsam dışı marketplace/profit snapshot flaky testini bu revizyonda düzeltme; sadece raporda not et.

Kanıt paketi:
- git diff --check
- npm run build
- php artisan config:clear ardından hedef test paketi
- SupportReplyService üzerinden gate bypass olmadığını gösteren yeni testlerin listesi
- git status --short
- dalga-f-kanit-paketi.md dosyasını gerçek test sayılarıyla güncelle
```

## Karar

Dalga F altyapı parçaları iyi yönde ilerlemiştir; ancak otomatik pilot için ana güvenlik kapısı şu an gerçek outbound yolunda enforce edilmediğinden **Dalga F kabul edilmedi**.

Revizyon sonrası özellikle `sendAiReply()` üzerinden gate bypass edilemediğini bağımsız olarak tekrar kontrol edeceğim.
