# ZOLM AI Müşteri İletişim Merkezi — Dalga AQ/AR/AS Kabul Kararı

**Tarih:** 2026-07-13  
**İnceleyen:** Codex — Baş mühendis kontrolü  
**Önceki kalite kapısı:** `docs/customer-care/dalga-aqaras-kalite-kapisi-01.md`  
**Karar:** ✅ **Dalga AQ/AR/AS KG01 revizyonu kabul edildi**

Bu karar; Organization/Tenant v2, Enterprise API & Scoped Tokens ve Commercial Packaging katmanlarında açılan P0/P1 maddelerinin kapatıldığını, ek baş mühendis sertleştirmelerinin uygulandığını ve bir sonraki dalga için ön koşulun kapandığını kayıt altına alır.

---

## Bağımsız Doğrulama Kanıtları

Çalıştırılan kontroller:

```bash
git diff --check
npm run build
./vendor/bin/sail artisan route:list --name=customer-care
./vendor/bin/sail artisan test \
  tests/Feature/CustomerCare/CustomerCareOrganizationTest.php \
  tests/Feature/CustomerCare/CustomerCareEnterpriseApiTest.php \
  tests/Feature/CustomerCare/CustomerCareEntitlementTest.php \
  --no-coverage --compact
./vendor/bin/sail artisan test tests/Feature/CustomerCare --no-coverage --compact
./vendor/bin/sail artisan test --no-coverage --compact
```

Sonuçlar:

- `git diff --check`: ✅ temiz
- `npm run build`: ✅ başarılı
- AQ/AR/AS hedef testleri: ✅ `30 passed / 58 assertions`
- Customer Care test paketi: ✅ `376 passed / 1197 assertions`
- Full test suite: ✅ `1835 passed / 7258 assertions`
- Route listesi: ✅ Enterprise API route'ları ve `organization`, `api`, `commercial` ekranları dahil `27` customer-care route aktif

Not: Full test suite sırasında yalnız mevcut doc-comment PHPUnit metadata deprecation uyarıları görülmüştür; test başarısını veya bu kabulü bloke etmez.

---

## Kabul Edilen Revizyon Maddeleri

### 1. P0 — Enterprise API outbound guard kapandı

`CustomerCareEnterpriseApiService::sendApiReply()` doğrudan mesaj/dispatch yazmak yerine `SupportReplyService::sendAgentReply()` hattını kullanıyor.

Kabul edilen davranışlar:

- Consent, policy, channel kill-switch, capability ve outbox kuralları API cevabında da çalışıyor.
- İnsan temsilci kilidi (`ownership_status = human`) varken API reply fail-closed engelleniyor.
- Başarılı veya outbox'a alınmış yanıtlar tek `SupportMessage` ve tek `SupportDispatch` üretir.
- Policy/capability/channel/master switch bloklarında dispatch oluşturulmaz.

### 2. P0 — API DTO ve PII minimization kapandı

Enterprise API response'ları allowlist DTO yapısına indirildi.

Kabul edilen davranışlar:

- `getMessages` body alanlarını `PiiRedactor` üzerinden maskeler.
- `getConversations` ham Eloquent model dönmez.
- `external_customer_id` alanı da baş mühendis sertleştirmesiyle redacted döner; telefon/e-posta gibi müşteri identifier sızıntısı engellenir.
- API access log payload'ları token/secret/body/PII sızdırmaz.

### 3. P0 — UI dropdown tenant scoping kapandı

`Api`, `Organization` ve `Commercial` ekranlarında global `all()` kullanımına dayalı sızıntı kapatıldı.

Kabul edilen davranışlar:

- Organizasyon ve mağaza seçimleri `CustomerCareOrganizationContext` üzerinden yetki-sınırlı gelir.
- Query-string veya Livewire property manipülasyonu fail-closed resetlenir.
- `Commercial` ekranının feature açıkken render edildiği ayrıca testle doğrulanmıştır.

### 4. P0 — Billing export PII ve CSV güvenliği kapandı

`CustomerCareEntitlementService::generateBillingExport()` reason/context verilerini PII ve CSV/XML güvenlik filtresinden geçirir.

Kabul edilen davranışlar:

- TCKN/e-posta/telefon benzeri PII export'a ham yazılmaz.
- UTF-8 BOM korunur.
- XML kontrol karakterleri ve satır kıran CSV injection kalıpları temizlenir.
- Yetkisiz kullanıcı başka mağazanın billing export'unu alamaz.

### 5. P0 — Organization system actor fallback sınırı kapandı

`CustomerCareOrganizationContext::getSystemActor()` production benzeri bağlamda organizasyon dışı belirsiz fallback yapmayacak şekilde sınırlandı.

Kabul edilen davranışlar:

- Organizasyon özelinde system actor tanımı yoksa fail-closed hata üretilir.
- Local/testing fallback yalnız geliştirme ve test ergonomisi için kalır.
- Başka organizasyonun system actor'ı yanlış org içinde kullanılamaz.

### 6. P1 — Service account governance kapsamı kapandı

`SupportRbacService` service account kullanıcılarının riskli governance onay/red aksiyonlarını gerçekleştirmesini engeller.

Kabul edilen davranışlar:

- Service account `approve_risk_action` ve `reject_risk_action` izinlerinde fail-closed engellenir.
- Test artık gerçek RBAC servis çağrısını ve exception yolunu doğrular.

### 7. P1 — Commercial service-level boundary kapandı

Commercial entitlement ve billing servisleri opsiyonel user bağlamında store erişimini zorunlu doğrular.

Kabul edilen davranışlar:

- `hasEntitlement()` kullanıcı bağlamı varsa store access kontrolü yapar.
- `generateBillingExport()` kullanıcı bağlamı varsa store access kontrolü yapar.
- Yetkisiz kullanıcı başka mağaza entitlement/billing verisini okuyamaz.

---

## Baş Mühendis Ek Sertleştirmeleri

KG01 revizyon raporu yeşil geldikten sonra bağımsız incelemede üç ek sertleştirme uygulandı:

1. `Commercial` Livewire component'inde eksik `CustomerCareOrganizationContext` import'u eklendi; feature açıkken sayfanın gerçekten render olduğu testle sabitlendi.
2. Enterprise API token oluştururken `store_ids` kapsamı servis katmanında client organizasyonu ile doğrulanır hale getirildi; client-side manipülasyonla farklı organizasyon mağazası token kapsamına yazılamaz.
3. Conversation list endpoint'inde `external_customer_id` redaction eklendi.

Bu ekler kabul kapsamını genişletmez; mevcut AQ/AR/AS kalite kapısını daha güvenli kapatmak için yapılan dar, geriye uyumlu hardening düzeltmeleridir.

---

## Kalan Not / Sonraki Hardening

Bu kabulü bloke etmeyen P2 takip notları:

1. PHPUnit 12 uyumu için eski doc-comment metadata testleri attribute formatına taşınmalı.
2. Enterprise API DTO contract'ı ileride versiyonlu OpenAPI belgesine dönüştürülebilir.
3. Commercial plan değişiklikleri ileride governance approval flow ile daha sıkı bağlanabilir.

---

## Sonuç

Dalga AQ/AR/AS KG01 revizyonu kabul edilmiştir.

Bu kabul ile Dalga AT/AU/AV için daha önce koyulan “AQ/AR/AS kabul kararı yoksa başlama” ön koşulu kapanmıştır.
