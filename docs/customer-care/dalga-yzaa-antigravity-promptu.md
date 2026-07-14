# ZOLM AI Müşteri İletişim Merkezi — Dalga Y/Z/AA Antigravity Uygulama Promptu

**Tarih:** 2026-07-12  
**Hazırlayan:** Codex — Baş mühendis planı  
**Ön koşul:** Dalga V/W/X uygulaması tamamlandıktan sonra çalıştırılmalıdır.  
**Kapsam:** Dalga Y, Z, AA uygulanacak; sonraki dalgalara geçilmeyecek.

---

## 0. Genel Talimatlar

/Volumes/TWINMOS/zolm reposunda çalış.

Önce şu dosyaları oku:

- `docs/customer-care/dalga-vwx-antigravity-promptu.md`
- `docs/customer-care/dalga-stu-antigravity-promptu.md`
- `docs/customer-care/dalga-pqr-antigravity-promptu.md`
- `docs/customer-care/dalga-mno-kabul-karari.md`
- `docs/customer-care/dalga-jkl-kabul-karari.md`
- `docs/customer-care/kvkk-retention-policy.md` varsa oku
- `docs/customer-care/web-chat-widget-contract.md` varsa oku
- `AGENTS.md`

Kurallar:

- Commit, push veya branch değişikliği yapma.
- Kapsam dışındaki kullanıcı değişikliklerine dokunma.
- Feature flag varsayılanlarını kapalı tut.
- Otomatik yanıtı global olarak açma.
- Testlerde canlı dış API çağrısı yapma.
- Gerçek ödeme/billing provider entegrasyonu uydurma.
- Yeni UI varsa ZOLM Kurumsal Açık Panel Sistemi ve mobil responsive kurallarına uy.
- Excel/CSV export varsa ZOLM export kurallarını uygula.
- Dalga AB/AC/AD veya başka kapsama geçme.

---

# Dalga Y — Customer Care Onboarding Wizard ve Guided Setup

## Amaç

ZOLM kullanan firmanın Müşteri İletişim Merkezi modülünü kendi başına güvenli şekilde kurabilmesini sağlamak. Bu dalga “teknik config” seviyesinden “ürün kurulumu” seviyesine geçiştir.

## Kapsam

1. Yeni rota:
   - `/customer-care/onboarding`
   - route adı: `customer-care.onboarding`
   - `auth` + güvenli feature flag
2. Feature flag:
   - `CUSTOMER_CARE_ONBOARDING_ENABLED=false`
3. Livewire onboarding wizard:
   - Step 1: Mağaza seçimi
   - Step 2: Kanal bağlantı durumu
   - Step 3: Marka sesi
   - Step 4: Bilgi merkezi başlangıç kontrolü
   - Step 5: Pilot güvenlik checklist
   - Step 6: Manuel / Copilot / Automatic önerilen mod
4. Kurulum ilerleme kaydı:
   - Var olan tablo uygunsa kullan; yoksa minimal `support_onboarding_states` migration.
   - Store scoped.
   - Adımlar tamamlandı/eksik olarak tutulmalı.
5. Readiness entegrasyonu:
   - `CustomerCarePilotReadinessService` çıktısı wizard’da kullanılmalı.
   - Automatic önerisi ancak readiness full pass ise gösterilmeli.
6. Brand voice entegrasyonu:
   - Marka sesi adımı mevcut `BrandVoiceService` üzerinden çalışmalı.
   - PII redaction ve prompt injection guard korunmalı.
7. UI:
   - ZOLM Kurumsal Açık Panel Sistemi.
   - Mobil responsive.
   - Sol stepper / sağ ana çalışma yüzeyi olabilir.
   - Veri yoksa sahte completion göstermeyecek.
8. Tests:
   - feature flag kapalıyken 404.
   - unauthorized store seçimi fail-closed.
   - onboarding state store-scoped.
   - brand voice adımı PII redaction/prompt injection guard.
   - readiness fail ise automatic önerisi gösterilmez.
   - readiness pass ise pilot önerisi gösterilir.
   - config mutation yok.

## Kapsam Dışı

- Gerçek billing/payment açma.
- Global `.env` değiştirme.
- Dış platform OAuth akışı icat etme.

---

# Dalga Z — Plan, Limit, Kota ve Usage Metering

## Amaç

Müşteri İletişim Merkezi modülünün SaaS olarak paketlenebilmesi için plan/limit/kota altyapısının kurulması. Bu dalga ücretlendirme yapmaz; ama kullanım ölçümü, limit enforcement ve plan davranışı için teknik zemini oluşturur.

## Kapsam

1. Plan config:
   - `config/customer-care.php` içinde güvenli default plan limitleri.
   - Örn:
     - monthly_ai_drafts
     - monthly_auto_replies
     - connected_channels
     - retained_days
     - knowledge_suggestions_per_day
2. Store/organization scoped usage service:
   - `CustomerCareUsageService`
   - Aylık usage sayımı.
   - AI draft, auto reply, agent reply, knowledge suggestion, channel connection gibi event’ler sayılmalı.
3. Limit enforcement:
   - AI draft limiti dolunca yeni draft üretimi fail-closed veya copilot disabled mesajı.
   - Auto reply limiti dolunca automatic gönderim engellenir, manual reply devam eder.
   - Knowledge suggestion limiti dolunca command/panel açık sebep verir.
4. Usage ledger:
   - Append-only veya özet tablo + event table yaklaşımı.
   - Eğer yeni migration yapılırsa backward compatible.
   - PII yok.
5. UI:
   - `/customer-care/settings` veya `/customer-care/pilot` içine “Kullanım ve Limitler” kartı.
   - Kalan limitler gerçek veriden gelir; sahte progress yok.
6. Artisan report:
   - `customer-care:usage-report --store=ID`
   - JSON opsiyonu varsa iyi olur.
7. Tests:
   - usage increments on AI draft.
   - usage increments on auto reply success only.
   - blocked/failed auto reply usage sayılmaz.
   - manual reply not blocked by auto reply quota.
   - quota reached blocks AI draft/auto reply with clear reason.
   - cross-store usage isolation.
   - report command works.

## Kapsam Dışı

- Stripe/Iyzico/ödeme sistemi entegrasyonu.
- Fatura oluşturma.
- Plan satın alma UI.

---

# Dalga AA — Yönetici Kontrol Merkezi, Audit Export ve Pilot Launch Report

## Amaç

ZOLM iç ekibinin ve firma yöneticisinin pilotu izlemesini, kaliteyi denetlemesini ve lansman raporu almasını sağlamak. Bu dalga modülü “çalışıyor” seviyesinden “yönetilebilir ve raporlanabilir ürün” seviyesine taşır.

## Kapsam

1. Yeni rota:
   - `/customer-care/admin`
   - route adı: `customer-care.admin`
   - sadece admin veya yetkili rol.
   - feature flag default kapalı: `CUSTOMER_CARE_ADMIN_CENTER_ENABLED=false`
2. Admin dashboard:
   - Store listesi
   - Her store için:
     - readiness durumu
     - circuit breaker status
     - son eval skoru
     - son 24 saat AI draft / auto reply / policy block / handoff
     - pending dispatch count
     - knowledge suggestion backlog
3. Audit export:
   - CSV export:
     - UTF-8 BOM
     - XML kontrol karakter temizliği
     - PII redacted
     - store scoped / admin scoped
   - Export içeriği:
     - support_agent_actions
     - policy blocks
     - circuit breaker events
     - eval run summary
4. Pilot launch report:
   - `customer-care:pilot-launch-report --store=ID`
   - Readiness + route/command + eval + quota + circuit + policy + outbox özetini üretir.
   - Markdown rapor dosyası:
     - `docs/customer-care/pilot-launch-report-store-{id}.md`
   - Rapor gerçek veriden gelir; sahte pass yazılmaz.
5. Tests:
   - non-admin admin route erişemez.
   - admin dashboard store özetlerini doğru hesaplar.
   - audit export PII redacted ve UTF-8/BOM uyumlu.
   - pilot launch report command creates markdown.
   - report fail/pass gerçek readiness’e bağlı.
   - cross-store admin/operator sınırı korunur.

## Kapsam Dışı

- Gerçek müşteri billing verisi.
- PDF rapor üretimi.
- Email ile rapor gönderme.

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

Dalga Y/Z/AA için ayrı kanıt paketleri oluştur:

- `docs/customer-care/dalga-y-kanit-paketi.md`
- `docs/customer-care/dalga-z-kanit-paketi.md`
- `docs/customer-care/dalga-aa-kanit-paketi.md`

`walkthrough.md` dosyasını güncelle.

İş bitince dur; kalite kapısı için Codex baş mühendis kontrolünü bekle.

---

## Antigravity’ye Gönderilecek Kısa Komut

```text
/Volumes/TWINMOS/zolm reposunda Dalga Y/Z/AA görevlerini uygula.

Önce şu dosyayı tamamen oku ve içindeki talimatları eksiksiz uygula:

/Volumes/TWINMOS/zolm/docs/customer-care/dalga-yzaa-antigravity-promptu.md

Yalnız Dalga Y/Z/AA kapsamını uygula.
Kapsam dışındaki mevcut kullanıcı değişikliklerine dokunma.
Commit, push veya branch değişikliği yapma.
Dalga AB/AC/AD veya başka kapsama geçme.
Test, build, route, command, scheduler ve git kanıt paketini verdikten sonra dur.
```
