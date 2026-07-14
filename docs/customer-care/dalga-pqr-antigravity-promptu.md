# ZOLM AI Müşteri İletişim Merkezi — Dalga P/Q/R Antigravity Uygulama Promptu

**Tarih:** 2026-07-12  
**Hazırlayan:** Codex — Baş mühendis planı  
**Ön koşul:** Dalga J/K/L ve Dalga M/N/O kabul edilmiştir.  
**Kapsam:** Dalga P, Q, R uygulanacak; sonraki dalgalara geçilmeyecek.

---

## 0. Genel Talimatlar

/Volumes/TWINMOS/zolm reposunda çalış.

Önce şu dosyaları oku:

- `docs/customer-care/dalga-jkl-kabul-karari.md`
- `docs/customer-care/dalga-mno-kabul-karari.md`
- `docs/customer-care/dalga-mno-kalite-kapisi-01.md`
- `docs/customer-care/adr/003-generic-outbound-dispatch.md`
- `docs/customer-care/adr/007-ai-shadow-golden-eval-ledger.md`
- `AGENTS.md`

Kurallar:

- Commit, push veya branch değişikliği yapma.
- Kapsam dışındaki kullanıcı değişikliklerine dokunma.
- Feature flag varsayılanlarını güvenli kapalı tut.
- Otomatik yanıtı global olarak açma.
- Canlı dış API çağrısı testlerde yapılmayacak; fake/mock kullanılacak.
- Migration gerekiyorsa backward compatible olacak.
- Yeni UI varsa ZOLM Kurumsal Açık Panel Sistemi ve mobil responsive kurallarına uyacak.
- Excel/CSV export varsa mevcut ZOLM export kurallarına uyacak.
- Dalga S/T/U veya başka kapsama geçme.

---

# Dalga P — Hepsiburada Production-Grade Support Adapter

## Amaç

Hepsiburada kanalını skeleton/dummy seviyesinden production-grade müşteri iletişim adaptörüne taşımak. Trendyol Dalga M standardına benzer güvenlik, idempotency, tenant izolasyonu ve outbox uyumu sağlanacak.

## Kapsam

1. Mevcut Hepsiburada connector ve MarketplaceQuestion akışını incele.
2. `HepsiburadaSupportChannelAdapter` için context-aware capability üret:
   - Kanal disabled ise `send_messages = unavailable`.
   - Mağaza Hepsiburada değilse veya connection configured değilse `send_messages = unavailable`.
   - DB capability unavailable ise `canReply()` false.
3. `sendReply()` gerçek güvenli gönderim yoluna bağlanacak:
   - Mevcut `MarketplaceQuestionAnswerService` / Hepsiburada connector kullanılabiliyorsa onu kullan.
   - Connector canlı cevap sözleşmesini desteklemiyorsa sahte başarı dönme; fail-closed dön.
4. Strict external id parsing:
   - Kabul edilen format net olsun: örn. `hepsiburada_questions_{id}`.
   - Malformed format fail-closed.
5. Tenant / IDOR koruması:
   - Soru ID’si channel store’una ait değilse cevap gönderme.
6. Idempotency:
   - Aynı idempotency key ile mükerrer gönderim engellenecek.
   - Cache veya mevcut outbox idempotency yapısıyla tutarlı olsun.
7. Health check:
   - connection yoksa `not_configured`.
   - connection configured ise `ok`.
   - provider/marketplace uyumsuzsa `error`.
8. Tests:
   - capabilities configured/enabled durumlarına göre değişiyor.
   - disabled channel reply bloklanıyor.
   - unsupported/unavailable capability reply bloklanıyor.
   - malformed external id bloklanıyor.
   - cross-tenant question IDOR bloklanıyor.
   - idempotency duplicate başarıyla tekrar gönderim yapmadan dönüyor.
   - connector unsupported ise fail-closed; sahte success yok.

## Kapsam Dışı

- Hepsiburada’dan yeni inbound sync protokolü icat etme.
- Var olmayan API credential veya endpoint uydurma.
- Global auto reply açma.

---

# Dalga Q — N11 Production-Grade Support Adapter

## Amaç

N11 skeleton/fail-closed adapter’ını production-grade destek adaptörüne yükseltmek. N11 soru/cevap akışı mevcut MarketplaceQuestion sistemiyle entegre edilecek.

## Kapsam

1. Mevcut N11 connector ve MarketplaceQuestion testlerini incele.
2. `N11SupportChannelAdapter` için context-aware capability üret:
   - connection configured değilse `send_messages = unavailable`.
   - kanal disabled ise unavailable.
   - DB capability unavailable ise `canReply()` false.
3. `sendReply()` güvenli gönderim:
   - Mevcut N11 connector canlı cevap sözleşmesini destekliyorsa `MarketplaceQuestionAnswerService` üzerinden gönder.
   - Desteklemiyorsa fail-closed dön; sahte success dönme.
4. Strict external id parsing:
   - Kabul edilen format net olsun: örn. `n11_questions_{id}`.
5. Tenant / IDOR:
   - Soru channel store’una ait değilse fail-closed.
6. Idempotency:
   - Aynı idempotency key tekrarında kanal API yeniden tetiklenmesin.
7. Contract test genişletmesi:
   - Trendyol, Hepsiburada, N11, WhatsApp adapter contract testleri ortak davranışı doğrulasın.
8. Tests:
   - N11 capability matrix.
   - N11 canReply configured/enabled/capability durumları.
   - malformed id.
   - cross-tenant idor.
   - idempotency.
   - connector unsupported fail-closed.

## Kapsam Dışı

- N11 canlı endpointleri repo içinde yoksa yeni canlı API protokolü uydurma.
- Ürün/sipariş sync davranışlarını değiştirme.
- Global automatic mode açma.

---

# Dalga R — Customer Care Settings, Brand Voice ve Pilot Activation Center

## Amaç

Modülün canlı pilot öncesi mağaza bazlı yönetilebilir olmasını sağlamak: marka sesi, kanal bazlı çalışma modu, confidence threshold, pilot allowlist görünürlüğü ve güvenli aktivasyon akışı tek panelde yönetilebilir hale gelecek.

## Kapsam

1. Yeni rota:
   - `/customer-care/settings`
   - route adı: `customer-care.settings`
   - `auth` + `customer-care.feature:settings_enabled` veya güvenli eşdeğer feature flag ile korunacak.
   - Yeni feature flag default `false`.
2. Store scoped settings UI:
   - ZOLM Kurumsal Açık Panel Sistemi.
   - Mobil responsive.
   - Seçili mağaza dışındaki mağaza verisi görülemeyecek.
3. Marka sesi yönetimi:
   - Mevcut `BrandVoiceService` varsa onu kullan.
   - Ton, hitap, emoji, selamlama, imza, kısa örnek cevap alanları.
   - PII redaction + prompt injection guard.
   - Değişiklik audit log’a yazılacak.
4. Kanal bazlı çalışma modu:
   - `manual`, `copilot`, `automatic`.
   - Default `manual`.
   - Automatic seçimi sadece şu şartlarda mümkün:
     - store pilot allowlist içinde,
     - golden eval geçerli ve güncel,
     - circuit breaker open değil,
     - auto reply global flag açık,
     - channel enabled,
     - policy engine self-test pass.
   - Şartlar sağlanmıyorsa UI açık sebep gösterecek ve kaydetmeyecek.
5. Confidence threshold ve güvenlik eşikleri:
   - Mağaza/kanal bazında minimum confidence threshold tanımlanabilecekse ekle.
   - Eğer mevcut şema yoksa güvenli minimal yapı kur; migration backward compatible olmalı.
   - Sistem default threshold 80 altında automatic seçime izin vermemeli.
6. Pilot activation checklist:
   - ReadinessService çıktısı panelde gösterilecek.
   - “Canlıya al” butonu sadece checklist full pass ise aktif.
   - Bu buton global config’i değiştirmeyecek; yalnız store/channel ayarlarını güvenli şekilde güncelleyecek.
7. Tests:
   - settings route flag kapalıyken 404.
   - unauthorized store selection 403 veya fail-closed.
   - brand voice update PII redaction + prompt injection guard.
   - automatic mode readiness başarısızken kaydedilemez.
   - automatic mode readiness başarılıyken kaydedilebilir.
   - manual/copilot seçimleri auto reply global flag’den bağımsız kaydedilebilir.
   - audit log oluşur.
   - UI render ve config mutation yok.

## Kapsam Dışı

- Global `.env` değerlerini runtime’da değiştirme.
- Auto reply’ı varsayılan açık yapma.
- Yeni müşteri mesajı gönderme/cevaplama davranışı ekleme.
- Instagram, Google Maps, Shopify, Ikas entegrasyonlarına geçme.

---

## Kabul Kanıtları

Uygulama bittikten sonra şu komutlar çalıştırılacak ve sonuçlar rapora yazılacak:

```bash
git diff --check
npm run build
./vendor/bin/sail artisan route:list --name=customer-care
./vendor/bin/sail artisan list customer-care --raw
./vendor/bin/sail artisan test tests/Feature/CustomerCare tests/Feature/WhatsApp/SupportChannelTest.php tests/Feature/MarketplaceQuestionsTest.php --no-coverage --compact
./vendor/bin/sail artisan test --no-coverage --compact
```

Dalga P/Q/R için ayrı kanıt paketleri oluştur:

- `docs/customer-care/dalga-p-kanit-paketi.md`
- `docs/customer-care/dalga-q-kanit-paketi.md`
- `docs/customer-care/dalga-r-kanit-paketi.md`

`walkthrough.md` dosyasını güncelle.

İş bitince dur; kalite kapısı için Codex baş mühendis kontrolünü bekle.

---

## Antigravity’ye Gönderilecek Kısa Komut

```text
/Volumes/TWINMOS/zolm reposunda Dalga P/Q/R görevlerini uygula.

Önce şu dosyayı tamamen oku ve içindeki talimatları eksiksiz uygula:

/Volumes/TWINMOS/zolm/docs/customer-care/dalga-pqr-antigravity-promptu.md

Yalnız Dalga P/Q/R kapsamını uygula.
Kapsam dışındaki mevcut kullanıcı değişikliklerine dokunma.
Commit, push veya branch değişikliği yapma.
Dalga S/T/U'ya geçme.
Test, build, route, command ve git kanıt paketini verdikten sonra dur.
```
