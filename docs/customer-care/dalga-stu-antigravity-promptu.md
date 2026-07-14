# ZOLM AI Müşteri İletişim Merkezi — Dalga S/T/U Antigravity Uygulama Promptu

**Tarih:** 2026-07-12  
**Hazırlayan:** Codex — Baş mühendis planı  
**Ön koşul:** Dalga P/Q/R uygulaması tamamlandıktan sonra çalıştırılmalıdır.  
**Kapsam:** Dalga S, T, U uygulanacak; sonraki dalgalara geçilmeyecek.

---

## 0. Genel Talimatlar

/Volumes/TWINMOS/zolm reposunda çalış.

Önce şu dosyaları oku:

- `docs/customer-care/dalga-pqr-antigravity-promptu.md`
- `docs/customer-care/dalga-mno-kabul-karari.md`
- `docs/customer-care/dalga-jkl-kabul-karari.md`
- `docs/customer-care/adr/003-generic-outbound-dispatch.md`
- `docs/customer-care/adr/007-ai-shadow-golden-eval-ledger.md`
- `AGENTS.md`

Kurallar:

- Commit, push veya branch değişikliği yapma.
- Kapsam dışındaki kullanıcı değişikliklerine dokunma.
- Feature flag varsayılanlarını kapalı tut.
- Otomatik yanıtı global olarak açma.
- Testlerde canlı dış API çağrısı yapma.
- Var olmayan API endpoint’i veya credential davranışı uydurma.
- Unsupported kanallarda sahte başarı dönme; fail-closed çalış.
- Yeni UI varsa ZOLM Kurumsal Açık Panel Sistemi ve mobil responsive kurallarına uy.
- Dalga V/W/X veya başka kapsama geçme.

---

# Dalga S — WhatsApp Customer Care Production Bridge

## Amaç

Mevcut WhatsApp modülünü Müşteri İletişim Merkezi çekirdeğine production-grade bağlamak. WhatsApp zaten repo içinde güçlü bir modül olarak var; bu dalga, WhatsApp’ı ayrı otomasyon adası olmaktan çıkarıp unified support inbox, outbox, policy, consent ve audit zincirine bağlar.

## Kapsam

1. Mevcut WhatsApp modellerini ve servislerini incele:
   - WaConversation / WaContact / WaMessage / WaOutbox benzeri mevcut yapılar
   - WhatsApp consent, suppression, quiet hours, template ve webhook kuralları
2. `WhatsAppSupportChannelAdapter` production bridge olacak:
   - `getCapabilities(?SupportChannel $channel)` context-aware olsun.
   - `canReply()` yalnız kanal enabled, store type uygun, consent uygun ve capability available ise true dönsün.
   - Unsupported veya eksik WhatsApp hesabında fail-closed.
3. Inbound projection:
   - WhatsApp inbound mesajları `support_conversations` ve `support_messages` içine idempotent projection ile düşmeli.
   - Raw payload support message’a taşınmamalı.
   - Contact / marketplace customer auto-merge yapılmamalı.
4. Outbound bridge:
   - Support outbox üzerinden WhatsApp gönderimi mevcut WhatsApp outbox/service’e güvenli devredilmeli.
   - Consent yoksa gönderim fail-closed.
   - Suppressed contact’a gönderim fail-closed.
   - Template gerekiyorsa boş template parametresi engellensin.
5. Policy engine:
   - WhatsApp karakter limiti ve link/contact/PII guard davranışı korunmalı.
6. Tests:
   - inbound projection idempotent
   - raw_payload support message’a sızmaz
   - consent missing blocks outbound
   - suppressed contact blocks outbound
   - disabled channel blocks outbound
   - WhatsApp adapter canReply context-aware
   - support outbox -> WhatsApp outbox handoff kayıtları auditlenir

## Kapsam Dışı

- WhatsApp marketing campaign davranışlarını değiştirme.
- Meta API endpointleri uydurma.
- Template yönetim ekranı geliştirme.

---

# Dalga T — Birleşik Müşteri Hafızası ve Channel Identity Resolver

## Amaç

ZOLM’ün müşteri hizmetleri vaadi yalnız “mesaja cevap” değil; müşterinin kanallar arası bağlamını anlayan bir destek çalışanı hissi vermek. Bu dalga, gizlilik sınırlarını koruyarak müşteri özetini ve geçmişini unified hale getirir.

## Kapsam

1. Yeni domain servis:
   - `CustomerCareIdentityResolver`
   - Kanal kimliklerini güvenli şekilde çözer: marketplace customer id, WhatsApp contact id, e-posta/telefon hash, store scope.
2. Hard privacy rule:
   - Farklı kanallardaki kimlikler otomatik merge edilmez.
   - Merge/association yalnız explicit deterministic key veya manuel onay ile yapılabilir.
   - Telefon/e-posta ham veri ile değil hash/normalized token ile eşleştirilir.
3. Customer summary service:
   - `CustomerCareCustomerSummaryService`
   - Seçili conversation için şu özetleri üretir:
     - Son siparişler
     - Son konuşmalar
     - Açık SLA / açık ticket
     - Son iade/iptal sinyalleri varsa
     - AI için kullanılabilir güvenli bağlam
4. Inbox sağ panel entegrasyonu:
   - Müşteri özeti gerçek veriden gelsin.
   - Veri yoksa açık empty state gösterilsin; sahte sipariş/sahte metrik yok.
   - PII maskeli gösterim.
5. AI context builder entegrasyonu:
   - AI draft/auto response context’ine yalnız store-scoped, allowed ve redacted customer summary girsin.
6. Tests:
   - cross-store customer summary sızmaz
   - WhatsApp contact marketplace customer ile otomatik merge edilmez
   - deterministic/manual association olmadan channel history birleşmez
   - PII masked summary
   - AI context only includes current store safe summary
   - empty state does not fabricate orders/conversations

## Kapsam Dışı

- Tam CRM modülü yazma.
- Global customer identity graph oluşturma.
- KVKK açık rıza ekranı tasarlama.

---

# Dalga U — KVKK Retention, Anonymization ve Pilot Exit Hardening

## Amaç

Pilot canlıya alınmadan önce veri yaşam döngüsü, denetim defteri ve acil kapanış davranışlarını sertleştirmek. Bu dalga, “bugün pilotu açtık, yarın kapatmamız gerekirse güvenli kapatabiliyor muyuz?” sorusuna cevap verir.

## Kapsam

1. KVKK retention policy dokümantasyonu:
   - `docs/customer-care/kvkk-retention-policy.md`
   - Hangi tablo ne kadar tutulur?
   - Hangi alan anonymize edilir?
   - Audit ledger neden silinmez, nasıl redakte edilir?
2. Anonymization service:
   - `CustomerCareAnonymizationService`
   - Store/customer/conversation bazlı PII alanlarını maskeler/anonymize eder.
   - Audit bütünlüğünü bozmadan çalışır.
   - Dispatch attempts ve AI runs append-only mantığı korunur; raw PII redakte edilir.
3. Artisan command:
   - `customer-care:anonymize`
   - Dry-run varsayılan olsun.
   - Gerçek çalıştırma için explicit `--force`.
   - Store scope zorunlu veya güvenli fail-closed.
4. Pilot exit / emergency stop:
   - Circuit breaker forced-open olduğunda:
     - automatic reply durur,
     - manual reply devam eder,
     - pending AI dispatch’ler güvenli şekilde cancelled/failed state’e alınır.
   - `customer-care:circuit-breaker --enable` sonrası otomatik outbox tekrar gönderim yapmamalı.
5. Retention scheduler:
   - Eğer scheduler eklenirse default güvenli dry-run/report-only olmalı.
   - Canlı silme/anonymization otomatik başlamamalı.
6. Tests:
   - anonymize dry-run data değiştirmez
   - force olmadan gerçek anonymization çalışmaz
   - anonymization PII redakte eder ama ledger ilişkilerini bozmaz
   - cross-store anonymization engellenir
   - emergency stop pending AI dispatch’leri durdurur
   - manual replies circuit breaker open iken devam eder
   - route/command listesinde komut görünür

## Kapsam Dışı

- Gerçek kullanıcı hesabı silme sistemi yazma.
- Tüm platformlar için hukuki metin üretme.
- Production scheduler ile otomatik veri silme açma.

---

## Kabul Kanıtları

Uygulama bittikten sonra şu komutlar çalıştırılacak ve sonuçlar rapora yazılacak:

```bash
git diff --check
npm run build
./vendor/bin/sail artisan route:list --name=customer-care
./vendor/bin/sail artisan list customer-care --raw
./vendor/bin/sail artisan schedule:list
./vendor/bin/sail artisan test tests/Feature/CustomerCare tests/Feature/WhatsApp/SupportChannelTest.php tests/Feature/MarketplaceQuestionsTest.php --no-coverage --compact
./vendor/bin/sail artisan test --no-coverage --compact
```

Dalga S/T/U için ayrı kanıt paketleri oluştur:

- `docs/customer-care/dalga-s-kanit-paketi.md`
- `docs/customer-care/dalga-t-kanit-paketi.md`
- `docs/customer-care/dalga-u-kanit-paketi.md`

`walkthrough.md` dosyasını güncelle.

İş bitince dur; kalite kapısı için Codex baş mühendis kontrolünü bekle.

---

## Antigravity’ye Gönderilecek Kısa Komut

```text
/Volumes/TWINMOS/zolm reposunda Dalga S/T/U görevlerini uygula.

Önce şu dosyayı tamamen oku ve içindeki talimatları eksiksiz uygula:

/Volumes/TWINMOS/zolm/docs/customer-care/dalga-stu-antigravity-promptu.md

Yalnız Dalga S/T/U kapsamını uygula.
Kapsam dışındaki mevcut kullanıcı değişikliklerine dokunma.
Commit, push veya branch değişikliği yapma.
Dalga V/W/X'e geçme.
Test, build, route, command, scheduler ve git kanıt paketini verdikten sonra dur.
```
