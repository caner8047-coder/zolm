# ZOLM AI Müşteri İletişim Merkezi — Dalga AB/AC/AD Kalite Kapısı 01

**Tarih:** 2026-07-13  
**İnceleyen:** Codex — Baş mühendis  
**Karar:** ❌ **Kabul edilmedi — P0/P1 revizyon gerekli**  

Bu kalite kapısı, Antigravity tarafından raporlanan Dalga AB/AC/AD çıktılarının bağımsız baş mühendis incelemesidir.

Dalga S/T/U + V/W/X Kalite Kapısı 02’deki Web Chat HMAC P0 düzeltmesinin kod tarafında kapandığı görülmüştür: `WebChatSupportChannelAdapter::projectMessage()` artık imzalı `raw_json` içeriğini decode edip iş verisini oradan okuyor ve `test_project_message_uses_signed_raw_json_not_outer_payload_fields` testi eklenmiş.

Ancak AB/AC/AD kapsamı hâlâ kabul seviyesinde değildir. Uygulama, raporlanan test sayılarına rağmen prompttaki güvenlik ve ürün doğruluğu kriterlerinin önemli kısmını karşılamıyor.

---

## Çalıştırılan Kontroller

```bash
git diff --check
./vendor/bin/sail artisan test \
  tests/Feature/CustomerCare/CustomerCareKnowledgeGroundingTest.php \
  tests/Feature/CustomerCare/CustomerCareRoutingTest.php \
  tests/Feature/CustomerCare/CustomerCareSalesAssistTest.php \
  tests/Feature/CustomerCare/WebChatSupportChannelAdapterTest.php \
  --no-coverage --compact
```

Sonuç:

- `git diff --check`: ✅ temiz
- Hedef testler: ✅ `19 passed / 58 assertions`

Not: Hedef testlerin yeşil olması olumlu; fakat AB/AC/AD için test kapsamı çok dardır:

- AB: 3 test
- AC: 3 test
- AD: 2 test

Prompttaki acceptance maddelerinin çoğu testlenmemiştir.

---

## P0-1 — Bilgi Bankası makale içeriği PII redaction olmadan AI context’e giriyor

**Dosya:** `app/Services/Support/CustomerCareKnowledgeGroundingService.php`

`WaKnowledgeArticle` içeriği şu anda sadece prompt-injection keyword içeriyorsa tamamen gizleniyor; ancak normal PII içeren bilgi makalesi ham şekilde context’e ekleniyor:

```php
$content = $article->content;
...
$kbText .= "Başlık: {$article->title}\nİçerik: {$content}\n\n";
```

Bu, Dalga AB kabul kriterindeki şu maddeye aykırıdır:

> PII içeren source kaydı redacted/sanitized olur.

### Risk

Bilgi bankasına yanlışlıkla telefon, e-posta, T.C. kimlik no, adres veya müşteri özel notu girilmişse, bu veri LLM prompt’una ve `support_ai_runs.prompt_raw/response_raw` zincirine sızabilir.

### Beklenen düzeltme

- `WaKnowledgeArticle.title` ve `content` LLM context’e eklenmeden önce `PiiRedactor::maskPii()` benzeri merkezi redaction’dan geçmeli.
- XML/control karakter ve HTML strip/sanitize uygulanmalı.
- Makale içeriği maksimum uzunlukla sınırlandırılmalı.
- Prompt injection filtresi PII redaction’dan bağımsız çalışmalı.

### Zorunlu test

`CustomerCareKnowledgeGroundingTest` içine:

- PII içeren knowledge article oluştur.
- `ground()` çağrısı sonrası `kb` içinde ham e-posta/telefon/TCKN görünmediğini assert et.
- Redacted placeholder göründüğünü assert et.

---

## P0-2 — Satış Copilot stale fiyatı net fiyat olarak yazabiliyor

**Dosya:** `app/Services/Support/CustomerCareSalesAssistService.php`

Alternatif ürün önerilerinde yalnız `last_stock_sync_at >= now()->subHours(24)` kontrol ediliyor. `last_price_sync_at` kontrol edilmiyor.

Kod şu anda stale fiyatlı ürünü önerip net fiyat yazabilir:

```php
->where('last_stock_sync_at', '>=', now()->subHours(24))
...
'suggested_draft' => "... (Fiyatı: {$alt->sale_price} {$alt->currency}).",
```

Bu, Dalga AD kabul kriterlerine aykırıdır:

> Stok/fiyat/kampanya güncel değilse net satış vaadi yok.  
> fiyat/kampanya stale ise net fiyat/kampanya cevabı yok.

### Beklenen düzeltme

- Alternatif ürün önerisi için hem stok hem fiyat freshness zorunlu olmalı.
- `last_price_sync_at` null veya 24 saatten eskiyse:
  - ürün önerilmemeli veya
  - fiyat cümlesi kesinlikle yazılmamalı.
- `sale_price` null/0/negatif gibi invalid değerler güvenli şekilde elenmeli.

### Zorunlu test

`CustomerCareSalesAssistTest` içine:

- Stok fresh ama fiyat stale olan listing oluştur.
- `generateSalesSuggestions()` sonucu net fiyat içermemeli veya öneri üretmemeli.
- “Fiyatı: 299.90 TRY” gibi net fiyat cümlesi olmadığını assert et.

---

## P0-3 — Sales Copilot cart signal imzalı Web Chat kaynağıyla doğrulanmıyor

**Dosya:** `app/Services/Support/CustomerCareSalesAssistService.php`

Servis `source_reference_json` içindeki `cart_value` / `cart_items` alanlarını doğrudan güvenilir kabul ediyor:

```php
$ref = $conversation->source_reference_json ?? [];
$cartValue = (float)($ref['cart_value'] ?? 0);
```

Dalga AD promptunda ise cart/session signal için açık şart vardı:

> Web chat signed payload içinde cart/session sinyali varsa kullanılabilir.  
> İmzasız veya stale cart sinyali kullanılmaz.

Mevcut implementation’da cart sinyalinin:

- imzalı Web Chat projection’dan geldiği,
- stale olmadığı,
- store scoped olduğu,
- PII içermediği,
- manipüle edilmediği

kanıtlanmıyor.

### Beklenen düzeltme

- Cart recovery yalnız güvenli kaynak işareti varsa çalışmalı. Örn:
  - `source_type = web_chat`
  - `source_reference_json.cart_signal_verified = true`
  - `source_reference_json.cart_signal_at >= now()->subMinutes(...)`
  - signed raw payload projection tarafından üretilmiş olmalı.
- İmzasız/manual set edilmiş `cart_value` için öneri üretilmemeli.
- Cart item içeriği PII redaction’dan geçmeli.

### Zorunlu test

`CustomerCareSalesAssistTest` içine:

- Sadece `source_reference_json.cart_value` set edilmiş ama verified olmayan conversation için cart recovery önerisi üretilmediğini assert et.
- Verified + fresh cart signal için öneri üretildiğini assert et.
- Stale verified cart signal için öneri üretilmediğini assert et.

---

## P1-1 — AB/AC/AD kanıt paketleri oluşturulmamış

Promptta zorunlu istenen dosyalar yok:

- `docs/customer-care/dalga-ab-kanit-paketi.md`
- `docs/customer-care/dalga-ac-kanit-paketi.md`
- `docs/customer-care/dalga-ad-kanit-paketi.md`

Antigravity raporunda `walkthrough.md` güncellendiği söylenmiş; ancak repo kökündeki `walkthrough.md` mevcut içerik olarak genel kurulum rehberi görünüyor ve AB/AC/AD kanıtlarını taşımıyor.

### Beklenen düzeltme

Her dalga için ayrı kanıt paketi oluştur:

- değişen dosyalar,
- migration listesi,
- test isimleri,
- çalıştırılan komutlar,
- bilinen eksikler,
- rollback notu,
- feature flag defaultları.

---

## P1-2 — AB/AD kaynak modellemesi hâlâ gerçek source-of-truth seviyesinde değil

**Dosya:** `app/Services/Support/CustomerCareKnowledgeGroundingService.php`

Servis `ChannelListing` bulamazsa `MpProduct` fallback kullanıyor:

```php
$pQuery = \App\Models\MpProduct::where('user_id', $store->user_id);
```

Bu fallback store-scoped değildir; aynı kullanıcı altında birden çok mağaza olduğunda mağazalar arası ürün bağlamı karışabilir. Ayrıca fallback ürünlerde stok/fiyat freshness yoktur ve `is_stale=false` yazılmaktadır.

### Beklenen düzeltme

- Store scope kanıtlanamayan fallback kaynakları AI grounding için kullanılmamalı.
- `MpProduct` kullanılacaksa store/channel ilişki sınırı açıkça kurulmalı.
- Freshness bilinmiyorsa `is_stale=true` veya kaynak “copilot-only / no exact price-stock” olarak işaretlenmeli.

### Zorunlu test

- Aynı kullanıcıya ait iki mağaza senaryosunda Store A grounding Store B/ortak fallback ürünü sızdırmamalı.
- Freshness bilinmeyen fallback ürün net stok/fiyat kaynağı gibi kullanılmamalı.

---

## P1-3 — Routing service takım üyeliği ve actor yetkisini doğrulamıyor

**Dosya:** `app/Services/Support/CustomerCareRoutingService.php`

`claim()` metodu kullanıcıyı conversation store’una veya team membership’e göre doğrulamıyor. UI katmanında `TenantContext::enforceConversationAccess()` çağrısı var; fakat service doğrudan kullanıldığında yetkisiz kullanıcı conversation claim edebilir.

### Beklenen düzeltme

- `claim()` ve `release()` service seviyesinde de actor/store yetki kontrolü yapmalı.
- Team bazlı claim kuralı varsa kullanıcı ilgili store/team üyesi olmalı.
- Background/CLI path varsa system actor açıkça kullanılmalı.

### Zorunlu test

- Farklı store kullanıcısı `CustomerCareRoutingService::claim()` çağırdığında fail-closed dönmeli.
- Team üyesi olmayan kullanıcı ilgili team queue conversation’ı claim edememeli veya ürün kararı olarak bu davranış açıkça ADR/kanıt paketinde belirtilmeli.

---

## P1-4 — Business hours yalnız routing rule olarak var; automatic gate’e bağlanmamış

Promptta şu şart vardı:

> Mesai dışı otomatik cevap default kapalı veya ayrı allowlist’e bağlı.

Mevcut kodda `business_hours` yalnız `CustomerCareRoutingService::ruleMatches()` içinde rule match olarak duruyor. `SupportReplyService::sendAiReply()` veya `CustomerCareAutomationGate` seviyesinde mesai dışı automatic yanıt engeli görünmüyor.

### Beklenen düzeltme

- Mesai dışı automatic reply merkezi automation gate içinde engellenmeli veya açık allowlist config’e bağlanmalı.
- Manual/agent reply bundan etkilenmemeli.

### Zorunlu test

- Mesai dışı + automatic mode + allowlist kapalı: `sendAiReply()` fail-closed.
- Mesai dışı manual/agent reply başarılı.

---

## P1-5 — Test kapsamı acceptance kriterlerini karşılamıyor

Eksik testlerden bazıları:

- AB:
  - PII source redaction
  - no-source durumda handoff/copy behavior
  - citation ledger’a yazılır
  - out-of-stock “stokta var” denmez
  - sync command dry-run veri değiştirmez
- AC:
  - cross-store routing leak
  - team membership claim guard
  - business hours automatic gate
  - command route/list kanıtı
- AD:
  - katalogda olmayan ürün önerilmez
  - stale price/kampanya net cevap yok
  - marketplace policy satış taslağındaki dış link/telefonu bloklar
  - signed/verified cart signal ayrımı
  - suggestion ledger/event PII içermez

---

## Olumlu Notlar

- Önceki Web Chat HMAC P0 açık doğru yönde kapanmış.
- `CustomerCareKnowledgeGroundingService` stale stock/price flag fikri doğru.
- `CustomerCareRoutingService::claim()` içinde `lockForUpdate()` kullanımı concurrency için doğru yönde.
- SLA escalation audit izi (`sla_escalated`) doğru bir başlangıç.
- Sales Copilot public Google/Instagram/Facebook comment tarafında öneriyi kapatıyor.
- Hedef testler ve `git diff --check` temiz.

---

## Antigravity’ye Verilecek Revizyon Komutu

```text
/Volumes/TWINMOS/zolm reposunda Dalga AB/AC/AD Kalite Kapısı 01 revizyonlarını uygula.

Önce şu dosyayı tamamen oku ve içindeki P0/P1 maddeleri eksiksiz düzelt:

/Volumes/TWINMOS/zolm/docs/customer-care/dalga-abacad-kalite-kapisi-01.md

Kurallar:
- Commit, push veya branch değişikliği yapma.
- Kapsam dışındaki mevcut kullanıcı değişikliklerine dokunma.
- Feature flag varsayılanlarını kapalı tut.
- Otomatik yanıtı global olarak açma.
- Testlerde canlı dış API çağrısı yapma.
- Var olmayan ürün, stok, fiyat, kampanya veya cart signal uydurma.
- Knowledge source içeriği LLM context’e girmeden PII redaction’dan geçsin.
- Sales Copilot stale fiyat/kampanya ile net satış vaadi üretmesin.
- Cart recovery yalnız verified + fresh signed web chat cart signal ile çalışsın.
- Routing claim/release service seviyesinde tenant/team guard içersin.
- Mesai dışı automatic reply merkezi gate’te fail-closed olsun.
- AB/AC/AD ayrı kanıt paketlerini oluştur.
- Test, build, route, command, scheduler ve git kanıt paketini verdikten sonra dur.
```

---

## Sonuç

Dalga AB/AC/AD şu haliyle kabul edilmedi. Uygulama iyi bir iskelet çıkarmış; fakat müşteri verisi, kaynak doğruluğu, satış vaadi ve ekip yetkisi gibi canlı riskleri kapatmadan pilot/ürün kabulü verilemez.

Bu kalite kapısındaki P0/P1 maddeler kapatıldıktan sonra tekrar bağımsız inceleme yapılacaktır.
