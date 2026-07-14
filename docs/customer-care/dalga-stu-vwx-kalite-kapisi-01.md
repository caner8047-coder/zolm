# ZOLM AI Müşteri İletişim Merkezi — Dalga S/T/U + V/W/X Kalite Kapısı 01

**Tarih:** 2026-07-12  
**İnceleyen:** Codex — Baş mühendis  
**Karar:** ❌ **Kabul edilmedi — P0/P1 revizyon gerekli**  

Bu kalite kapısı; Antigravity tarafından raporlanan Dalga S/T/U ve Dalga V/W/X çıktılarının bağımsız baş mühendis incelemesidir.

Testlerin yeşil olması olumlu; ancak public/social/review/web-chat kanallarında “gerçek gönderim” ile “sahte başarılı kabul” arasındaki çizgi kırılmış durumda. Bu fazlar canlı müşteri kanallarına temas ettiği için fail-closed davranış, test yeşilliğinden daha önceliklidir.

---

## Çalıştırılan Kontroller

```bash
git diff --check
./vendor/bin/sail artisan test \
  tests/Feature/CustomerCare/MetaSocialSupportChannelAdapterTest.php \
  tests/Feature/CustomerCare/GoogleBusinessSupportChannelAdapterTest.php \
  tests/Feature/CustomerCare/WebChatSupportChannelAdapterTest.php \
  tests/Feature/CustomerCare/CustomerCareAnonymizationTest.php \
  --no-coverage --compact
```

Sonuç:

- `git diff --check`: ✅ temiz
- Hedef testler: ✅ `31 passed / 86 assertions`

Not: Bu testler aşağıdaki P0 entegrasyon açıklarını kapsamıyor.

---

## P0-1 — Meta Social outbound sahte başarılı dönüyor

**Dosya:** `app/Services/Support/MetaSocialSupportChannelAdapter.php`

`sendReply()` aktif `IntegrationConnection` ve conversation bulduktan sonra gerçek Meta connector/outbox olmadığı halde:

- `meta_msg_*` şeklinde yapay `channel_message_id` üretiyor,
- idempotency cache’e bu yapay ID’yi yazıyor,
- `success => true` dönüyor,
- `getOutboundTargetStatus()` sabit `sent` dönüyor.

Bu davranış Dalga V promptundaki şu kurala aykırıdır:

> Eğer mevcut connector yoksa sahte success dönme; fail-closed.

### Beklenen düzeltme

- Gerçek Meta delivery provider / connector yoksa `success=false` dönmeli.
- Gerçek connector yokken `SupportAgentAction` üzerinde `meta_outbox_handoff` yazılmamalı.
- `getCapabilities()` içinde `send_messages/reply_comments` yalnız aktif connection değil, gerçek outbound provider desteği de varsa `available` olmalı.
- Idempotency lock yalnız gerçek provider kabulünden veya gerçek internal outbox kaydından sonra yazılmalı.

### Zorunlu testler

- Aktif Meta connection var ama outbound provider yoksa `sendReply()` fail-closed döner.
- Aynı senaryoda support outbox dispatch/message `sent` olmaz.
- Public comment outbound’da connector yoksa fake `channel_message_id` oluşmaz.

---

## P0-2 — Google Business review reply sahte başarılı dönüyor

**Dosya:** `app/Services/Support/GoogleBusinessSupportChannelAdapter.php`

`sendReply()` aktif Google connection ve conversation bulduktan sonra gerçek Google Business Profile API/provider olmadan:

- `google_reply_*` şeklinde yapay ID üretiyor,
- `google_review_replied` audit aksiyonu yazıyor,
- `success => true` dönüyor,
- `getOutboundTargetStatus()` sabit `sent` dönüyor.

Bu davranış Dalga W promptundaki şu kurala aykırıdır:

> Google Business Profile API endpointleri yoksa uydurma.

### Beklenen düzeltme

- Gerçek GBP reply provider yoksa `success=false` dönmeli.
- “Yorum yanıtı yayınlandı” mesajı yalnız gerçek provider cevabı veya gerçek internal dispatch kabulü sonrası kullanılmalı.
- `getCapabilities()` içinde `reply_reviews` gerçek outbound provider yoksa `unavailable` kalmalı.

### Zorunlu testler

- Aktif Google connection var ama reply provider yoksa `sendReply()` fail-closed döner.
- Dispatch/message `sent` olmaz.
- `google_review_replied` audit log yalnız gerçek yanıt veya gerçek provider kabulünden sonra yazılır.

---

## P0-3 — Web Chat offline durumunda generic outbox hâlâ `sent` yazabilir

**Dosyalar:**

- `app/Services/Support/WebChatSupportChannelAdapter.php`
- `app/Services/Support/SupportOutboxService.php`

`WebChatSupportChannelAdapter::sendReply()` müşteri offline ise doğru şekilde `status => queued` dönüyor. Ancak generic outbox gönderim yolu adapter sonucundaki `status` alanını okumuyor:

```php
$targetStatus = $adapter->getOutboundTargetStatus();
```

`WebChatSupportChannelAdapter::getOutboundTargetStatus()` ise sabit `sent` dönüyor.

Sonuç: doğrudan adapter testi yeşil olsa bile gerçek `SupportOutboxService::sendDispatch()` yolunda offline web chat yanıtı `sent` olarak işaretlenebilir.

### Beklenen düzeltme

Seçeneklerden biri uygulanmalı:

1. `SupportOutboxService` güvenli allowlist ile `$result['status']` değerini dikkate almalı (`sent`, `accepted`, `queued`, `pending` gibi).
2. Adapter contract dinamik outbound status dönebilecek şekilde genişletilmeli.
3. Web Chat outbound için ayrı delivery queue/table varsa generic dispatch bu gerçek durumu kullanmalı.

### Zorunlu testler

- Offline web chat conversation için `SupportOutboxService::sendDispatch()` çalıştırıldığında:
  - `SupportDispatch.status` `sent` olmaz,
  - `SupportMessage.delivery_status` `sent` olmaz,
  - beklenen durum `queued` veya `pending` olur.
- Online web chat için yalnız gerçek socket/provider kabulü varsa `sent`, yoksa `accepted/queued` kullanılır.

---

## P0-4 — Web Chat projection HMAC doğrulamayı zorunlu tutmuyor

**Dosya:** `app/Services/Support/WebChatSupportChannelAdapter.php`

`verifySignature()` metodu var; ancak `projectMessage()` imza veya signed token zorunluluğu uygulamıyor. Şu an adapter metodunu doğrudan kullanan herhangi bir public inbound handler imzasız payload’ı projection’a alabilir.

Dalga X promptundaki zorunlu kabul testi:

> missing/invalid signature 403.

Mevcut test yalnız `verifySignature()` metodunun matematiksel olarak doğru çalıştığını kanıtlıyor; inbound projection yolunun imzayı zorunlu tuttuğunu kanıtlamıyor.

### Beklenen düzeltme

- Public inbound endpoint yoksa oluşturulmalı; endpoint imzasız payload’da `403` dönmeli.
- Eğer endpoint kapsam dışı tutulacaksa `projectMessage()` imza doğrulanmış context olmadan çalışmayacak şekilde fail-closed olmalı.
- Secret `IntegrationConnection.webhook_secret` veya encrypted credential üzerinden alınmalı; payload içinden gelen salt/secret güven kaynağı olmamalı.

### Zorunlu testler

- Missing signature → `403` veya fail-closed.
- Invalid signature → `403` veya fail-closed.
- Valid signature → projection başarılı.
- Cross-store signed token/payload mismatch → fail-closed.

---

## P1-1 — Circuit breaker pending AI dispatch iptalinde message status güncellenmiyor

**Dosya:** `app/Services/Support/CustomerCareAnonymizationService.php`

`cancelPendingAiDispatches()` sadece `support_dispatches.status` alanını `cancelled` yapıyor. İlgili AI `SupportMessage.delivery_status` alanı `pending` kalabilir.

Bu durum operasyon panelinde “mesaj hâlâ bekliyor mu / iptal mi?” belirsizliği yaratır.

### Beklenen düzeltme

- İptal edilen AI dispatch’lerin bağlı mesajları da `delivery_status = cancelled` veya proje standardındaki terminal statüye çekilmeli.
- Agent/manual dispatch’ler etkilenmemeye devam etmeli.
- Mümkünse `SupportDispatchAttempt` veya `SupportAgentAction` üzerinde emergency stop audit izi yazılmalı.

### Zorunlu testler

- AI dispatch iptal edilince bağlı `SupportMessage.delivery_status` da terminal statüye geçer.
- Agent dispatch/message etkilenmez.

---

## P1-2 — Test kapsamı “adapter doğrudan çağrı” seviyesinde kalmış

V/W/X testleri adapter metotlarını doğrudan çağırıyor; ancak canlı yolun önemli kısmı generic outbox üzerinden geçiyor.

### Beklenen ek testler

- Meta outbound → `SupportOutboxService::sendDispatch()`
- Google review reply → `SupportOutboxService::sendDispatch()`
- Web Chat offline outbound → `SupportOutboxService::sendDispatch()`
- Policy block + public comment/review kanal suffix davranışı → gerçek dispatch yolu

Bu testler olmadan “adapter testi yeşil” canlı dispatch güvenliği için yeterli kabul edilmez.

---

## Olumlu Notlar

- S/T/U tarafında temel omurga doğru yönde:
  - WhatsApp context-aware capability ve consent/suppression kontrolleri eklenmiş.
  - Customer identity resolver ham telefon yerine hash yaklaşımına gitmiş.
  - Customer summary store-scoped ve PII maskeli tasarlanmış.
  - Anonymization servisinde dry-run default ve force zorunluluğu doğru.
- V/W/X tarafında inbound projection, idempotency ve raw payload izolasyonu için iyi başlangıç var.
- `git diff --check` temiz.

---

## Antigravity’ye Verilecek Revizyon Komutu

```text
/Volumes/TWINMOS/zolm reposunda Dalga S/T/U + V/W/X Kalite Kapısı 01 revizyonlarını uygula.

Önce şu dosyayı tamamen oku ve içindeki P0/P1 maddeleri eksiksiz düzelt:

/Volumes/TWINMOS/zolm/docs/customer-care/dalga-stu-vwx-kalite-kapisi-01.md

Kurallar:
- Commit, push veya branch değişikliği yapma.
- Kapsam dışındaki mevcut kullanıcı değişikliklerine dokunma.
- Feature flag varsayılanlarını kapalı tut.
- Otomatik yanıtı global olarak açma.
- Testlerde canlı dış API çağrısı yapma.
- Var olmayan API endpoint’i veya credential davranışı uydurma.
- Gerçek outbound provider/connector yoksa sahte success dönme; fail-closed çalış.
- Web Chat offline outbound generic SupportOutboxService yolunda sent yazılmamalı.
- Web Chat inbound projection HMAC/signed token doğrulaması olmadan çalışmamalı.
- Circuit breaker pending AI dispatch iptalinde bağlı AI message delivery_status da terminal statüye çekilmeli.
- Yeni testler adapter doğrudan çağrı yanında SupportOutboxService gerçek dispatch yolunu da kapsamalı.
- Dalga Y/Z/AA veya başka kapsama geçme.

Revizyon sonunda şu kanıtları ver:
- git status --short
- git diff --check
- npm run build
- ./vendor/bin/sail artisan route:list --name=customer-care
- ./vendor/bin/sail artisan list customer-care --raw
- ./vendor/bin/sail artisan schedule:list
- ./vendor/bin/sail artisan test tests/Feature/CustomerCare tests/Feature/WhatsApp/SupportChannelTest.php tests/Feature/MarketplaceQuestionsTest.php --no-coverage --compact
- ./vendor/bin/sail artisan test --no-coverage --compact
- Düzeltme dosya-test eşleşmeleri

Kanıt paketlerini güncelle:
- docs/customer-care/dalga-s-kanit-paketi.md
- docs/customer-care/dalga-t-kanit-paketi.md
- docs/customer-care/dalga-u-kanit-paketi.md
- docs/customer-care/dalga-v-kanit-paketi.md
- docs/customer-care/dalga-w-kanit-paketi.md
- docs/customer-care/dalga-x-kanit-paketi.md
- walkthrough.md

İş bitince dur; kalite kapısı onayını Codex baş mühendis incelemesine bırak.
```

---

## Sonuç

Dalga S/T/U + V/W/X şu haliyle canlı pilot kapısından geçemez.

Özellikle V/W/X public kanal davranışları, müşteri ve marka itibarı açısından P0 seviyesindedir. Revizyon tamamlandıktan sonra tekrar kalite kapısı yapılacaktır.
