# ZOLM AI Müşteri İletişim Merkezi — Dalga J/K/L Kabul Kararı

**Tarih:** 2026-07-12  
**İnceleyen:** Codex — Baş mühendis kontrolü  
**Önceki kalite kapısı:** `docs/customer-care/dalga-jkl-kalite-kapisi-01.md`  
**Karar:** ✅ **Dalga J/K/L Kalite Kapısı 01 revizyonu kabul edildi**

Bu kabul kararı yalnız Dalga J/K/L revizyon kapsamı içindir. Sonradan eklenen Dalga M/N/O bulguları bu karardan bağımsızdır.

---

## 1. Kabul Özeti

Antigravity tarafından bildirilen Dalga J/K/L Kalite Kapısı 01 revizyonu kod üzerinde doğrulandı. Önceki kalite kapısındaki P0/P1 maddeleri uygulanmış görünüyor:

1. Inbox public state IDOR koruması güçlendirildi.
2. `CUSTOMER_CARE_AUTO_REPLY_MAX_PER_HOUR=0` otomatik yanıtlar için fail-closed hale getirildi.
3. Golden eval ledger akışında cache/DB ayrımı netleşti; test seed için `recordManualEvalResult()` ayrıldı.
4. `saveGoldenEval()` cache optimizasyonu olarak kaldı ve response detaylarını PII redaction’dan geçiriyor.
5. Test ortamında production davranışını maskeleyen dinamik eval fallback kaldırıldı; eval gate DB ledger sonucuna bağlı çalışıyor.
6. `git diff --check` temiz.

---

## 2. Bağımsız Doğrulamalar

### 2.1. Whitespace / format kontrolü

```bash
git diff --check
```

**Sonuç:** ✅ Temiz çıktı.

### 2.2. Hedef J/K/L regresyon testleri

```bash
./vendor/bin/sail artisan test \
  tests/Feature/CustomerCare/CustomerCareInboxTest.php \
  tests/Feature/CustomerCare/CanaryCircuitBreakerTest.php \
  tests/Feature/CustomerCare/SupportAiEvalLedgerTest.php \
  --no-coverage --compact
```

**Sonuç:** ✅ Başarılı.

```text
Tests: 24 passed (95 assertions)
```

### 2.3. Auto reply zero-limit doğrulaması

```bash
./vendor/bin/sail artisan test \
  tests/Feature/CustomerCare/CustomerCarePilotGateTest.php \
  --filter='auto_reply_max_per_hour' \
  --no-coverage --compact
```

**Sonuç:** ✅ Başarılı.

```text
Tests: 1 passed (3 assertions)
```

---

## 3. Kod Kanıtları

### 3.1. Inbox IDOR koruması

`app/Livewire/CustomerCare/Inbox.php` içinde `selectedConversationForCurrentUser()` resolver’ı var ve seçili konuşma her render/aksiyon öncesi `TenantContext::enforceConversationAccess()` ile doğrulanıyor.

Kapsanan aksiyonlar:

- `claimConversation()`
- `releaseConversation()`
- `resolveConversation()`
- `reopenConversation()`
- `changeAiMode()`
- `generateAiDraft()`
- `sendReply()`
- `render()`

### 3.2. Servis katmanı tenant koruması

`app/Services/Support/SupportReplyService.php` içinde `generateAiDraft()` artık auth actor varsa konuşma erişimini servis seviyesinde de doğruluyor.

### 3.3. Rate limit fail-closed davranışı

`app/Services/Support/AI/CustomerCareAutomationGate.php` içinde:

- `auto_reply_max_per_hour <= 0` otomatik yanıtı engelliyor.
- Manuel yanıtlar bu limitten etkilenmiyor.
- Testler `CanaryCircuitBreakerTest` ve `CustomerCarePilotGateTest` içinde bu davranışı kapsıyor.

### 3.4. Eval ledger / PII redaction

`app/Services/Support/AI/CustomerCareEvalService.php` içinde:

- `saveGoldenEval()` artık DB yazımı yapmıyor; cache’e sanitized veri yazıyor.
- `recordManualEvalResult()` test/eval seed için ayrı DB yazım metodu olarak kullanılıyor.
- `getLatestGoldenEval()` DB ledger’dan okuyor.
- Eval response preview’leri PII redaction’dan geçiyor.

---

## 4. Notlar ve Sınırlar

Bu kabul kararı Dalga J/K/L için geçerlidir.

Şu dosya ayrı bir kalite kapısı olarak açık kalır:

- `docs/customer-care/dalga-mno-kalite-kapisi-01.md`

Dalga M/N/O içinde tespit edilen bilgi önerisi PII edit sızıntısı, öneri servis bütünlük guard’ı, outbox AI gate context sorunu ve analytics sahte metrik problemi J/K/L kabulünü geri almaz; ancak M/N/O pilot kabulünü engeller.

---

## 5. Sonuç

Dalga J/K/L Kalite Kapısı 01 revizyonu kabul edilmiştir.

**Durum:** ✅ J/K/L kabul edildi.  
**Sonraki adım:** Dalga M/N/O Kalite Kapısı 01 revizyonu tamamlanmalı ve yeniden incelenmelidir.
