# ZOLM AI Müşteri İletişim Merkezi — Dalga A/B/C Kalite Kapısı 02

## Karar

**RED — PİLOT HAZIR DEĞİL**

Dalga A/B/C Kalite Kapısı 01 sonrası yapılan revizyonlar faydalı ilerleme sağlamıştır: WhatsApp `wa_outbox.idempotency_key` hatası giderilmiş, generic dispatch komutu ve scheduler eklenmiş, temel tenant/policy servisleri yazılmış ve test paketi genişletilmiştir.

Ancak gerçek müşteri mesajı göndermeye izin vermek için gereken güvenlik ve teslimat garantileri hâlâ tamamlanmamıştır. Feature flag'ler kapalı kalmalı, otomatik yanıt ve gerçek kanal gönderimi pilotta açılmamalıdır.

Bağımsız doğrulama:

```text
./vendor/bin/sail artisan test tests/Feature/CustomerCare tests/Feature/WhatsApp/SupportChannelTest.php --no-coverage

Tests: 1 risky, 41 passed (145 assertions)
```

Ek doğrulamalar:

```text
git diff --check
# CLEAN

./vendor/bin/sail artisan route:list --name=customer-care --env=testing
# GET|HEAD customer-care customer-care.home

./vendor/bin/sail artisan schedule:list
# support-process-outbox her dakika listeleniyor
```

## P0 — Kalan Pilot Blokerleri

### 1. WhatsApp adapter hâlâ cross-tenant gönderime açık

`WhatsAppSupportChannelAdapter::sendReply()` `wa_` prefix'ini silip `WaConversation::find($waConvId)` ile global kayıt açıyor. Bulunan WhatsApp konuşmasının `store_id` değeri gönderilen `SupportChannel::store_id` ile karşılaştırılmıyor.

Risk:

- Manipüle edilmiş `external_conversation_id` ile başka mağazaya ait `wa_conversations` kaydına `wa_outbox` yazılabilir.
- `fetchMessages()` ve `resolveOrderContext()` de aynı global ID yaklaşımını kullanıyor.

Zorunlu düzeltme:

- `conversationExternalId` tam regex ile parse edilmeli: `^wa_(\d+)$`.
- `WaConversation::whereKey($id)->where('store_id', $channel->store_id)` kullanılmalı.
- `contact.store_id` ile `conversation.store_id` tutarlılığı doğrulanmalı.
- Cross-tenant WhatsApp negatif testi eklenmeli: Store A kanalıyla Store B `wa_conversation` ID'sine gönderim sıfır `WaOutbox` üretmeli.

### 2. Outbox atomic claim hâlâ yarış penceresi taşıyor

`processPendingDispatches()` önce candidate ID'leri seçiyor, sonra claim sırasında yalnız `id` ve `status in (pending, failed)` koşulu ile `sending` yapıyor. Claim sorgusunda `retry_at <= now`, `attempt_count < max` ve stale olmayan kayıt koşulları tekrar edilmediği için şu senaryo mümkün:

1. Worker A ve Worker B aynı failed/ready kaydı candidate listesine alır.
2. Worker A claim eder, gönderir, hata alır ve `failed + retry_at=future` yazar.
3. Worker B candidate ID ile gelir, kayıt tekrar `failed` olduğu için claim eder ve backoff beklemeden ikinci gönderimi yapar.

Risk:

- Aynı dispatch kısa aralıkta birden fazla kez harici kanala gönderilebilir.
- Retry backoff semantiği kırılır.

Zorunlu düzeltme:

- Candidate seçimi ve claim tek atomik işlem olmalı veya claim update aynı uygunluk koşullarını tekrar etmelidir: status, retry_at, attempt_count, updated_at/stale policy.
- `sendDispatch()` doğrudan çağrıldığında da terminal/sent/accepted durumları yeniden gönderilmemeli.
- Test gerçek servis akışını simüle etmeli; yalnız iki ardışık `update(['status'=>'sending'])` testi yeterli değil.

### 3. Background/job tenant doğrulaması eksik

`KnowledgeBaseService` ve `BrandVoiceService` sadece `auth()->user()` varsa tenant kontrolü yapıyor. CLI/job/worker veya servis katmanından user olmadan çağrıldığında store/channel erişimi doğrulanmadan çalışıyor.

`SupportOutboxService` worker içinde dispatch'in `message -> conversation -> channel -> store` bütünlüğünü tekrar doğrulamıyor. `conversation_id`, `message_id` ve `support_channel_id` çapraz tenant kayıtlarla yaratılırsa worker bunu göndermeye çalışabilir.

Zorunlu düzeltme:

- Servis metotları açık actor veya tenant context almalı; user yoksa sessiz geçmemeli.
- Worker dispatch sırasında `dispatch.support_channel_id === conversation.support_channel_id`, `conversation.store_id === channel.store_id`, `message.conversation_id === conversation.id` doğrulamalı.
- Background job IDOR negatif testleri eklenmeli.

### 4. Trendyol adapter yetkili kullanıcı bağlamı taşımıyor

Trendyol store kontrolü eklenmiş; bu iyi. Fakat `sendReply()` içinde `auth()->user()` kullanılıyor. Scheduler/worker akışında `auth()` boş olacağı için marketplace answer servisinin audit/yetki bağlamı belirsizleşiyor.

Zorunlu düzeltme:

- Dispatch payload'ında `actor_user_id` veya açık sistem aktörü kararı bulunmalı.
- `MarketplaceQuestionAnswerService::sendAnswer()` için worker-safe actor/audit sözleşmesi netleşmeli.
- Auth'suz worker testinde yanıt gönderiminin nasıl auditleneceği kanıtlanmalı.

### 5. Human ownership ve çalışma modu yalnız kısmi enforce ediliyor

AI mesajı human-owned konuşmada bloklanıyor; bu doğru başlangıç. Ancak `ai_mode/manual/copilot/automatic` gönderim kapısında kullanılmıyor. `SupportReplyService` resolved/closed konuşmayı otomatik `open` yapıyor; bunun policy/domain kararı yok.

Zorunlu düzeltme:

- Manual, copilot ve automatic için outbound karar matrisi uygulanmalı.
- Closed/resolved konuşmaya gönderim kuralları domain service içinde açık olmalı.
- AI auto-send kapısı `customer-care.auto_reply_enabled`, conversation mode ve ownership'i birlikte kontrol etmeli.

## P1 — Kalan Ürün/Mimari Eksikler

### 6. AI hâlâ üretim cevabı için güvenilir değil

Gemini adapter yalnız son mesajı gönderiyor, başarılı her cevaba sabit `85` confidence ve `Gemini AI Core` kaynağı veriyor. Katalog, sipariş, bilgi merkezi, marka sesi, policy, structured output, boş cevap kontrolü ve `support_ai_runs` ledger yok.

Bu nedenle ZCC-001/002/003/007 tamamlanmış sayılmaz. Auto reply kesinlikle kapalı kalmalıdır.

### 7. Knowledge base ve brand voice placeholder seviyesinde

Bilgi arama basit `LIKE` sorgusu. Marka sesi güncellemesinde validasyon, uzunluk limiti, audit ve prompt-injection sınırı yok. Bunlar kontrollü pilot öncesi ya tamamlanmalı ya da UI'da "hazır" gibi gösterilmemeli.

### 8. Projection yaşam döngüsü hâlâ manuel

`SupportProjectionService` idempotent projection için başlangıç sunuyor; ancak event/job/backfill/cursor/recovery, source reference DB garantisi ve ownership/status eşleme kuralları tamamlanmadı.

### 9. Audit retention hâlâ cascade delete ile çelişiyor

`support_dispatch_attempts` append-only audit olarak hedefleniyor; migration'da dispatch silinirse cascade ile attempt kayıtları da siliniyor. KVKK/retention kararı netleşmeden pilot kabulü verilmemeli.

### 10. UI dili fazla iddialı

Minimal Customer Care ekranı "güvenlik doğrulamaları başarıyla tamamlanmıştır" diyor. Bu ifade mevcut kalite kapısı kararıyla uyumsuz. UI metni "hazırlık ve doğrulama devam ediyor" seviyesine çekilmeli.

## Antigravity İçin Revizyon Talimatı

Şu maddeler tamamlanmadan pilot kabulü isteme:

1. WhatsApp adapter store-bound lookup ve cross-tenant negatif testleri.
2. Outbox claim yarış penceresini kapatan gerçek atomic claim ve yeniden gönderim koruması.
3. Worker/job tenant bütünlük doğrulamaları.
4. Trendyol worker actor/audit sözleşmesi.
5. Mode/ownership/auto-reply karar matrisi.
6. AI için en azından shadow/golden dataset ve `support_ai_runs` ledger ADR/uygulama kararı.
7. Knowledge/brand voice validasyon ve audit sınırı.
8. Projection job/backfill/cursor veya bu kapsamın pilot dışı olduğuna dair açık karar.
9. Audit retention migration kararının netleştirilmesi.
10. UI metninin gerçek durumla uyumlu hale getirilmesi.

Kanıt paketi tekrar şu çıktıları içermelidir:

- `git status --short`
- untracked dosyalar dahil diff stat
- CustomerCare + WhatsApp + MarketplaceQuestion testleri
- yeni IDOR ve concurrency testleri
- `git diff --check`
- `route:list`
- `schedule:list`
- migration rollback/fresh kanıtı

Commit, push ve branch değişikliği yapılmayacaktır.
