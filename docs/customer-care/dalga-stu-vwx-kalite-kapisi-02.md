# ZOLM AI Müşteri İletişim Merkezi — Dalga S/T/U + V/W/X Kalite Kapısı 02

**Tarih:** 2026-07-13  
**İnceleyen:** Codex — Baş mühendis  
**Karar:** ❌ **Kabul edilmedi — tek P0 revizyon gerekli**  

Bu kalite kapısı, `docs/customer-care/dalga-stu-vwx-kalite-kapisi-01.md` sonrası yapılan revizyonların bağımsız baş mühendis kontrolüdür.

Önceki P0/P1 maddelerinin büyük kısmı doğru kapanmış görünüyor:

- Meta Social outbound artık gerçek `MetaSocialConnectorInterface` yoksa fail-closed çalışıyor.
- Google Business Profile reply artık gerçek `GoogleBusinessConnectorInterface` yoksa fail-closed çalışıyor.
- Web Chat offline outbound generic `SupportOutboxService::sendDispatch()` yolunda `queued` kalıyor; sahte `sent` yazılmıyor.
- Circuit breaker pending AI dispatch iptalinde bağlı `SupportMessage.delivery_status` da `cancelled` oluyor ve `circuit_breaker_cancel` audit izi yazılıyor.

Ancak Web Chat HMAC doğrulama akışında canlı inbound güvenliğini etkileyen bir P0 boşluk kaldı.

---

## Çalıştırılan Kontroller

```bash
git diff --check
./vendor/bin/sail artisan test \
  tests/Feature/CustomerCare/WebChatSupportChannelAdapterTest.php \
  tests/Feature/CustomerCare/MetaSocialSupportChannelAdapterTest.php \
  tests/Feature/CustomerCare/GoogleBusinessSupportChannelAdapterTest.php \
  tests/Feature/CustomerCare/CanaryCircuitBreakerTest.php \
  --no-coverage --compact
```

Sonuç:

- `git diff --check`: ✅ temiz
- Hedef testler: ✅ `40 passed / 115 assertions`

Not: Testlerin yeşil olması olumlu; fakat aşağıdaki P0 senaryo mevcut test setinde kapsanmıyor.

---

## P0-1 — Web Chat HMAC imzası doğrulanıyor ama imzalanan veri kullanılmıyor

**Dosya:** `app/Services/Support/WebChatSupportChannelAdapter.php`

`projectMessage()` içinde `raw_json` ve `signature` zorunlu tutulmuş; bu doğru yönde. Ancak imza doğrulandıktan sonra `raw_json` decode edilip kanonik veri kaynağı olarak kullanılmıyor. Kod imza sonrası hâlâ dış `$payload` alanlarını kullanıyor:

- `store_id`
- `idempotency_key`
- `session_id`
- `body`
- `is_online`

İlgili akış:

- `raw_json` ve `signature` okunuyor.
- `verifySignature($rawJson, $signature, $connection->webhook_secret)` çağrılıyor.
- Sonra `idempotency_key`, `session_id`, `body`, `is_online` değerleri imzalanmış `raw_json` yerine dış `$payload` array’inden alınıyor.

Bu şu bypass ihtimalini doğurur:

1. Saldırgan veya hatalı client masum bir `raw_json` üretir ve doğru imza ekler.
2. Aynı request içinde dış payload alanlarına farklı `body`, `session_id` veya `idempotency_key` koyar.
3. Adapter imzayı masum `raw_json` üzerinde doğrular ama projection’da imzalanmamış dış alanları kullanır.

Bu durumda HMAC, mesaj bütünlüğünü gerçek anlamda korumaz.

### Beklenen düzeltme

`projectMessage()` imza doğrulamasından sonra kanonik payload’ı sadece imzalanmış `raw_json` içinden üretmelidir.

Önerilen güvenli akış:

1. `raw_json` string alınır.
2. `signature` doğrulanır.
3. `json_decode($rawJson, true)` yapılır.
4. Decode başarısızsa fail-closed.
5. Projection için kullanılacak `store_id`, `idempotency_key`, `session_id`, `body`, `is_online` gibi tüm iş alanları decoded signed payload’dan okunur.
6. Dış `$payload` array’i yalnız taşıyıcı alanlar (`raw_json`, `signature`) için kullanılır.
7. `raw_json` içindeki `store_id` ile `$channel->store_id` eşleşmezse fail-closed.

Alternatif olarak public endpoint request’i daha yukarı katmanda signed payload’a normalize edebilir; fakat adapter seviyesinde de bu invariant testle korunmalıdır.

### Zorunlu test

`WebChatSupportChannelAdapterTest` içine şu davranışı kanıtlayan test eklenmeli:

```text
Geçerli imza, raw_json içindeki A payload’ına ait.
Dış payload içinde body/session_id/idempotency_key B olarak manipüle edilmiş.
projectMessage() ya fail-closed döner ya da yalnız imzalanmış A payload’ını projelendirir.
Asla dış B alanlarını support_messages / support_conversations içine yazmaz.
```

Örnek test adı:

```php
test_project_message_uses_signed_raw_json_not_outer_payload_fields
```

Test kabul kriterleri:

- İmzalı `raw_json.body = "İmzalı mesaj"` iken dış `$payload['body'] = "Manipüle mesaj"` ise veritabanına `"Manipüle mesaj"` yazılmamalı.
- İmzalı `raw_json.session_id` dış session’dan farklıysa conversation hash imzalı session’dan türemeli veya request fail-closed kapanmalı.
- İmzalı `raw_json.idempotency_key` dış idempotency’den farklıysa external message id dış alandan türememeli.
- `raw_json.store_id` channel store ile eşleşmiyorsa fail-closed.

---

## Kabul İçin Yeterli Olacak Kanıtlar

Antigravity revizyon sonrası aşağıdaki kanıtları sunmalı:

```bash
git diff --check
./vendor/bin/sail artisan test tests/Feature/CustomerCare/WebChatSupportChannelAdapterTest.php --no-coverage --compact
./vendor/bin/sail artisan test tests/Feature/CustomerCare --no-coverage --compact
npm run build
```

Ek olarak kısa raporda şu dosya/davranışlar açıkça belirtilmeli:

- `WebChatSupportChannelAdapter::projectMessage()` artık signed `raw_json` içeriğini kanonik payload olarak kullanıyor.
- Dış `$payload` alanları imza sonrası iş verisi olarak kullanılmıyor.
- Yeni tamper testinin adı ve sonucu.

---

## Antigravity’ye Verilecek Revizyon Komutu

```text
/Volumes/TWINMOS/zolm reposunda Dalga S/T/U + V/W/X Kalite Kapısı 02 revizyonunu uygula.

Önce şu dosyayı tamamen oku ve içindeki tek P0 maddeyi eksiksiz düzelt:

/Volumes/TWINMOS/zolm/docs/customer-care/dalga-stu-vwx-kalite-kapisi-02.md

Kurallar:
- Commit, push veya branch değişikliği yapma.
- Kapsam dışındaki mevcut kullanıcı değişikliklerine dokunma.
- Feature flag varsayılanlarını kapalı tut.
- Otomatik yanıtı global olarak açma.
- Testlerde canlı dış API çağrısı yapma.
- Web Chat projectMessage imza doğrulamasından sonra iş verisini sadece imzalanmış raw_json içinden okusun.
- Dış payload alanları imzalanmış raw_json ile çelişirse dış alanlar kullanılmasın; güvenli tercih fail-closed veya signed raw_json kanonik kabulüdür.
- Test, build ve git kanıt paketini verdikten sonra dur.
```

---

## Sonuç

Dalga S/T/U + V/W/X revizyonları kabul seviyesine çok yaklaşmıştır; ancak Web Chat signed payload bütünlüğü kapanmadan public widget inbound kanalı pilot/canlı kabul alamaz.

Bu P0 düzeltme tamamlanıp testle kanıtlandığında, kapı tekrar değerlendirilecek ve kabul kararı verilebilecektir.
