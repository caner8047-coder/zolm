# Faz 0 Kalite Kapısı 01 — Revizyon Gerekli

**Karar:** `REVİZYON GEREKLİ`  
**Faz 1 durumu:** Kapalı  
**İnceleyen:** Codex / Baş mühendis  
**Tarih:** 2026-07-11

## 1. Genel değerlendirme

Antigravity kapsam disiplinine uymuş, yalnız izin verilen raporu oluşturmuş ve çalışma ağacındaki diğer dosyalara dokunmamıştır. Raporun ana yönü doğrudur:

- `support_*` birleşik konuşma/projection çekirdeği için doğru adaydır.
- `marketplace_questions` ve `wa_*` kanal source-of-truth kayıtları korunmalıdır.
- Trendyol support adapter, queue/outbox, tenant izolasyonu, gerçek güven ve marka sesi ana boşluklardır.
- Faz 0 testi bağımsız olarak tekrar çalıştırılmıştır: 10 passed, 1 risky.

Ancak aşağıdaki maddi doğruluk hataları düzeltilmeden rapor mimari source-of-truth kabul edilemez.

## 2. Zorunlu rapor düzeltmeleri

Antigravity yalnız `docs/customer-care/00-mevcut-durum-dogrulama.md` dosyasını aşağıdaki doğrulamalarla güncelleyecektir. Uygulama kodu veya başka doküman değiştirilmeyecektir.

### Düzeltme 1 — WhatsApp Support adapter outbox'a yazmıyor

Raporun adapter tablosunda WhatsApp için “Outbox'a yazar” ifadesi yanlıştır.

Gerçek kod:

- `app/Services/Support/WhatsAppSupportChannelAdapter.php:91`
- Metot yalnız `WaConversation` buluyor.
- `WaOutbox`, `OutboxService::enqueue()` veya job çağrısı yapmıyor.
- Satır 102 doğrudan `success=true` döndürüyor.

Doğru sınıflandırma:

> `placeholder / sahte başarı`: Gerçek outbox kaydı ve Meta gönderimi oluşmadan kuyruğa alındı sonucu döner.

Bu bulgu en kritik bulgular listesine eklenmelidir.

### Düzeltme 2 — Human handoff geri bırakma metodu mevcut

“`releaseHandoff()` bulunamadı” ifadesi eksik ve yanıltıcıdır.

Gerçek kod:

- `app/Services/WhatsApp/HumanHandoffService.php:64`
- `resolve()` handoff kaydını resolved yapıyor.
- Conversation `ai_status=active` durumuna dönüyor ve atamayı kaldırıyor.

Doğru boşluk:

> Fonksiyonel geri bırakma `resolve()` ile mevcut. Eksik olan; açık ownership state machine, yetki/policy kontrolü, concurrency koruması ve “insan çözümü” ile “AI'a geri bırakma” kararlarının ayrı audit edilebilir eylemler olmasıdır.

ZCC-003 matrisi `kısmi` kalabilir ancak gerekçesi düzeltilmelidir.

### Düzeltme 3 — IntegrationConnection credential şifrelemesi doğrulandı

“Credential şifreleme doğrulanamadı” ifadesi yanlıştır.

Gerçek kod:

- `app/Models/IntegrationConnection.php:26`
- `credentials_encrypted => encrypted:array` cast mevcut.

Doğru güvenlik bulgusu:

- `credentials_encrypted` şifreli ✅
- `webhook_secret` için encrypted cast görünmüyor ⚠️
- Model serialization/UI masking ve key rotation ayrıca doğrulanmalıdır.

ZCC-016 ve güvenlik tablosu buna göre güncellenmelidir.

### Düzeltme 4 — Risky testin nedeni performans değildir

`test_whatsapp_raw_payload_not_in_support_message` bir performans testi değildir.

Gerçek neden:

- Test konuşmayı oluşturuyor ancak `WaInboundMessage` oluşturmuyor.
- `fetchMessages()` boş collection döndürüyor.
- `foreach` gövdesi hiç çalışmadığı için sıfır assertion oluşuyor.
- Dolayısıyla test raw payload güvenliğini fiilen kanıtlamıyor.

Test değerlendirmesi buna göre düzeltilmeli ve bu durum test kapsamı boşluğu olarak raporlanmalıdır. Bu revizyon fazında test kodu değiştirilmez.

### Düzeltme 5 — AI provider fiilen FakeAiProvider'a bağlı

Rapor Gemini provider varlığını belirtmiş ancak gerçek dependency injection bağını kaçırmıştır.

Gerçek kod:

- `app/Providers/AppServiceProvider.php:18`
- `AiProviderInterface` koşulsuz olarak `FakeAiProvider` sınıfına bind ediliyor.
- `AiChatService` interface'i inject ediyor; normal container çözümünde Gemini kullanılmıyor.
- `GeminiAiProvider` ayrıca API anahtarı yoksa veya API hata verirse `FakeAiProvider` cevabı döndürüyor.

Bu davranış production için yüksek riskli **fail-open / sahte yanıt** problemidir. Demo/test sağlayıcısı gerçek müşteri cevabı gibi kullanılamaz.

Raporun AI provider, güvenlik, kritik bulgular ve ZCC kapsam bölümlerine şu sonuç eklenmelidir:

> Provider selection ortam/config bazlı ve fail-closed olmalıdır. Production'da gerçek provider başarısızsa taslak başarısız/eskalasyon olmalı; FakeAiProvider yalnız test veya açık demo modunda kullanılmalıdır.

### Düzeltme 6 — `wa_outbox` kanallar arası canonical outbox olamaz

Rapor “Yeni outbox oluşturma, `WaOutbox`ı genişlet” sonucuna kesin karar gibi varmıştır. Bu karar Faz 0 kanıtıyla desteklenmiyor.

Gerçek şema:

- `wa_outbox.contact_id` zorunlu ve `wa_contacts` tablosuna bağlı.
- WhatsApp template, Meta message ID ve WhatsApp otomasyon alanları taşıyor.
- `OutboxService::enqueue()` yalnız aktif WooCommerce mağazasına izin veriyor.

Doğru sonuç:

> `wa_outbox` WhatsApp kanal outbox'ı olarak korunmalıdır. Kanallar arası dispatch için `support_messages` lifecycle + generic dispatch tablosu veya yeni `support_outbox` seçenekleri Faz 1 ADR'sinde değerlendirilmelidir. WhatsApp şeması Trendyol'a zorla genişletilmemelidir.

“Oluşturulmaması gereken tekrar yapılar” ve canonical karar bölümü düzeltilmelidir.

### Düzeltme 7 — Generic AI contractına izin verilmeli

“Yeni WA AI provider oluşturma” doğru olsa da WhatsApp'a özel `App\Services\WhatsApp\AiProviderInterface` bütün CustomerCare domain'inin canonical contractı kabul edilmemelidir.

Doğru karar:

> Mevcut sağlayıcı implementasyonu/adaptörü yeniden kullanılabilir; fakat Faz 1'de kanal bağımsız CustomerCare AI contractı tanımlanabilir. Bu contract mevcut `AIService` ve/veya WhatsApp providerlarını adapter arkasından kullanır. İkinci bir aynı Gemini HTTP implementasyonu yazılmaz.

### Düzeltme 8 — Tenant kararı kesinleşmiş gibi yazılmamalı

`MarketplaceStore + LegalEntity` mevcut operasyonel sahiplik zinciridir; fakat çok kullanıcılı SaaS tenantı olarak canonical olduğu henüz kanıtlanmamıştır.

Doğru sınıflandırma:

- Mevcut sahiplik: `User → LegalEntity → MarketplaceStore`
- Mevcut kayıt partition anahtarı çoğunlukla `store_id`
- Support sorgularında otomatik query scope/policy yok
- `support_channels.store_id` nullable
- Çok kullanıcılı firma üyeliği/RBAC eksik
- Organization gerekip gerekmediği Faz 1 ADR kararıdır

### Düzeltme 9 — Queue driver kararı blocker değildir

Database queue mevcut hacimde otomatik olarak hata sayılmamalıdır. Redis'e geçiş önerisi ancak yük, queue latency, retry hacmi ve operasyon ortamı ölçülerek verilmelidir.

Doğru sonuç:

> Database queue MVP için korunabilir; queue driver seçimi performans/yük testi ve deployment koşullarıyla Faz 3 öncesi veya production sertleştirmesinde karara bağlanır.

### Düzeltme 10 — Çalışma modu “sıfır” değil placeholder

Özette “çalışma modu altyapısı sıfır” denmemelidir. `SupportConversation.ai_mode` ve WhatsApp `ai_status` alanları başlangıç/placeholder altyapıdır. Eksik olan enforce edilen mode state machine, policy, UI, audit ve otomasyon kararıdır.

## 3. Baş mühendis mimari kararları

Faz 0 revizyonunda aşağıdaki kararlar rapora “Codex kalite kapısı kararı” olarak eklenebilir:

1. **`support_*` birleşik conversation/message projection çekirdeği onaylandı.** Yeni paralel `care_conversations` ve `care_messages` oluşturulmayacak.
2. **Kanal source-of-truth kayıtları korunacak.** Trendyol için `MarketplaceQuestion`, WhatsApp için `wa_*` kanoniktir.
3. **MarketplaceQuestion → SupportConversation bağlantısı ilk aşamada idempotent projection olacaktır.** Legacy kayda zorunlu FK veya big-bang taşıma yapılmayacaktır. Deterministic external ID ve source reference kullanılacaktır.
4. **Otomatik cevap için başlangıç güven eşiği şu anda belirlenmeyecek.** Auto-reply kapalı kalacak; eşikler golden dataset ve shadow verisiyle Faz 9–10'da kalibre edilecektir.
5. **KVKK alt işleyici belgesi Faz 1 ADR/dokümantasyonu engellemez**, ancak gerçek müşteri verisinin haricî LLM'e gönderilmesini ve production pilotunu engeller.
6. **Organization kararı Faz 1 ADR çıktısıdır.** Karar verilene kadar mevcut store/user sahipliği yeni SaaS tenant modeli gibi varsayılmayacaktır.
7. **Database queue şimdilik korunabilir.** Redis zorunluluğu ölçümsüz mimari karar değildir.

## 4. Revizyon teslimatı

Antigravity yalnız şu dosyayı güncelleyecektir:

```text
docs/customer-care/00-mevcut-durum-dogrulama.md
```

Teslimatta:

- Yukarıdaki 10 düzeltmenin raporda hangi bölümlere işlendiği
- Güncellenen en kritik bulgular
- Güncellenen ZCC matrisi
- `git status --short`
- Uygulama koduna dokunulmadığı teyidi

verilecek ve durulacaktır. Faz 1'e geçilmeyecektir.
