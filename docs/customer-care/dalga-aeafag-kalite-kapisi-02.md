# ZOLM AI Müşteri İletişim Merkezi — Dalga AE/AF/AG Kalite Kapısı 02

Tarih: 2026-07-13  
İnceleyen: Codex Baş Mühendis  
Karar: **RED / DAR REVİZYON GEREKLİ**

Bu rapor, Dalga AE/AF/AG Kalite Kapısı 01 revizyonu sonrası yapılan bağımsız kontroldür. Önceki P0 maddelerinin önemli bölümü kapanmıştır; ancak canlı güvenlik ve veri sızıntısı açısından hâlâ kabul engeli olan dar kapsamlı riskler kalmıştır.

---

## 1. Doğrulanan İyileştirmeler

Çalıştırılan hedef test:

```bash
./vendor/bin/sail artisan test \
  tests/Feature/CustomerCare/CustomerCareQualityTest.php \
  tests/Feature/CustomerCare/CustomerCareIntegrationHubTest.php \
  tests/Feature/CustomerCare/CustomerCareOpsTest.php \
  --no-coverage --compact
```

Sonuç:

```text
25 passed (68 assertions)
```

Kod kontrolünde kapanmış görünen başlıklar:

- `/customer-care/quality` ve `/customer-care/ops` route’ları feature middleware’e bağlanmış.
- `quality_center_enabled` ve `ops_center_enabled` config bayrakları varsayılan kapalı eklenmiş.
- `SupportReplyService::sendAiReply()` içine provider health ve budget guard eklenmiş.
- Integration Hub empty secret durumunda HTTP çağrısı yapmadan fail-closed dönüyor.
- `support_integration_events` için `(store_id, idempotency_key)` unique index eklenmiş.
- Ops metriklerinde AI run failure ve dispatch failure ayrılmış.
- AE/AF/AG kanıt paketleri oluşturulmuş.

Bu ilerleme doğru yönde; fakat aşağıdaki maddeler kapanmadan kabul verilemez.

---

## 2. P0 Kabul Engelleri

### P0-1 — Integration Hub encrypted secret zorunlu değil; plaintext fallback hâlâ canlı gönderimde kullanılıyor

Dosyalar:

- `app/Services/Support/Integration/CustomerCareIntegrationHubService.php`
- `app/Livewire/CustomerCare/Integrations.php`
- `tests/Feature/CustomerCare/CustomerCareIntegrationHubTest.php`

Durum:

`CustomerCareIntegrationHubService::dispatchEvent()` ve `Integrations::retryDelivery()` secret çözümlemesinde şu pattern var:

```php
try {
    $secret = Crypt::decryptString($rawSecret);
} catch (DecryptException $e) {
    $secret = $rawSecret;
}
```

Bu, decrypt edilemeyen herhangi bir değeri plaintext secret kabul edip outbound webhook imzasında kullanıyor. Hatta mevcut `CustomerCareIntegrationHubTest::setUp()` içinde webhook channel secret hâlâ plaintext olarak kuruluyor ve ana HMAC testi bu plaintext fallback sayesinde geçiyor.

Risk:

Bu davranış “secret encrypted olmalı” kabul kriterini fiilen deliyor. Veritabanındaki plaintext veya bozuk ciphertext, fail-closed yerine geçerli secret gibi kullanılıyor. Enterprise Integration Hub için bu kabul edilemez.

Beklenen düzeltme:

- Runtime gönderim yolu yalnız decrypt edilebilen encrypted secret kabul etmeli.
- Decrypt başarısızsa:
  - HTTP çağrısı yapılmamalı,
  - delivery `failed` olmalı,
  - güvenli hata yazılmalı.
- Eski plaintext veriler desteklenecekse bu ayrı ve kontrollü migration/rotation adımı olmalı; normal dispatch path plaintext fallback yapmamalı.
- Testler:
  - Plaintext `webhook_secret` ile `dispatchEvent()` HTTP çağrısı yapmaz.
  - Invalid ciphertext ile `dispatchEvent()` HTTP çağrısı yapmaz.
  - Ana HMAC testi encrypted secret ile kurulmalı.
  - `retryDelivery()` invalid/plaintext secret ile HTTP çağrısı yapmaz.

---

### P0-2 — QualityCenter submitReview client-side property manipülasyonu ile cross-store message/conversation yazabilir

Dosya:

- `app/Livewire/CustomerCare/QualityCenter.php`

Durum:

`submitReview()` içinde doğrulama yalnız `selectedItemId` üstünden yapılıyor:

- `filterType === ai_run` ise selected AI run mağazaya ait mi diye bakılıyor.
- `agent_reply` ise selected item message mağazaya ait mi diye bakılıyor.

Fakat review oluşturulurken şu Livewire public property’leri doğrudan kullanılıyor:

- `$this->selectedConversationId`
- `$this->selectedMessageId`

Bu property’ler client-side Livewire payload ile manipüle edilebilir. Yani saldırgan/yanlış client şu akışı oluşturabilir:

1. `selectedItemId` = Store A’ya ait geçerli kayıt.
2. `selectedConversationId` / `selectedMessageId` = Store B’ye ait kayıt.
3. `submitReview()` doğrulaması geçer.
4. `SupportQualityReview` Store A altında Store B message/conversation referansı ile yazılabilir.
5. `kb_candidate` kararında `SupportMessage::find($this->selectedMessageId)` ile cross-store mesaj gövdesi öneriye taşınabilir.

Mevcut testler Store B kaydını doğrudan `selectedItemId` yapmayı test ediyor; fakat `selectedItemId` geçerli iken `selectedMessageId`/`selectedConversationId` manipülasyonunu test etmiyor.

Risk:

Bu doğrudan cross-store IDOR ve PII sızıntısı riskidir.

Beklenen düzeltme:

- `submitReview()` public property’lere güvenmemeli.
- Server-side resolver ile canonical target yeniden yüklenmeli:
  - AI run seçiliyse run store scoped yüklenmeli; conversation/message ID’leri run’dan ve store doğrulamasından türetilmeli.
  - Agent reply seçiliyse message store scoped yüklenmeli; conversation ID message ilişkisinden türetilmeli.
- Review ve KB suggestion yazımında sadece canonical resolved IDs kullanılmalı.
- `SupportMessage::find()` yerine store-scoped resolver kullanılmalı.
- Testler:
  - `selectedItemId` Store A valid, `selectedMessageId` Store B manipüle → işlem fail-closed.
  - `selectedItemId` Store A valid, `selectedConversationId` Store B manipüle → işlem fail-closed.
  - `kb_candidate` cross-store manipulated message body yazamaz.

---

### P0-3 — Quality sampling command dry-run/execute çıktılarında raw prompt/body PII sızabilir

Dosya:

- `app/Console/Commands/CustomerCareSampleQualityReviewsCommand.php`

Durum:

Komut tablo çıktısında doğrudan raw alanları özetliyor:

```php
mb_substr($run->prompt_raw, 0, 40)
mb_substr($msg->body_encrypted, 0, 40)
```

Bu çıktı terminal loglarında, CI çıktılarında veya operasyon transcript’lerinde kalabilir. Quality dalgasında PII redaction temel kabul kriteridir; yalnız DB yazımını maskelemek yetmez.

Risk:

Dry-run güvenli kabul edildiği için daha sık çalıştırılır. Raw müşteri adı, telefon, e-posta, sipariş notu veya hassas mesaj terminal çıktısına sızabilir.

Beklenen düzeltme:

- Komut çıktısındaki AI prompt ve agent message özetleri `PiiRedactor` ile maskelenmeli.
- XML/control character sanitize uygulanmalı.
- Test:
  - Dry-run output raw telefon/e-posta/isim içermez.
  - Execute output raw telefon/e-posta/isim içermez.

---

## 3. P1 Revizyon Maddeleri

### P1-1 — Existing encrypted secret tekrar kaydedilirken double-encrypt riski var

Dosya:

- `app/Livewire/CustomerCare/Integrations.php`

Durum:

`saveWebhook()` içinde kullanıcı yeni secret girmeden sadece webhook URL güncellerse:

```php
$secret = $channel->config_json['webhook_secret'];
...
'webhook_secret' => Crypt::encryptString($secret)
```

Bu durumda mevcut encrypted blob tekrar encrypt edilir. Sonraki dispatch’te tek decrypt sonrası gerçek secret değil, eski encrypted blob elde edilir. Webhook imzası dış sistemle uyuşmaz.

Beklenen düzeltme:

- Yeni secret girildiyse encrypt et.
- Yeni secret girilmediyse mevcut encrypted secret blob’u aynen koru.
- Mevcut secret invalid ise kaydetme/dispatch fail-closed.
- Test:
  - URL güncelleme + secret boş → secret double-encrypt olmaz, decrypt sonucu eski raw secret kalır.

---

### P1-2 — Sample command system actor fallback hatalı ve audit kimliği belirsiz

Dosya:

- `app/Console/Commands/CustomerCareSampleQualityReviewsCommand.php`

Durum:

```php
'reviewer_id' => TenantContext::getSystemActor()->id ?? 1
```

Bu ifade `getSystemActor()` null dönerse null-safe değildir; ayrıca `?? 1` fallback’i belirsiz kullanıcıya audit yazma riski taşır. Önceki dalgalarda system actor deterministic ve fail-closed olmalı kararı alınmıştı.

Beklenen düzeltme:

- System actor yoksa komut fail-closed ve açıklayıcı hata ile durmalı.
- `1` fallback kullanılmamalı.
- Test:
  - System actor yokken `--execute` review yazmaz ve non-zero exit code döner.

---

### P1-3 — Ops maliyet UI hâlâ unknown-only durumda `$0.0000` gösterebilir

Dosyalar:

- `app/Livewire/CustomerCare/OpsCenter.php`
- `resources/views/livewire/customer-care/ops-center.blade.php`

Durum:

`hasCostData=true`, ancak tüm cost event’lerin `cost_estimate=null` olduğu durumda `sum('cost_estimate')` sonucu `0` döner ve UI:

```text
$0.0000
⚠️ X adet çalıştırmanın maliyeti hesaplanamadı
```

gösterebilir. Uyarı var ama ana değer yine sahte sıfır izlenimi oluşturur.

Beklenen düzeltme:

- Bilinen cost yoksa ana değer `$0.0000` değil “Hesaplanamadı / Bilinmiyor” olmalı.
- Bilinen ve bilinmeyen karışık ise:
  - “Bilinen toplam: $X”
  - “Bilinmeyen: N çalışma”
  ayrı gösterilmeli.
- Test:
  - Yalnız null cost event varken view `$0.0000` göstermez.

---

### P1-4 — `.env.example` yeni AE/AF/AG flag’lerini içermiyor

Dosya:

- `.env.example`

Durum:

`CUSTOMER_CARE_QUALITY_CENTER_ENABLED`, `CUSTOMER_CARE_OPS_CENTER_ENABLED`, `CUSTOMER_CARE_INTEGRATION_HUB_ENABLED`, budget cap değişkenleri `.env.example` içinde görünmüyor.

Beklenen düzeltme:

- Yeni flag/env değişkenleri `.env.example` içine varsayılan güvenli değerlerle eklenmeli.
- Değişkenler kısa açıklama ile gruplandırılmalı.

---

## 4. Kabul İçin Minimum Kanıt

Revizyon sonrası Antigravity şu çıktıları vermeli:

```bash
git diff --check
./vendor/bin/sail artisan test tests/Feature/CustomerCare/CustomerCareQualityTest.php tests/Feature/CustomerCare/CustomerCareIntegrationHubTest.php tests/Feature/CustomerCare/CustomerCareOpsTest.php --no-coverage --compact
./vendor/bin/sail artisan test tests/Feature/CustomerCare --no-coverage --compact
./vendor/bin/sail artisan test --no-coverage --compact
npm run build
```

Ek kanıt:

- `docs/customer-care/dalga-ae-kanit-paketi.md` güncellendi.
- `docs/customer-care/dalga-af-kanit-paketi.md` güncellendi.
- `docs/customer-care/dalga-ag-kanit-paketi.md` güncellendi.
- `walkthrough.md` güncellendi.

---

## 5. Antigravity’ye Verilecek Kısa Komut

```text
/Volumes/TWINMOS/zolm reposunda Dalga AE/AF/AG Kalite Kapısı 02 revizyonlarını uygula.

Önce şu dosyayı tamamen oku ve içindeki P0/P1 maddelerini eksiksiz düzelt:

/Volumes/TWINMOS/zolm/docs/customer-care/dalga-aeafag-kalite-kapisi-02.md

Yalnız AE/AF/AG Kalite Kapısı 02 kapsamını uygula.
AB/AC/AD kalite kapısı maddelerine dokunma; onlar ayrı revizyon konusudur.
Kapsam dışındaki mevcut kullanıcı değişikliklerine dokunma.
Commit, push veya branch değişikliği yapma.
Yeni dalgaya geçme.

Zorunlu kanıt:
- git diff --check
- AE/AF/AG hedef testleri
- CustomerCare testleri
- full test suite
- npm run build
- güncel dalga-ae/af/ag kanıt paketleri
- güncel walkthrough

Kanıt paketini verdikten sonra dur.
```

