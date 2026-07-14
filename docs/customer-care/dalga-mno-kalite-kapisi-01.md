# ZOLM AI Müşteri İletişim Merkezi — Dalga M/N/O Kalite Kapısı 01

**Tarih:** 2026-07-12  
**İnceleyen:** Codex — Baş mühendis kontrolü  
**Kapsam:** Dalga J/K/L revizyon kontrolü + Dalga M, N, O teslim incelemesi  
**Karar:** ❌ **Kabul edilmedi — P0/P1 düzeltme gerekli**

Bu kalite kapısı, Antigravity tarafından bildirilen Dalga M/N/O teslim raporunun kod, test, build ve güvenlik davranışı açısından bağımsız kontrolüdür.

---

## 1. Kısa Karar

Dalga J/K/L kalite kapısında daha önce işaretlenen ana güvenlik düzeltmelerinin önemli bölümü kodda uygulanmış görünüyor:

- Inbox public state IDOR koruması güçlendirilmiş.
- `CUSTOMER_CARE_AUTO_REPLY_MAX_PER_HOUR=0` artık fail-closed çalışıyor.
- Eval gate artık DB ledger sonucuna bağlı çalışıyor.
- `git diff --check` temiz.

Ancak Dalga M/N/O için canlı pilot öncesi kabulü engelleyen yeni P0/P1 bulgular var:

1. Bilgi önerisi düzenleme ekranı PII veriyi tekrar maskelemiyor; onaylanırsa bilgi merkezine ham kişisel veri yazılabilir.
2. `CustomerCareSuggestionService::createSuggestionFromMessage()` store / conversation / message bütünlüğünü doğrulamadan öneri kaydedebiliyor.
3. AI outbox ikinci otomasyon gate kontrolünde confidence bağlamını kaybediyor ve gate bloklandığında mesajı `sending` durumunda bırakabiliyor.
4. Analytics servisinde veri yokken sahte konu başarı oranları üretiliyor.
5. Hedef test komutu tek koşuda yeşil değil; `MarketplaceQuestionsTest` içinde Antigravity diff’iyle gelen suffix üretimi flaky/unique çakışması oluşturdu.

Bu maddeler düzelmeden Dalga M/N/O kabul edilmemelidir.

---

## 2. Çalıştırılan Doğrulamalar

### 2.1. Git whitespace kontrolü

```bash
git diff --check
```

**Sonuç:** ✅ Temiz çıktı.

### 2.2. Frontend build

```bash
npm run build
```

**Sonuç:** ✅ Başarılı.

Özet:

```text
vite v7.3.1 building client environment for production...
✓ 53 modules transformed.
public/build/manifest.json              0.33 kB │ gzip:  0.17 kB
public/build/assets/app-BLasCvHg.css  165.12 kB │ gzip: 26.21 kB
public/build/assets/app-BjqOcoUn.js    37.21 kB │ gzip: 14.89 kB
✓ built in 2.15s
```

### 2.3. Hedef test paketi

```bash
./vendor/bin/sail artisan test tests/Feature/CustomerCare tests/Feature/WhatsApp/SupportChannelTest.php tests/Feature/MarketplaceQuestionsTest.php --no-coverage --compact
```

**Sonuç:** ❌ Başarısız.

Özet:

```text
Tests: 1 failed, 138 passed (575 assertions)
```

Başarısız test:

```text
Tests\Feature\MarketplaceQuestionsTest
⨯ woocommerce connector imports reviews and replies with wordpress comment

SQLSTATE[23000]: Integrity constraint violation:
Duplicate entry 'questions-117699553@example.test' for key 'users.users_email_unique'
```

Tekil filtreyle aynı test bazen geçiyor:

```bash
./vendor/bin/sail artisan test tests/Feature/MarketplaceQuestionsTest.php --filter=test_woocommerce_connector_imports_reviews_and_replies_with_wordpress_comment --no-coverage --compact
```

**Sonuç:** ✅ 1 passed.

Bu, hatanın işlevsel WooCommerce davranışından çok test veri üretimindeki flaky/unique collision riskine işaret ediyor. Fakat kalite kanıt paketi açısından “tam yeşil” iddiasını düşürür.

---

## 3. P0 / P1 Bulgular

### P0-1 — Bilgi önerisi edit akışında PII ham veri olarak bilgi merkezine sızabilir

**Dosya:** `app/Livewire/CustomerCare/KnowledgeSuggestions.php`  
**Satırlar:** 90-124, 145-152

`CustomerCareSuggestionService` ilk AI önerisi üretiminde `PiiRedactor` kullanıyor. Ancak operatör öneriyi düzenlediğinde `saveEdit()` yalnızca `strip_tags()` yapıyor:

```php
$suggestion->update([
    'title' => trim(strip_tags($this->editTitle)),
    'proposed_answer' => trim(strip_tags($this->editProposedAnswer)),
    'category' => trim(strip_tags($this->editCategory ?: 'general')),
]);
```

Sonra `approve()` bu alanları doğrudan `KnowledgeBaseService::createArticle()` içine gönderiyor. `KnowledgeBaseService` de PII redaksiyonu yapmıyor; yalnızca HTML temizliği ve prompt injection kontrolü yapıyor.

**Risk:** Operatör edit sırasında telefon, e-posta veya T.C. Kimlik numarası girerse, bilgi bankasına ham kişisel veri yayınlanabilir. Bu, Dalga N’in “KVKK & PII maskeleme” kabul şartını bozar.

**Zorunlu düzeltme:**

- `KnowledgeSuggestions::saveEdit()` içinde `PiiRedactor::maskPii()` title ve proposed_answer için uygulanmalı.
- `approve()` veya `KnowledgeBaseService::createArticle()` seviyesinde de ikinci güvenlik katmanı olarak PII redaksiyonu yapılmalı.
- Test eklenmeli:
  - `test_editing_suggestion_redacts_pii_before_save`
  - `test_approved_edited_suggestion_does_not_write_raw_pii_to_knowledge_base`

---

### P0-2 — Öneri servisi store / conversation / message bütünlüğünü doğrulamıyor

**Dosya:** `app/Services/Support/CustomerCareSuggestionService.php`  
**Satırlar:** 79-160

`createSuggestionFromMessage(int $storeId, SupportConversation $conversation, SupportMessage $message, ...)` dışarıdan aldığı üç ayrı kaydın birbirine ait olduğunu doğrulamıyor:

- `$conversation->store_id === $storeId`
- `$message->conversation_id === $conversation->id`
- `$message` gerçekten aynı tenant / store kapsamında mı?

Sonuçta yanlış veya saldırgan bir çağrı ile bir mağazanın `store_id` altında başka conversation/message içeriğinden öneri kaydı üretilebilir.

**Risk:** Bilgi önerisi kuyruğunda cross-tenant veri karışması veya yanlış kaynak attribution oluşabilir. Bu modülün “öğrenen bilgi merkezi” kısmında tenant izolasyonu en kritik çizgilerden biridir.

**Zorunlu düzeltme:**

- Metod başında bütünlük guard eklenmeli.
- Uyuşmazlıkta fail-closed davranılmalı: tercihen `AuthorizationException` veya `null` + audit log.
- Test eklenmeli:
  - `test_create_suggestion_rejects_conversation_from_another_store`
  - `test_create_suggestion_rejects_message_from_another_conversation`

---

### P1-1 — Outbox AI gate ikinci kontrolde confidence bağlamını kaybediyor ve mesajı `sending` bırakabiliyor

**Dosya:** `app/Services/Support/SupportOutboxService.php`  
**Satırlar:** 158-165

`SupportReplyService::sendAiReply()` ilk otomasyon kapısını gerçek `$confidenceScore` ile çağırıyor. Ancak outbox `sendDispatch()` içinde AI mesajları için tekrar gate çalıştırılıyor:

```php
$gateResult = $gate->canAutomate($conversation);
```

Bu çağrı confidence parametresi vermediği için varsayılan `85` ile çalışıyor. Böylece outbox katmanı gerçek AI karar bağlamını kaybediyor.

Ayrıca gate bloklandığında yalnız dispatch `failed` oluyor:

```php
$dispatch->update(['status' => 'failed', ...]);
return false;
```

İlgili `support_messages.delivery_status` güncellenmiyor; mesaj `sending` durumunda kalabilir.

**Risk:** Pilot/canary karar izlenebilirliği bozulur; “neden gönderilmedi?” sorusunun ledger karşılığı eksik kalır. Ayrıca UI’da gönderiliyor gibi görünen ama gate tarafından bloklanan mesajlar oluşabilir.

**Zorunlu düzeltme:**

- Outbox gate kontrolü gerçek confidence / AI run bağlamını kullanmalı veya pre-gate yapılmış AI dispatch için deterministik bir karar token’ı taşımalı.
- Gate bloklandığında `support_messages.delivery_status` `failed` veya `cancelled` olarak güncellenmeli.
- Test eklenmeli:
  - `test_outbox_gate_block_marks_ai_message_failed_or_cancelled`
  - `test_outbox_gate_uses_original_ai_confidence_or_decision_context`

---

### P1-2 — Analytics servisinde veri yokken sahte konu başarı oranları üretiliyor

**Dosya:** `app/Services/Support/CustomerCareAnalyticsService.php`  
**Satırlar:** 103-110

`SupportAiRun` verisi yoksa servis şu varsayılan metrikleri üretiyor:

```php
'shipping_delay' => ['name' => 'Kargo & Teslimat', 'success_rate' => 92.5, 'total_runs' => 24],
'product_info' => ['name' => 'Ürün Bilgisi', 'success_rate' => 87.0, 'total_runs' => 15],
'refund_status' => ['name' => 'İade & İptal', 'success_rate' => 80.0, 'total_runs' => 10],
```

**Risk:** Operasyon analitiği paneli gerçek olmayan başarı oranları gösterir. Bu müşteri hizmetleri modülünün temel ilkesiyle çelişir: sistem uydurmamalı, veri yoksa “veri yok” demelidir.

**Zorunlu düzeltme:**

- Sahte metrik üretimi kaldırılmalı.
- UI, `topics` boş olduğunda açık “Henüz yeterli AI çalışma verisi yok” empty state göstermeli.
- Test eklenmeli:
  - `test_analytics_does_not_fabricate_topic_metrics_when_no_ai_runs`

---

### P1-3 — `MarketplaceQuestionsTest` veri üretimi flaky unique çakışmasına açık

**Dosya:** `tests/Feature/MarketplaceQuestionsTest.php`  
**Satır:** 659

Antigravity diff’i:

```diff
- $suffix = (string) random_int(100000, 999999);
+ $suffix = (string) (time() % 100000) . (string) random_int(1000, 9999);
```

Bu değişiklik aynı saniyede çoklu test çalışırken `questions-{suffix}@example.test` e-posta alanında çakışma üretebiliyor. Hedef test paketinde gerçek çakışma görüldü.

**Zorunlu düzeltme:**

- Suffix üretimi çakışmasız hale getirilmeli: `Str::uuid()`, `Str::ulid()` veya factory’nin unique email üretimi kullanılmalı.
- `MarketplaceQuestionsTest` tam dosya olarak çalıştırılıp yeşil kanıtlanmalı.
- Hedef paket tekrar yeşil olmalı.

---

## 4. P2 / İyileştirme Notları

### P2-1 — Analytics export testi gerçek CSV gövdesini güçlü doğrulamıyor

**Dosya:** `tests/Feature/CustomerCare/CustomerCareAnalyticsTest.php`

`test_secure_pii_redacted_export` şu an Livewire response effect/html üzerinden zayıf kontrol yapıyor. Export stream içeriği doğrudan assert edilmeli.

Öneri:

- CSV response body yakalanmalı.
- BOM, UTF-8, XML kontrol karakter temizliği ve PII yokluğu gerçek içerikte doğrulanmalı.

---

### P2-2 — `CustomerCareAnalyticsService` SLA hesaplarında `created_at` / `sent_at` tutarlılığı netleşmeli

Ortalama ilk yanıt ve çözüm süresi `created_at` üzerinden hesaplanıyor; bazı mesaj üretimlerinde iş zamanı `sent_at` alanına yazılıyor. Bu fazda blocker değil, ama analitik doğruluğu için ADR veya test ile netleştirilmeli.

---

## 5. Fazla Kapsam / Dikkat

Bu kalite kapısı kod düzeltmesi istemiyor; yalnız Antigravity’ye düzeltme kapsamı açılmalı.

Antigravity şu dosyalar dışında kapsam genişletmemeli:

- `app/Livewire/CustomerCare/KnowledgeSuggestions.php`
- `app/Services/Support/CustomerCareSuggestionService.php`
- `app/Services/Support/KnowledgeBaseService.php` veya merkezi PII redaction katmanı
- `app/Services/Support/SupportOutboxService.php`
- `app/Services/Support/CustomerCareAnalyticsService.php`
- `resources/views/livewire/customer-care/analytics.blade.php`
- `tests/Feature/CustomerCare/KnowledgeSuggestionsTest.php`
- `tests/Feature/CustomerCare/SupportOutboxTest.php` veya ilgili AI/outbox test dosyası
- `tests/Feature/CustomerCare/CustomerCareAnalyticsTest.php`
- `tests/Feature/MarketplaceQuestionsTest.php`
- İlgili kanıt paketleri: `docs/customer-care/dalga-m-kanit-paketi.md`, `dalga-n-kanit-paketi.md`, `dalga-o-kanit-paketi.md`, `walkthrough.md`

Commit, push veya branch değişikliği yapılmamalı.

---

## 6. Antigravity’ye Gönderilecek Talimat

```text
/Volumes/TWINMOS/zolm reposunda Dalga M/N/O Kalite Kapısı 01 revizyonunu uygula.

Önce şu dosyayı tamamen oku:

/Volumes/TWINMOS/zolm/docs/customer-care/dalga-mno-kalite-kapisi-01.md

Yalnız bu kalite kapısındaki P0/P1/P2 maddelerini düzelt.
Kapsam dışındaki mevcut kullanıcı değişikliklerine dokunma.
Commit, push veya branch değişikliği yapma.
Dalga P/Q/R'ye geçme.

Zorunlu kabul kriterleri:
1. KnowledgeSuggestions edit ve approve akışında PII ham veri bilgi önerisi veya bilgi bankasına yazılamasın.
2. CustomerCareSuggestionService store/conversation/message bütünlük uyuşmazlıklarında fail-closed çalışsın.
3. SupportOutboxService AI gate bloklarında mesaj delivery_status stale "sending" kalmasın ve confidence/decision context kaybolmasın.
4. Analytics servisinde veri yokken sahte başarı oranı üretilmesin; UI boş veri durumunu açık göstersin.
5. MarketplaceQuestionsTest suffix üretimi flaky unique çakışması üretmeyecek hale getirilsin.
6. Yeni/var olan testlerle tüm bu davranışlar kanıtlansın.

Çalıştırılacak kanıtlar:
- git diff --check
- npm run build
- ./vendor/bin/sail artisan test tests/Feature/CustomerCare tests/Feature/WhatsApp/SupportChannelTest.php tests/Feature/MarketplaceQuestionsTest.php --no-coverage --compact
- ./vendor/bin/sail artisan test --no-coverage --compact

Kanıt paketlerini ve walkthrough.md dosyasını güncel sonuçlarla güncelledikten sonra dur.
```

---

## 7. Sonuç

Dalga M/N/O ürün yönü doğru: Trendyol adapter, bilgi önerisi döngüsü ve operasyon analitiği modül vizyonuna hizmet ediyor.

Ancak canlı pilot öncesi sistemin “uydurmama, sızdırmama, tenant karıştırmama” ilkeleri tavizsiz olmalı. Bu nedenle bu teslimat kalite kapısından geçmemiştir.

**Durum:** ❌ Dalga M/N/O revizyon gerekiyor.  
**Sonraki adım:** Antigravity Kalite Kapısı 01 revizyonunu uygulasın; sonra yeniden baş mühendis kontrolü yapılacak.
