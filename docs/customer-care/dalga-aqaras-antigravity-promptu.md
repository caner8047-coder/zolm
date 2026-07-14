# ZOLM AI Müşteri İletişim Merkezi — Dalga AQ/AR/AS Antigravity Uygulama Promptu

**Tarih:** 2026-07-13  
**Hazırlayan:** Codex — Baş mühendis planı  
**Ön koşul:** Dalga AK/AL/AM ve Dalga AN/AO/AP kalite kapıları kabul edilmeden uygulanmamalıdır. Açık P0/P1 kalite kapısı varsa bu promptu uygulamaya başlama; durumu raporla ve dur.  
**Kapsam:** Dalga AQ, AR, AS uygulanacak; sonraki dalgalara geçilmeyecek.

> Baş mühendis notu: Bu prompt şimdiden hazırlanmıştır; fakat `docs/customer-care/dalga-akalam-kalite-kapisi-01.md` ve `docs/customer-care/dalga-anaoap-kalite-kapisi-01.md` kapanmadan Antigravity bu çalışmaya başlamamalıdır.

---

## 0. Genel Talimatlar

/Volumes/TWINMOS/zolm reposunda çalış.

Önce şu dosyaları tamamen oku:

- `AGENTS.md`
- `docs/customer-care/dalga-akalam-kalite-kapisi-01.md`
- `docs/customer-care/dalga-anaoap-kalite-kapisi-01.md`
- `docs/customer-care/dalga-akalam-antigravity-promptu.md`
- `docs/customer-care/dalga-anaoap-antigravity-promptu.md`
- `docs/customer-care/dalga-ahaiaj-kabul-karari.md`
- `docs/customer-care/adr/002-tenant-ve-organizasyon-siniri.md`
- `docs/customer-care/adr/003-generic-outbound-dispatch.md`
- `docs/customer-care/adr/006-human-ownership-state-machine.md`
- `docs/customer-care/kvkk-retention-policy.md`
- `docs/customer-care/integration-hub-contract.md`

Kurallar:

- Commit, push veya branch değişikliği yapma.
- Kapsam dışındaki kullanıcı değişikliklerine dokunma.
- Açık kalite kapısı varsa uygulamaya başlama; sadece blokaj raporu ver.
- Feature flag varsayılanlarını kapalı tut.
- Otomatik yanıtı global veya mağaza bazında kendiliğinden açma.
- Testlerde canlı dış API çağrısı yapma.
- Sahte başarı, sahte entitlement, sahte fatura, sahte tenant izolasyonu veya fabricated commercial metric üretme.
- Tenant/store/organization izolasyonu query, command, service ve UI action seviyesinde doğrulanmalı.
- PII/KVKK verisi log, export, API response, billing evidence, audit evidence veya tenant diagnostic içine raw sızmamalı.
- Yeni UI varsa ZOLM Kurumsal Açık Panel Sistemi ve mobil responsive kurallarına uy.
- CSV/Excel export varsa ZOLM export kurallarını uygula.
- Dalga AT veya başka kapsama geçme.

---

# Dalga AQ — Organization/Tenant v2 ve Kurumsal Hesap Sınırı

## Amaç

ZOLM Müşteri İletişim Merkezi’nde bugüne kadar store bazlı güvenlik büyük ölçüde güçlendirildi. Ancak kurumsal SaaS seviyesinde store tek başına tenant kimliği değildir. Bu dalga; legal entity, organization, store, user membership ve service actor sınırlarını netleştirir.

Bu dalga mevcut veriyi kırmaz; büyük destructive tenant migration yapmaz. Ama ileride tüm customer-care modüllerinin kullanacağı güvenli `OrganizationContext` / tenant boundary sözleşmesini kurar.

## Kapsam

1. Feature flag:
   - `CUSTOMER_CARE_ORG_CENTER_ENABLED=false`
   - Varsayılan kapalı.
2. Mevcut model analizi:
   - `User`
   - `LegalEntity`
   - `Store`
   - support role assignment
   - system actor
   - mevcut `TenantContext`
3. Yeni soyutlama:
   - `CustomerCareOrganizationContext`
   - veya mevcut `TenantContext` içinde açık organization/legal entity boundary metotları.
4. Migration gerekiyorsa backward compatible:
   - `support_organization_memberships`
   - `support_organization_settings`
   - `support_service_accounts`
   - Var olan `legal_entities` yapısı ile çakışma yaratma.
5. Access boundary:
   - Kullanıcının erişebildiği organization/legal entity/store listesi deterministic olmalı.
   - Store access varsa hangi organization üzerinden olduğu izlenebilir olmalı.
   - Cross-organization store access fail-closed.
6. System actor:
   - Her organization için system actor resolved edilebilir olmalı.
   - Eksik system actor production’da fail-closed.
   - Factory/ilk kullanıcı fallback yok.
7. Service account:
   - İnsan kullanıcı olmayan entegrasyon aktörleri için minimal service account kaydı.
   - Token üretimi bu dalgada şart değil; sadece identity boundary.
   - Raw secret üretme/loglama yok.
8. UI:
   - `/customer-care/organization`
   - Organization/store membership görünümü,
   - service actor durumu,
   - access diagnostic,
   - store boundary health.
9. Commands:
   - `customer-care:org-diagnostics --organization=ID --dry-run`
   - `customer-care:org-diagnostics --store=ID --dry-run`
10. Tests:
    - Feature flag kapalı route 404.
    - User yalnız organization membership içindeki store’ları görebilir.
    - Cross-organization store access fail-closed.
    - System actor organization scope dışında kullanılamaz.
    - Service account insan kullanıcı gibi self-approval yapamaz.
    - Organization diagnostic PII veya secret sızdırmaz.
    - Existing store-level TenantContext davranışı geriye uyumlu kalır.

## Kapsam Dışı

- Mevcut tüm app tenant modelini kökten değiştirmek.
- Global Eloquent scope eklemek.
- SSO/SCIM implementasyonu.
- Faturalama organization ownership aktarımı.

---

# Dalga AR — Enterprise API, Service Accounts ve Scoped Access Tokens

## Amaç

Kurumsal müşterilerin kendi sistemlerinden ZOLM Müşteri İletişim Merkezi’ne güvenli, scope’lu ve denetlenebilir erişim sağlaması. Bu dalga public enterprise API zemini kurar; canlı üçüncü parti provider başarısı uydurmaz.

Bu dalga Integration Hub webhook’larının yanında inbound/outbound kurumsal API erişim standardını oluşturur.

## Kapsam

1. Feature flag:
   - `CUSTOMER_CARE_ENTERPRISE_API_ENABLED=false`
   - Varsayılan kapalı.
2. API access model:
   - service account,
   - token hash,
   - scope listesi,
   - store/organization boundary,
   - expires_at,
   - last_used_at,
   - revoked_at.
3. Yeni tablolar gerekiyorsa:
   - `support_api_clients`
   - `support_api_tokens`
   - `support_api_access_logs`
4. Token güvenliği:
   - Token yalnız oluşturma anında gösterilir.
   - DB’de token hash saklanır.
   - Plain token loglanmaz/export edilmez.
   - Token prefix kısa tanımlayıcı olarak tutulabilir.
5. Scope örnekleri:
   - `conversations:read`
   - `messages:read`
   - `replies:create`
   - `analytics:read`
   - `webhooks:manage`
   - `knowledge:read`
   - `knowledge:write`
6. API endpointleri minimal ve güvenli:
   - `GET /api/customer-care/v1/conversations`
   - `GET /api/customer-care/v1/conversations/{id}/messages`
   - `POST /api/customer-care/v1/conversations/{id}/reply`
   - `GET /api/customer-care/v1/analytics/summary`
   - Her endpoint feature flag + token + scope + tenant boundary kontrol etmeli.
7. Reply endpoint:
   - policy engine,
   - rate limit,
   - channel capability,
   - human/AI ownership guard,
   - support dispatch/outbox standardı dışına çıkmamalı.
8. Audit:
   - Her API request redacted access log yazmalı.
   - PII response minimization uygulanmalı.
   - 4xx/5xx log secret/token içermemeli.
9. UI:
   - `/customer-care/api`
   - API client listesi,
   - token oluştur/revoke,
   - scope seçimi,
   - son kullanım logları.
10. Docs:
    - `docs/customer-care/enterprise-api-contract.md`
    - Auth header,
    - scope listesi,
    - rate limit,
    - error codes,
    - örnek request/response.
11. Tests:
    - Feature flag kapalı API 404/fail-closed.
    - Token hash doğrulama çalışır; plain token DB’de yok.
    - Scope eksikse endpoint 403.
    - Cross-store conversation okunamaz.
    - Reply endpoint policy violation’da dispatch oluşturmaz.
    - Revoked/expired token erişemez.
    - Access logs token/secret/PII sızdırmaz.
    - API UI route feature flag kapalıyken 404.

## Kapsam Dışı

- OAuth authorization server.
- SAML/OIDC SSO.
- Public marketplace app store.
- SDK paketleri yayınlamak.

---

# Dalga AS — Commercial Packaging, Entitlements ve Billing Readiness

## Amaç

ZOLM Müşteri İletişim Merkezi’nin ürün paketleri, özellik hakları, kullanım limitleri ve ticari raporlamasını kurumsal satışa hazır hale getirmek. Dalga Z usage metering’i kurdu; bu dalga onu ürün paketleri ve entitlement karar katmanına bağlar.

Bu dalga gerçek ödeme/tahsilat sistemi kurmaz. Fatura kesmez. Ama ticari kararların teknik enforcement temelini kurar.

## Kapsam

1. Feature flag:
   - `CUSTOMER_CARE_COMMERCIAL_CENTER_ENABLED=false`
   - Varsayılan kapalı.
2. Plan modelleri:
   - trial,
   - starter,
   - growth,
   - pro,
   - enterprise.
3. Entitlement örnekleri:
   - enabled channels,
   - monthly AI drafts,
   - monthly auto replies,
   - monthly agent seats,
   - knowledge suggestions,
   - integrations,
   - enterprise API,
   - security/evidence center,
   - experiments,
   - launch center,
   - success center.
4. Yeni tablolar gerekiyorsa:
   - `support_commercial_plans`
   - `support_commercial_subscriptions`
   - `support_entitlements`
   - `support_entitlement_events`
5. Entitlement service:
   - `CustomerCareEntitlementService`
   - feature access kararları tek merkezden verilmeli.
   - Unknown entitlement fail-closed.
   - Manual agent reply gibi kritik operasyonel özellikler yanlışlıkla paket limitiyle kesilmemeli.
6. Usage metering entegrasyonu:
   - Dalga Z `CustomerCareUsageService` ile uyumlu.
   - Başarılı usage event append-only kalmalı.
   - Blocked usage event yanlışlıkla quota tüketmemeli.
7. Plan değişikliği:
   - Upgrade/downgrade request kaydı.
   - Gerçek ödeme yok.
   - Enterprise plan override governance approval gerektirebilir.
8. UI:
   - `/customer-care/commercial`
   - Plan/entitlement görünümü,
   - usage progress,
   - limit yaklaşma uyarıları,
   - plan compare,
   - exportable usage summary.
9. Commands:
   - `customer-care:entitlement-audit --store=ID --dry-run`
   - `customer-care:usage-billing-export --store=ID --month=YYYY-MM`
10. Export:
    - CSV/Excel kurallarına uygun.
    - PII yok.
    - XML kontrol karakter temizliği.
    - UTF-8 BOM.
11. Tests:
    - Feature flag kapalı route 404.
    - Unknown entitlement fail-closed.
    - Plan limit auto reply/draft’i engeller ama manual reply’i engellemez.
    - Usage event append-only korunur.
    - Blocked action quota tüketmez.
    - Cross-store subscription/entitlement okunamaz.
    - Billing export PII içermez ve UTF-8/XML güvenli.
    - Enterprise override approval olmadan uygulanmaz.
    - Existing usage metering regresyonu bozulmaz.

## Kapsam Dışı

- Stripe/Iyzico/Paraşüt/Logo canlı ödeme veya fatura entegrasyonu.
- Tahsilat ekranı.
- Muhasebe kayıtlarının resmi deftere aktarımı.
- Vergi/fatura mevzuat motoru.

---

## Kanıt Paketleri

Dalga AQ/AR/AS için ayrı kanıt paketleri oluştur:

- `docs/customer-care/dalga-aq-kanit-paketi.md`
- `docs/customer-care/dalga-ar-kanit-paketi.md`
- `docs/customer-care/dalga-as-kanit-paketi.md`

Her pakette şunlar olsun:

1. Değiştirilen/eklenen dosyalar.
2. Migration listesi ve rollback notu.
3. Route/API/command/scheduler listesi.
4. Feature flag varsayılanları.
5. Tenant/KVKK/fail-closed güvenlik kanıtları.
6. Test komutları ve sonuçları.
7. Bilinen kapsam dışı maddeler.

`walkthrough.md` dosyasını da Dalga AQ/AR/AS özetiyle güncelle.

---

## Zorunlu Doğrulama Komutları

En az şunları çalıştır:

```bash
git diff --check
npm run build
./vendor/bin/sail artisan route:list --name=customer-care
./vendor/bin/sail artisan route:list --path=api/customer-care
./vendor/bin/sail artisan list customer-care --raw
./vendor/bin/sail artisan schedule:list
./vendor/bin/sail artisan test tests/Feature/CustomerCare --no-coverage --compact
```

Mümkünse full suite:

```bash
./vendor/bin/sail artisan test --no-coverage --compact
```

Full suite uzun sürerse en az Customer Care paketi + ilgili regresyon testlerini çalıştır ve full suite’in çalıştırılamama sebebini açıkça raporla.

---

## Antigravity’ye Verilecek Kısa Komut

```text
/Volumes/TWINMOS/zolm reposunda Dalga AQ/AR/AS görevlerini uygula.

Önce şu dosyayı tamamen oku ve içindeki talimatları eksiksiz uygula:

/Volumes/TWINMOS/zolm/docs/customer-care/dalga-aqaras-antigravity-promptu.md

Yalnız Dalga AQ/AR/AS kapsamını uygula.
AK/AL/AM ve AN/AO/AP kalite kapıları kabul edilmediyse uygulamaya başlama; blokaj raporu ver ve dur.
Kapsam dışındaki mevcut kullanıcı değişikliklerine dokunma.
Commit, push veya branch değişikliği yapma.
Dalga AT veya başka kapsama geçme.
Test, build, route, API route, command, scheduler ve git kanıt paketini verdikten sonra dur.
```
