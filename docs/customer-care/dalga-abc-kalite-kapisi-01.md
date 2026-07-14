# ZOLM AI Müşteri İletişim Merkezi — Dalga A/B/C Kalite Kapısı 01

## Karar

**RED — PİLOT HAZIR DEĞİL**

Teslim edilen kod, kavram kanıtı seviyesinde faydalı bir başlangıçtır; ancak tenant izolasyonu, gerçek kanal teslimatı, outbox concurrency/retry ve AI doğruluk güvenliği production veya kontrollü pilot seviyesinde değildir. Mevcut feature flag'ler kapalı tutulmalı ve bu kod üzerinden gerçek müşteriye mesaj gönderilmemelidir.

Test paketi geçmektedir; fakat testler aşağıdaki gerçek entegrasyon ve güvenlik hatalarını kapsamamaktadır.

## P0 — Pilot Öncesi Zorunlu Blokerler

### 1. WhatsApp gerçek gönderimi şema ile uyumsuz

`WhatsAppSupportChannelAdapter::sendReply()` yeni `WaOutbox` kaydında zorunlu ve unique `idempotency_key` alanını yazmamaktadır. `wa_outbox.idempotency_key` nullable değildir. Gerçek adapter çağrısı SQL hatasına düşer.

Ek olarak generic dispatch, WhatsApp mesajını yalnız `wa_outbox` kuyruğuna bıraktığı anda `sent` saymaktadır. Bu durum teslimat semantiğini bozar. Generic durum `queued/accepted` olmalı; `sent/delivered/read` statüleri WhatsApp delivery olaylarından yansıtılmalıdır.

Zorunlu düzeltme:

- Deterministic idempotency key generic dispatch'ten WhatsApp adapter'ına taşınmalı.
- Aynı dispatch'in yeniden çalışması ikinci `wa_outbox` kaydı üretmemeli.
- Gerçek `WaOutbox` modeli ve MySQL şemasıyla entegrasyon testi yazılmalı.
- Generic dispatch ile kanal outbox statü eşlemesi açıkça modellenmeli.

### 2. Outbox çift gönderime açık ve maksimum deneme limiti çalışmıyor

`sendDispatch()` kaydı koşulsuz `sending` yapmaktadır. İki worker aynı dispatch'i okuyup aynı mesajı iki kez gönderebilir. `processPendingDispatches()` lock/claim yapmadan tüm kayıtları belleğe almaktadır.

Beşinci hatada `status=failed, retry_at=null` yazılmaktadır; seçim sorgusu `failed` ve `retry_at IS NULL` kayıtlarını tekrar aldığı için terminal hata sonsuza dek yeniden denenir.

Zorunlu düzeltme:

- Atomik claim veya `FOR UPDATE SKIP LOCKED`/eşdeğer güvenli worker deseni.
- Terminal durum ayrı olmalı (`exhausted`/`dead`) veya sorgu `attempt_count < max_attempts` şartı taşımalı.
- Stale `sending` recovery politikası bulunmalı.
- Queue job/command ve scheduler/worker bağlantısı gerçek olarak eklenmeli; şu anda `processPendingDispatches()` hiçbir production akışından çağrılmıyor.
- İki worker concurrency, terminal retry ve stale recovery testleri eklenmeli.

### 3. TenantContext uygulanmıyor; yalnız yardımcı metot olarak duruyor

Tenant testleri sadece `TenantContext` metodunun kendi sonucunu sınamaktadır. Buna karşın gerçek okuma/yazma yolları bu context'i kullanmamaktadır:

- Customer Care Home tüm tenant'lardaki kanal ve dispatch sayılarını gösterir.
- `SupportReplyService` conversation erişimini ve verilen `userId` yetkisini doğrulamaz.
- `KnowledgeBaseService` çağıranın store erişimini doğrulamaz.
- `BrandVoiceService` başka tenant kanalını okuyup güncelleyebilir.
- Conversation claim/release/resolve metotlarında policy, tenant ve rol kontrolü yoktur.
- Outbound adapter'lar channel/store/source kaydının birbirine ait olduğunu doğrulamaz.

Zorunlu düzeltme:

- Tek bir request/job `TenantContext` tasarımı ve açık tenant/store scope sözleşmesi.
- UI, servis, policy ve job sorgularının tamamında scope enforcement.
- IDOR testleri: başka tenant konuşmasına yanıt, marka sesi güncelleme, bilgi arama, sayaç görüntüleme, claim/release ve dispatch erişimi.
- Background job payload'ında tenant kimliği ve çalıştırma anında yeniden doğrulama.

### 4. Trendyol adapter'ında cross-tenant/source doğrulaması yok

Adapter external ID içinden yerel question ID çıkarıp global `MarketplaceQuestion::find()` çağırmaktadır. Bulunan sorunun gönderilen `SupportChannel`, conversation ve store ile ilişkisi doğrulanmamaktadır. Yanlış veya manipüle edilmiş external ID başka mağazanın sorusuna yanıt gönderebilir.

Zorunlu düzeltme:

- Question lookup en az `store_id = channel.store_id` ve projection source reference ile sınırlandırılmalı.
- External ID parse işlemi tam eşleşmeli ve canonical source reference kullanılmalı.
- Marketplace answer servisine gerçek yetkili kullanıcı/source bilgisi aktarılmalı.
- Cross-tenant ve yanlış source ID entegrasyon testleri yazılmalı.

### 5. Human ownership kilidi outbound gönderimde enforce edilmiyor

State alanları eklenmiştir; fakat `SupportReplyService`, AI veya kanal gönderim yolunda ownership/lifecycle/automation mode kontrolü bulunmamaktadır. Model metotları herkes tarafından çağrılabilir, audit üretmez ve policy uygulamaz.

Zorunlu düzeltme:

- Claim/release/resolve/reopen işlemleri domain service + policy üzerinden yapılmalı.
- Human ownership kilidi AI taslak/gönderim kapısında atomik biçimde tekrar kontrol edilmeli.
- Closed/resolved conversation'a gönderim kuralları tanımlanmalı.
- Her geçiş auditli olmalı; yetkisiz ve concurrency negatif testleri eklenmeli.

### 6. Feature flag'ler gerçek gönderim yollarını korumuyor

Config'te bayraklar vardır ancak `SupportReplyService`, AI provider binding ve adapter gönderimleri master/auto-reply/demo/pilot bayraklarıyla korunmamaktadır. Route'un kapalı olması servislerin başka kodlardan çağrılmasını engellemez.

Zorunlu düzeltme:

- Master kill switch ve kanal bazlı outbound kill switch servis katmanında enforce edilmeli.
- AI otomatik gönderim, copilot ve manuel gönderim birbirinden ayrılmalı.
- Pilot allowlist/store allowlist olmadan gerçek gönderim yapılamamalı.
- Bayrak kapalıyken hiçbir haricî API/outbox yan etkisi olmadığını kanıtlayan testler eklenmeli.

## P1 — Mimari ve Ürün Blokerleri

### 7. AI entegrasyonu güven skoru veya kaynak doğrulaması yapmıyor

Gemini adapter yalnız son mesajı göndermekte; katalog, sipariş, konuşma geçmişi, bilgi merkezi veya marka sesiyle gerçek orkestrasyon yapmamaktadır. Başarılı her yanıta sabit `85` güven ve sahte bir `Gemini AI Core` kaynağı verilmektedir. Structured output doğrulaması, boş cevap kontrolü, safety sonucu, kaynak-grounding ve `support_ai_runs` ledger yoktur.

Bu yapı ZCC-001/002/003/007 gereksinimlerini karşılamaz ve otomatik yanıta uygun değildir.

Zorunlu düzeltme:

- Provider adapter ile orchestration/risk engine ayrılmalı.
- Typed structured output schema doğrulanmalı.
- Güven değeri modelin kendi beyanı olmamalı; kaynak kapsamı, veri tazeliği, policy ve intent risk sinyallerinden hesaplanmalı.
- Kaynaksız ürün/fiyat/stok/sipariş iddiası engellenmeli.
- `support_ai_runs`, input/source hash, model, token/maliyet, latency, karar ve hata ledger'ı eklenmeli.
- Golden dataset ve shadow-mode değerlendirmesi olmadan automatic mode açılamamalı.

### 8. Demo fallback production ortamında açılabilir

Kod `demo_mode=true` olduğunda environment kontrolü yapmadan Fake adapter'a düşmektedir. ADR'deki “yalnız local/test” şartı uygulanmamıştır.

Zorunlu düzeltme:

- Fake provider yalnız explicit provider seçimi + `app()->environment(['local','testing'])` birlikte sağlanınca kullanılmalı.
- Production'da demo flag açılsa bile fail-closed testi bulunmalı.

### 9. Bilgi merkezi ve marka sesi yalnız placeholder seviyesinde

Bilgi merkezi WhatsApp tablosunda basit LIKE aramasıdır; çağıran yetkilendirmesi, sürüm/onay/source freshness, chunk/relevance ve prompt injection güvenliği yoktur. Marka sesi girdileri doğrulanmadan kanal config'ine yazılmaktadır.

Zorunlu düzeltme:

- Tenant-authorized API/service boundary.
- Validasyon, uzunluk limitleri, audit ve prompt-injection sınırları.
- Kaynak sürümü, published state ve cevapta kullanılan exact source kaydı.
- ADR-005 kararı uygulanmadan “generic bilgi merkezi tamamlandı” denmemeli.

### 10. Projection yaşam döngüsü eksik

Yalnız manuel servis çağrısı vardır; event/job/backfill/cursor ve hata recovery akışı yoktur. Projection, assigned user yazarken ownership durumunu uyumlu güncellemez. Source reference bütünlüğü ve silme/güncelleme davranışları tanımlı değildir.

Zorunlu düzeltme:

- Event/job/backfill ve idempotent recovery tasarımı.
- Source reference için unique constraint veya eşdeğer DB garantisi.
- Ownership/lifecycle eşleme kuralları.
- Güncelleme, cevap, silinme ve tekrar işleme testleri.

### 11. Audit verileri cascade delete ile kaybolabilir

`support_dispatch_attempts` append-only audit olarak tanımlanmasına rağmen dispatch silindiğinde cascade ile silinmektedir; conversation/message/channel silinmesi de dispatch'i zincirleme silebilir. Bu, ADR'deki audit hedefiyle çelişir.

Zorunlu düzeltme:

- KVKK saklama/silme politikasıyla uyumlu audit retention kararı.
- PII payload/error redaksiyonu.
- Cascade yerine restrict/anonymize/archive seçeneklerinin karara bağlanması.

## Test Kanıtı ve Yanlış Güven Problemi

Bağımsız çalıştırma:

```text
CustomerCare + WhatsApp SupportChannel:
30 passed, 1 risky, 103 assertions
```

Bu sonuç kabul için yeterli değildir; çünkü test doubles gerçek adapter ve gerçek şema uyumsuzluğunu saklamaktadır. Eksik olan başlıca testler:

- Gerçek WhatsApp adapter + gerçek `wa_outbox` şeması.
- Gerçek Trendyol adapter store/source izolasyonu.
- İki worker outbox concurrency.
- Beşinci deneme sonrası terminal durum.
- Tenant scoped UI ve tüm mutation endpoint/service yolları.
- Kill switch kapalıyken sıfır dış yan etki.
- Production demo/fake provider yasağı.
- Golden dataset, hallucination ve source-grounding ölçümü.

## Kapsam Kararı

- Mevcut Customer Care feature flag'leri `false` kalacaktır.
- Gerçek kanal gönderimi pilotta açılmayacaktır.
- “Dalga A/B/C tamamlandı” ve “pilot hazır” ifadeleri geri çekilecektir.
- Mevcut çalışma **prototip altyapı** olarak kabul edilebilir; production/pilot kabulü verilmemiştir.
- Düzeltmeler tek seferde uygulanabilir; ancak Antigravity P0 maddeleri tamamlanmadan P1 veya yeni kanal geliştirmesine geçmemelidir.

## Antigravity Teslimat Kanıtı

Revizyon sonunda şu kanıtlar zorunludur:

1. Her P0/P1 maddesi için dosya ve test eşlemesi.
2. Tam `git status --short` ve untracked dosyalar dahil diff stat.
3. Migration fresh testi ve rollback testi.
4. Customer Care, WhatsApp ve MarketplaceQuestion regresyon paketleri.
5. Gerçek adapter entegrasyon testleri; yalnız mock adapter yeterli değildir.
6. Concurrency/retry terminal testleri.
7. Cross-tenant IDOR negatif test matrisi.
8. Feature flag/kill switch yan etkisizlik testleri.
9. AI golden dataset ve shadow-mode raporu.
10. Build, `git diff --check`, route ve scheduler/queue kanıtı.

Commit, push ve branch değişikliği yapılmayacaktır. Kapsam dışı kullanıcı değişiklikleri korunacaktır.
