# ZOLM AI Müşteri İletişim Merkezi — Dalga A/B/C Kabul Kararı

Tarih: 2026-07-11  
Karar sahibi: Codex baş mühendis kontrolü  
Kapsam: Dalga A/B/C Revizyon 02 kanıt paketi sonrası altyapı kalite kapısı

## Karar

**Dalga A/B/C altyapı kapsamı kabul edildi.**

Bu kabul; support projection, generic outbox, kanal adapter sınırları, temel tenant güvenliği, state machine, feature flag kapıları, route/scheduler entegrasyonu ve başlangıç AI ledger şemasının geliştirme dalında ilerlemesi için verilmiştir.

Bu kabul **production otomatik cevap açılışı**, **müşteri pilotu**, **AI automatic mode canlı kullanımı** veya **KVKK/hukuk onayı** anlamına gelmez.

## Doğrulanan kanıtlar

- Customer Care, WhatsApp Support ve MarketplaceQuestions regresyon paketleri çalıştırıldı.
- Sonuç: **58 passed, 1 risky, 219 assertions**.
- Risky test mevcut Faz 0 bulgusudur: `whatsapp raw payload not in support message` testi assertion üretmemektedir.
- `git diff --check` temizdir.
- `customer-care` route'u mevcuttur.
- `support-process-outbox` ve `whatsapp-process-outbox` scheduler kayıtları mevcuttur.
- `2026_07_26_*` migration'ları uygulanmış durumdadır.
- Veritabanı seviyesinde `support_dispatch_attempts.support_dispatch_id` ilişkisi **ON DELETE RESTRICT** olarak doğrulanmıştır.

## Kabul edilen ana iyileştirmeler

1. `support_dispatches` ve `support_dispatch_attempts` ile generic outbound dispatch çekirdeği oluştu.
2. Outbox claim/retry/final-state davranışları testlerle güvence altına alındı.
3. Trendyol ve WhatsApp adapter tarafında cross-tenant IDOR kontrolleri güçlendirildi.
4. Human ownership, lifecycle ve AI mode ayrımı daha net hale getirildi.
5. Feature flag ve kill-switch kapıları default kapalı/fail-closed çalışıyor.
6. `support_ai_runs` tablo/model başlangıcı eklendi.
7. Knowledge base ve brand voice servislerinde temel tenant sınırı, sanitization ve uzunluk limiti eklendi.
8. ADR-007 ile shadow/golden eval/AI ledger yolu belgelendi.

## Pilot öncesi açık şartlar

Aşağıdaki maddeler Dalga A/B/C kabulünü bozmaz; ancak **pilot veya production otomatik cevap açılışından önce çözülmelidir**.

### 1. System actor sözleşmesi üretim için sertleştirilmeli

`TenantContext::getSystemActor()` şu an sırasıyla `system@zolm.com`, admin kullanıcı, ilk kullanıcı ve son çare olarak factory ile kullanıcı oluşturma yoluna gidiyor.

Bu geliştirme akışında deterministik bir başlangıç sağlıyor; fakat production için yeterli değil. Factory kullanımı production servis kodunda kalmamalı, ilk kullanıcı fallback'i tenant/audit açısından açık bırakılmamalıdır.

Beklenen pilot öncesi karar:

- System actor açıkça provision edilmeli.
- Yoksa fail-closed davranmalı.
- Actor kimliği config veya migration/seeder ile yönetilmeli.
- Background worker akışında bu actor'ın tenant erişim modeli ayrıca test edilmelidir.

### 2. AI ledger henüz AI akışına tam bağlı değil

`support_ai_runs` migration ve model olarak oluşturuldu; ancak mevcut aramada AI karar akışında `SupportAiRun` yazımı görünmüyor.

Beklenen pilot öncesi karar:

- Her AI taslak/cevap denemesi `support_ai_runs` içine append-only yazılmalı.
- Model adı, prompt template, kaynaklar, confidence, token/latency ve hata durumu kaydedilmeli.
- Otomatik cevap sadece bu ledger ve golden eval kapıları geçtikten sonra açılmalı.

### 3. Ledger retention ve cascade politikası netleşmeli

`support_dispatch_attempts` tarafında `ON DELETE RESTRICT` doğru uygulanmış durumda. Buna karşılık:

- `support_dispatches` parent ilişkileri hâlâ cascade kullanıyor.
- `support_ai_runs.store_id` ve `support_ai_runs.conversation_id` ilişkileri cascade kullanıyor.

Bu, denetim geçmişi ve KVKK silme/anonymization dengesi için ayrıca karar gerektirir.

Beklenen pilot öncesi karar:

- Denetim kayıtları silinmeyecek mi, anonimleştirilecek mi?
- Store/conversation silme girişimleri ledger kayıtları varken fail mi edecek, soft-delete/anonymize mi uygulanacak?
- Bu davranış testle sabitlenmelidir.

### 4. Knowledge/Brand Voice güvenliği testle genişletilmeli

Temel `strip_tags`, uzunluk limiti ve keyword tabanlı prompt-injection engeli var. Ancak Türkçe enjeksiyon cümleleri, kaynak doğrulama, durable audit ve negatif test kapsamı hâlâ başlangıç seviyesinde.

Beklenen pilot öncesi karar:

- Türkçe ve karma dilli prompt-injection örnekleri eklenmeli.
- Marka sesi değişiklikleri sadece log değil, tercihen domain audit kaydı olarak izlenmeli.
- Knowledge source freshness/version bilgisi AI grounding tarafına bağlanmalı.

## Faz kapanış notu

Dalga A/B/C, **altyapı ve güvenlik iskeleti açısından kabul edildi**.

Sonraki doğru adım, tüm fazları körlemesine büyütmek değil; kısa ve kontrollü bir **Pilot Öncesi Sertleştirme Dalgası** açmaktır. Bu dalga yalnız yukarıdaki dört şartı, mevcut risky testi ve ledger/actor güvenliğini hedeflemelidir.

