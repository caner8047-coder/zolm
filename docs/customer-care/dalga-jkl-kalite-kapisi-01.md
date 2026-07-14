# ZOLM AI Müşteri İletişim Merkezi — Dalga J/K/L Kalite Kapısı 01

Tarih: 2026-07-12  
Karar sahibi: Codex baş mühendis kontrolü  
Kapsam: Dalga J — Kalıcı Eval Ledger, Dalga K — Temsilci Inbox, Dalga L — Canary & Circuit Breaker  
Karar: **Kabul edilmedi — P0/P1 revizyon gerekli**

## Baş mühendis özeti

Dalga J/K/L kapsamı genel yön olarak doğru ilerlemiş; kalıcı eval tabloları, inbox yüzeyi, pilot monitor ve circuit breaker iskeleti oluşmuş. Ancak kalite kapısı kabulü için üç kritik güvenlik/operasyon boşluğu var:

1. Inbox ekranında `selectedConversationId` public state üzerinden tenant dışı konuşmanın mesaj geçmişi render edilebilir.
2. Circuit breaker rate limit semantiği ters uygulanmış: `CUSTOMER_CARE_AUTO_REPLY_MAX_PER_HOUR=0` fail-closed olması gerekirken mevcut kodda limitsiz gibi davranıyor.
3. Eval ledger/cache yazımında PII redaksiyonu tutarsız: `runGoldenDatasetEval()` case result yazarken maskeliyor, fakat `saveGoldenEval()` fallback DB yazımında ve cache payload'ında raw response kalabiliyor.

Ek olarak `git diff --check` temiz değildir.

Bu nedenle Dalga J/K/L şu haliyle **canlı pilot güvenliği için kabul edilmez**.

---

## P0-1 — Inbox `selectedConversationId` render IDOR açığı

### Bulgular

Dosya:

```text
app/Livewire/CustomerCare/Inbox.php
```

`selectConversation()` içinde tenant kontrolü var; fakat Livewire public property olan `selectedConversationId`, client payload ile doğrudan değiştirilebilir. `render()` metodu ise seçili konuşmayı şu şekilde doğrudan okuyor:

```php
$selectedConversation = SupportConversation::find($this->selectedConversationId);
```

Ardından mesajlar tenant scope uygulanmadan yükleniyor:

```php
$messages = $selectedConversation->messages()
    ->where('delivery_status', '!=', 'draft')
    ->orderBy('created_at', 'asc')
    ->get();
```

Bu, başka mağazaya ait konuşma ID'si biliniyorsa mesaj geçmişinin render edilmesine yol açabilir.

### Ek risk

`generateAiDraft()` da `SupportConversation::find($this->selectedConversationId)` ile başlıyor. `SupportReplyService::generateAiDraft()` içinde ayrıca `TenantContext::enforceConversationAccess()` yok. Bu yol, en azından tenant dışı konuşma için `support_ai_runs` üzerinde failed ledger yazımı gibi cross-tenant yan etki üretebilir.

### Zorunlu düzeltme

Inbox component içinde tek bir güvenli resolver kullanılmalı:

```php
private function selectedConversationForCurrentUser(): ?SupportConversation
```

Bu resolver:

- Konuşmayı yalnız kullanıcının erişebildiği store listesi içinde aramalı veya `TenantContext::enforceConversationAccess()` uygulamalı.
- Yetkisiz durumda `selectedConversationId = null` yapmalı.
- `render()`, `claimConversation()`, `releaseConversation()`, `resolveConversation()`, `reopenConversation()`, `changeAiMode()`, `generateAiDraft()` ve `sendReply()` aynı resolver'ı kullanmalı.

Ayrıca `SupportReplyService::generateAiDraft()` veya orchestrator girişinde de actor/tenant enforce olmalı. UI tek koruma katmanı kabul edilmemeli.

### Zorunlu testler

`CustomerCareInboxTest` içine ekle:

- Başka kullanıcı `selectedConversationId` public state'ini doğrudan başka mağazanın konuşmasına set ettiğinde render içinde müşteri mesajı görünmemeli.
- Başka kullanıcı `generateAiDraft()` çağırdığında taslak oluşmamalı, `support_messages` ve `support_ai_runs` içinde başka store/conversation için kayıt oluşmamalı.
- Başka kullanıcı public state manipülasyonu ile `sendReply()` denediğinde outbound message/dispatch/audit oluşmamalı.

---

## P0-2 — `AUTO_REPLY_MAX_PER_HOUR=0` fail-closed değil

### Bulgular

Dosya:

```text
app/Services/Support/CustomerCarePilotMonitorService.php
```

Config:

```php
'auto_reply_max_per_hour' => (int) env('CUSTOMER_CARE_AUTO_REPLY_MAX_PER_HOUR', 0),
```

Bu değer için tasarım kararı:

```text
0 = fail-closed
```

Mevcut kod:

```php
} elseif ($maxPerHour > 0 && $autoReplyCount1h >= $maxPerHour) {
```

Bu, `0` değerinde rate limit kontrolünü tamamen devre dışı bırakıyor. Güvenli varsayılan tersine dönmüş oluyor.

### Zorunlu düzeltme

`CustomerCareAutomationGate` otomatik yanıtı göndermeden önce saatlik limitin pozitif yapılandırıldığını kesin şart olarak kontrol etmeli.

Beklenen davranış:

- `auto_reply_max_per_hour <= 0` ise automatic reply **fail-closed** dönmeli.
- Hiçbir `support_messages` veya `support_dispatches` kaydı oluşmamalı.
- Manual reply bu limitten etkilenmemeli.
- Limit pozitifse, son 1 saatteki AI outbound sayısı limite ulaşınca automatic reply engellenmeli.

Bu kontrol yalnız dashboard metriği değil, gerçek `SupportReplyService::sendAiReply()` yolunun içinde çalışan gate tarafından enforce edilmelidir.

### Zorunlu testler

`CanaryCircuitBreakerTest` içine ekle:

- `CUSTOMER_CARE_AUTO_REPLY_MAX_PER_HOUR` default/0 iken, diğer tüm kapılar geçse bile `sendAiReply()` başarısız olur ve mesaj/dispatch oluşturmaz.
- Limit `2`, mevcut AI outbound sayısı `0` veya `1` iken otomatik yanıt geçebilir.
- Limit `2`, mevcut AI outbound sayısı `2` iken otomatik yanıt engellenir.
- Manual reply limit 0 iken çalışmaya devam eder.

---

## P0-3 — Eval ledger/cache PII redaksiyonu tutarsız

### Bulgular

Dosya:

```text
app/Services/Support/AI/CustomerCareEvalService.php
```

`runGoldenDatasetEval()` içinde case result yazılırken response `PiiRedactor` ile maskeleniyor. Bu doğru.

Fakat `saveGoldenEval()` metodu:

1. Raw `$result` payload'ını cache'e yazıyor.
2. DB'de run yoksa fallback olarak `support_ai_eval_runs` ve `support_ai_eval_case_results` oluşturuyor.
3. Bu fallback case result içinde `response_preview` raw `detail['response']` üzerinden yazılıyor:

```php
'response_preview' => mb_substr($detail['response'] ?? '', 0, 500),
```

Bu, aynı serviste iki farklı veri güvenliği davranışı yaratıyor.

### Zorunlu düzeltme

Önerilen net ayrım:

- `runGoldenDatasetEval()` kalıcı DB ledger yazan tek yol olsun.
- `saveGoldenEval()` DB yazımı yapmasın; gerekiyorsa yalnız sanitized/cache-safe özet tutsun.
- Cache gerekiyorsa raw response değil, PII maskelenmiş summary/details cache'lensin.
- Test seeding için `saveGoldenEval()` yerine model factory/helper kullanılsın veya ayrı `recordManualEvalResult()` gibi açık isimli, PII güvenli metot yazılsın.

### Zorunlu testler

`SupportAiEvalLedgerTest` içine ekle:

- `saveGoldenEval()` raw PII içeren details ile çağrılsa bile DB case result içinde e-posta/telefon/T.C. Kimlik yazılmaz veya `saveGoldenEval()` hiç DB yan etkisi üretmez.
- Cache payload'ında raw PII bulunmaz.
- `PilotDashboard::runGoldenEval()` tek bir eval run üretir; duplicate run/case result oluşturmaz.

---

## P1-1 — `git diff --check` temiz değil

Komut:

```text
git diff --check
```

Sonuç:

```text
app/Services/Support/TrendyolSupportChannelAdapter.php:70: trailing whitespace.
```

Bu düzeltilmeden kalite kapısı kanıtı geçerli sayılamaz.

---

## P1-2 — Test ortamı production davranışını maskeleyen eval fallback içeriyor

### Bulgular

Dosya:

```text
app/Services/Support/AI/CustomerCareAutomationGate.php
```

Mevcut kod:

```php
if (app()->environment('testing') && !$evalResult) {
    $evalResult = $this->evalService->runGoldenDatasetEval($conversation->store_id, $this->aiProvider);
}
```

Production davranışı “eval yoksa fail-closed” iken test ortamında “eval yoksa yeni eval çalıştır” davranışı var. Bu, testlerin gerçek production kapısını maskelemesine yol açabilir.

### Zorunlu düzeltme

- Automatic gate, ortamdan bağımsız olarak son kalıcı eval sonucunu okumalıdır.
- Eval yoksa test ortamında da fail-closed olmalıdır.
- Testler başarılı eval gerekiyorsa DB'ye açık şekilde eval run seed etmelidir.

### Zorunlu testler

- Testing ortamında eval yokken `canAutomate()` ve `sendAiReply()` fail-closed dönmeli.
- Test provider mock'u olsa bile gate kendiliğinden eval koşturmamalı.

---

## Doğrulama notu

Bu kalite kapısında hedef test paketi çalıştırılmadan önce `git diff --check` başarısız olduğu için komut zinciri durdu:

```text
app/Services/Support/TrendyolSupportChannelAdapter.php:70: trailing whitespace.
```

Antigravity raporundaki “git diff --check temiz” ifadesi mevcut çalışma ağacıyla uyuşmuyor.

---

## Revizyon sonrası zorunlu kanıt paketi

Antigravity şu adımları uygulayıp durmalıdır:

```text
git diff --check
npm run build
./vendor/bin/sail artisan route:list --name=customer-care
./vendor/bin/sail artisan schedule:list
./vendor/bin/sail artisan test tests/Feature/CustomerCare tests/Feature/WhatsApp/SupportChannelTest.php tests/Feature/MarketplaceQuestionsTest.php --no-coverage --compact
./vendor/bin/sail artisan test --no-coverage --compact
```

Rapor şunları içermelidir:

- Değişen dosyalar
- Eklenen/düzeltilen test isimleri
- `git diff --check` kanıtı
- Hedef test sonucu
- Full suite sonucu
- Build sonucu
- Route/scheduler kanıtı
- P0/P1 maddelerinin dosya-test eşleşmesi

## Son karar

Dalga J/K/L mevcut haliyle **kabul edilmedi**.

Revizyon sonrası tekrar baş mühendis incelemesine sunulmalıdır.
