# ZOLM AI Müşteri İletişim Merkezi — Dalga M/N/O Kabul Kararı

**Tarih:** 2026-07-12  
**İnceleyen:** Codex — Baş mühendis kontrolü  
**Önceki kalite kapısı:** `docs/customer-care/dalga-mno-kalite-kapisi-01.md`  
**Karar:** ✅ **Dalga M/N/O Kalite Kapısı 01 revizyonu kabul edildi**

Bu kabul kararı, Dalga M/N/O için daha önce reddedilen kalite kapısı maddelerinin revizyon sonrası bağımsız doğrulamasıdır.

---

## 1. Kabul Özeti

Antigravity tarafından uygulanan revizyonlar kod üzerinde doğrulandı. Önceki kalite kapısındaki P0/P1/P2 maddeleri kabul edilebilir şekilde kapatılmıştır:

1. Bilgi önerisi edit ve approve akışında PII ham veri sızıntısı kapatıldı.
2. `CustomerCareSuggestionService::createSuggestionFromMessage()` store / conversation / message bütünlük guard’ı ile fail-closed hale getirildi.
3. `SupportOutboxService::sendDispatch()` AI gate bloklarında mesajı stale `sending` durumunda bırakmıyor.
4. Outbox AI gate kontrolü, varsa `SupportAiRun.confidence_score` bağlamını kullanıyor.
5. Analytics servisinde veri yokken sahte topic başarı oranı üretimi kaldırıldı.
6. Analytics UI boş topic metriği için açık empty state gösteriyor.
7. `MarketplaceQuestionsTest` suffix üretimi UUID/random string ile flaky unique çakışması üretmeyecek hale getirildi.
8. CSV export testi gerçek `StreamedResponse` içeriğini buffer ile doğrulayacak şekilde güçlendirildi.

---

## 2. Bağımsız Doğrulamalar

### 2.1. Whitespace / format kontrolü

```bash
git diff --check
```

**Sonuç:** ✅ Temiz çıktı.

### 2.2. Frontend build

```bash
npm run build
```

**Sonuç:** ✅ Başarılı.

```text
public/build/manifest.json              0.33 kB │ gzip:  0.17 kB
public/build/assets/app-BLasCvHg.css  165.12 kB │ gzip: 26.21 kB
public/build/assets/app-BjqOcoUn.js    37.21 kB │ gzip: 14.89 kB
✓ built in 2.09s
```

### 2.3. Hedef test paketi

```bash
./vendor/bin/sail artisan test tests/Feature/CustomerCare tests/Feature/WhatsApp/SupportChannelTest.php tests/Feature/MarketplaceQuestionsTest.php --no-coverage --compact
```

**Sonuç:** ✅ Başarılı.

```text
Tests: 146 passed (599 assertions)
Duration: 4.92s
```

### 2.4. Full test suite

```bash
./vendor/bin/sail artisan test --no-coverage --compact
```

**Sonuç:** ✅ Başarılı.

```text
Tests: 1582 passed (6585 assertions)
Duration: 90.21s
```

Not: Full suite içinde mevcut PHPUnit doc-comment metadata warning’leri görünüyor; bunlar bu modülün revizyonundan kaynaklanan failure değildir.

### 2.5. Route doğrulaması

```bash
./vendor/bin/sail artisan route:list --name=customer-care
```

**Sonuç:** ✅ 5 route kayıtlı.

```text
customer-care
customer-care/analytics
customer-care/inbox
customer-care/pilot
customer-care/suggestions
```

### 2.6. Komut / scheduler doğrulaması

```bash
./vendor/bin/sail artisan list customer-care --raw
```

Kayıtlı komutlar:

```text
customer-care:circuit-breaker
customer-care:generate-knowledge-suggestions
customer-care:pilot-monitor
customer-care:pilot-readiness
customer-care:run-golden-eval
```

```bash
./vendor/bin/sail artisan schedule:list | rg -n "customer-care|support-process-outbox"
```

İlgili scheduler kayıtları mevcut:

```text
support-process-outbox
customer-care-pilot-monitor
```

---

## 3. Kod Kanıtları

### 3.1. PII edit / approve sızıntısı kapandı

`app/Livewire/CustomerCare/KnowledgeSuggestions.php` içinde `saveEdit()` artık `PiiRedactor::maskPii()` kullanıyor.

`app/Services/Support/KnowledgeBaseService.php` içinde `createArticle()` ikinci güvenlik sınırı olarak title/content üzerinde PII redaction uyguluyor.

Kapsayan testler:

- `test_editing_suggestion_redacts_pii_before_save`
- `test_approved_edited_suggestion_does_not_write_raw_pii_to_knowledge_base`

### 3.2. Öneri servis bütünlüğü kapandı

`app/Services/Support/CustomerCareSuggestionService.php` içinde:

- conversation store eşleşmesi,
- message conversation eşleşmesi

kontrol ediliyor. Uyuşmazlıkta `AuthorizationException` ile fail-closed davranıyor.

Kapsayan testler:

- `test_create_suggestion_rejects_conversation_from_another_store`
- `test_create_suggestion_rejects_message_from_another_conversation`

### 3.3. Outbox AI gate stale status sorunu kapandı

`app/Services/Support/SupportOutboxService.php` içinde AI gate bloklarında:

- dispatch `failed` durumuna alınıyor,
- message `delivery_status` değeri `failed` olarak güncelleniyor,
- varsa `SupportAiRun.confidence_score` gate’e aktarılıyor.

Kapsayan testler:

- `test_outbox_gate_block_marks_ai_message_failed_or_cancelled`
- `test_outbox_gate_uses_original_ai_confidence_or_decision_context`

### 3.4. Analytics uydurma metrik üretmiyor

`app/Services/Support/CustomerCareAnalyticsService.php` içinde sahte default topic metrikleri kaldırıldı. Topic verisi yoksa `topics` boş kalıyor.

`resources/views/livewire/customer-care/analytics.blade.php` içinde boş durumda şu metin gösteriliyor:

```text
Henüz yeterli AI çalışma verisi yok
```

Kapsayan test:

- `test_analytics_does_not_fabricate_topic_metrics_when_no_ai_runs`

### 3.5. MarketplaceQuestions flaky unique çakışması kapandı

`tests/Feature/MarketplaceQuestionsTest.php` içinde `createStoreGraph()` artık UUID/random string tabanlı benzersiz değerler üretiyor.

Hedef paket ve full suite tekrar yeşil geçti.

---

## 4. P2 Sertleştirme Notu

Kalite kapısını kapatmaya engel değil, ancak ileride daha temiz bir production-hardening adımı önerilir:

`SupportOutboxService::sendDispatch()` AI mesajı için `SupportAiRun` bulamazsa şu an confidence fallback olarak `100` kullanıyor. Normal ürün yolunda `SupportReplyService::sendAiReply()` mesaj oluşturmadan önce gerçek confidence ile pre-gate yaptığı için mevcut kabulü engellemiyorum.

Yine de daha sert tasarım için ileride:

- AI dispatch içine immutable `automation_decision_id` veya `confidence_score` snapshot eklenebilir.
- AI run bulunamayan outbound AI dispatch için fail-closed davranış değerlendirilebilir.

Bu madde Dalga M/N/O kabulünü bloke etmez; production sertleştirme backlog’una alınabilir.

---

## 5. Sonuç

Dalga M/N/O Kalite Kapısı 01 revizyonu kabul edilmiştir.

**Durum:** ✅ Dalga M/N/O kabul edildi.  
**Sonraki adım:** Dalga P/Q/R planlamasına geçilebilir; ancak canlı pilot açılışından önce tüm feature flag’lerin hâlâ kapalı varsayılanlarda olduğu ve pilot allowlist’in bilinçli açıldığı tekrar doğrulanmalıdır.
