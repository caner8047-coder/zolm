# ZOLM AI Müşteri İletişim Merkezi — Dalga AQ/AR/AS Kalite Kapısı 01

**Tarih:** 2026-07-13  
**İnceleyen:** Codex — Baş mühendis kontrolü  
**İlgili prompt:** `docs/customer-care/dalga-aqaras-antigravity-promptu.md`  
**Karar:** ❌ **Dalga AQ/AR/AS kabul edilmedi — P0/P1 revizyon gerekli**

Dalga AQ/AR/AS teslimatı repo içinde görünür durumda: route, command, migration, model, servis, test ve kanıt paketleri mevcut. Hedef testler yeşil. Ancak bağımsız kod incelemesinde testlerin yakalamadığı üretim seviyesi güvenlik ve doğruluk açıkları bulundu.

Bu kalite kapısı, modülün bir sonraki Dalga AT/AU/AV aşamasına geçmesini engeller.

---

## Doğrulanan Komutlar

```bash
git diff --check
./vendor/bin/sail artisan route:list --name=customer-care
./vendor/bin/sail artisan route:list --path=api/customer-care
./vendor/bin/sail artisan list customer-care --raw
./vendor/bin/sail artisan test \
  tests/Feature/CustomerCare/CustomerCareOrganizationTest.php \
  tests/Feature/CustomerCare/CustomerCareEnterpriseApiTest.php \
  tests/Feature/CustomerCare/CustomerCareEntitlementTest.php \
  --no-coverage --compact
```

Sonuçlar:

- `git diff --check`: ✅ temiz
- AQ/AR/AS hedef testleri: ✅ `19 passed / 32 assertions`
- Route listesi: ✅ `organization`, `api`, `commercial` rotaları ve 4 Enterprise API endpoint’i görünüyor
- Command listesi: ✅ `org-diagnostics`, `entitlement-audit`, `usage-billing-export` görünüyor

> Not: Testlerin yeşil olması kabul için yeterli değil; aşağıdaki P0 maddeleri test kapsamı boşluklarını gösteriyor.

---

## P0-1 — Enterprise API reply endpoint’i `SupportReplyService` / outbox güvenlik standardını bypass ediyor

**Dosyalar:**

- `app/Http/Controllers/CustomerCare/EnterpriseApiController.php`
- `app/Services/Support/CustomerCareEnterpriseApiService.php`

**Bulgular:**

`EnterpriseApiController::reply()` policy check yaptıktan sonra `CustomerCareEnterpriseApiService::sendApiReply()` çağırıyor. Bu metod ise doğrudan:

- `SupportMessage::create()`
- `SupportDispatch::create()`

yapıyor.

Bu yol mevcut production outbound standardını bypass ediyor:

- `SupportReplyService::sendAgentReply()`
- channel capability / `canReply()`
- rate limit
- ownership / human lock guard
- outbox idempotency standardı
- policy audit standardı
- channel kill-switch ve connector fail-closed zinciri

**Risk:** Enterprise API ile dış sistemler, temsilci yanıtı standardının dışında dispatch oluşturabilir. Bu canlıda kanal capability, rate limit veya ownership guard’ın atlanmasına yol açabilir.

**Beklenen düzeltme:**

Enterprise API reply endpoint’i doğrudan message/dispatch oluşturmamalı. Cevap gönderimi mevcut `SupportReplyService::sendAgentReply()` veya aynı kontrolleri kullanan tek standard outbound servis üzerinden yapılmalı.

**Kabul testleri:**

- Enterprise API reply, disabled channel durumunda dispatch oluşturmaz.
- Enterprise API reply, unavailable capability durumunda dispatch oluşturmaz.
- Enterprise API reply, human ownership / lock kurallarını ihlal edemez.
- Enterprise API reply, rate limit veya kill-switch kapalıyken fail-closed döner.
- Başarılı API reply tek bir `SupportMessage` ve tek bir outbox/dispatch standardı üzerinden ilerler.

---

## P0-2 — Enterprise API message response raw PII döndürebiliyor

**Dosya:** `app/Http/Controllers/CustomerCare/EnterpriseApiController.php`

**Bulgular:**

`getMessages()` içinde encrypted body decrypt edilip doğrudan response’a yazılıyor:

```php
'body' => $body,
```

Kod yorumunda “hassas verileri filtreleyebiliriz” denmiş fakat fiili PII redaction yok.

**Risk:** `messages:read` scope’una sahip bir entegrasyon token’ı müşteri mesajındaki e-posta, telefon, TCKN veya özel bilgileri raw alabilir. Prompt “PII response minimization” şartı koymuştu.

**Beklenen düzeltme:**

API response body alanları `PiiRedactor` veya eşdeğer merkezi redaction servisinden geçmeli. Gerekirse raw message read ayrıca explicit daha riskli scope’a ayrılmalı; varsayılan `messages:read` PII-minimized dönmeli.

**Kabul testleri:**

- Mesaj gövdesinde e-posta/telefon/TCKN varsa API response maskeli döner.
- Access log zaten redacted kalır.
- Response minimization conversation listesi için de uygulanır; gereksiz Eloquent model alanları ham dönmez, allowlist DTO döner.

---

## P0-3 — UI dropdown’ları tenant dışı organization/store isimlerini sızdırıyor

**Dosyalar:**

- `app/Livewire/CustomerCare/Api.php`
- `app/Livewire/CustomerCare/Organization.php`
- `app/Livewire/CustomerCare/Commercial.php`

**Bulgular:**

Üç UI da erişim detaylarını seçili kayıtta kontrol etse bile liste kaynaklarında global sorgu kullanıyor:

- `LegalEntity::all()`
- `MarketplaceStore::all()`
- `MarketplaceStore::first()`

Örnekler:

- `Api::render()` → `organizations => LegalEntity::all()`
- `Organization::render()` → `organizations => LegalEntity::all()`
- `Commercial::render()` → `stores => MarketplaceStore::all()`
- `Commercial::mount()` → `MarketplaceStore::first()`

**Risk:** Kullanıcı yetkisi olmayan organization/store adlarını dropdown içinde görebilir. Bu doğrudan tenant metadata sızıntısıdır.

**Beklenen düzeltme:**

Tüm listeler kullanıcı erişimine göre scoped olmalı:

- organization listesi: sadece kullanıcının owner/member/service-account/admin olarak erişebildiği legal entity kayıtları
- store listesi: sadece erişilebilen organization/store kayıtları
- mount default’u global `first()` olmamalı; kullanıcının erişebildiği ilk kayıt veya boş state olmalı

**Kabul testleri:**

- Yetkisiz kullanıcı API ekranında başka organization adını dropdown’da göremez.
- Yetkisiz kullanıcı Organization ekranında başka organization adını dropdown’da göremez.
- Yetkisiz kullanıcı Commercial ekranında başka store adını dropdown’da göremez.
- Query-string ile yabancı `selectedOrgId` / `selectedStoreId` verilirse listeler boş kalmakla yetinmez; güvenli hata/404/403 veya explicit fail-closed davranış verir.

---

## P0-4 — Billing export PII-safe değil; TCKN/e-posta/telefon maskelenmiyor

**Dosya:** `app/Services/Support/CustomerCareEntitlementService.php`

**Bulgular:**

`generateBillingExport()` yalnız XML kontrol karakterlerini ve noktalı virgülü temizliyor:

```php
$reason = preg_replace(...);
$reason = str_replace(';', ',', $reason);
```

Ancak PII redaction yok. Testte TCKN içeren örnek veri var; fakat test TCKN’in çıktıda olmadığını assert etmiyor.

**Risk:** Billing export içine TCKN, e-posta, telefon veya serbest metindeki müşteri bilgisi raw yazılabilir. Bu KVKK ve faturalama kanıt paketi için P0 sızıntıdır.

**Beklenen düzeltme:**

`generateBillingExport()` reason/context alanlarını merkezi `PiiRedactor` ile maskelemeli, sonra XML/CSV sanitization uygulamalı.

**Kabul testleri:**

- Billing export içinde TCKN raw görünmez.
- E-posta ve telefon raw görünmez.
- UTF-8 BOM korunur.
- XML kontrol karakterleri temiz kalır.
- CSV delimiter/newline injection normalize edilir.

---

## P0-5 — Organization system actor global fallback kullanıyor

**Dosya:** `app/Services/Support/CustomerCareOrganizationContext.php`

**Bulgular:**

Yorum “global fallback yerine fail-closed” diyor, fakat kod şunu yapıyor:

```php
$email = $settings?->system_actor_email ?? config('customer-care.system_actor_email');
```

Bu, organization-specific system actor yoksa global system actor’a düşer.

**Risk:** AQ dalgasının ana hedefi organization boundary idi. Global fallback, yanlış organization adına system actor işlemi yapılmasına kapı açar.

**Beklenen düzeltme:**

Production/enterprise organization context’te organization-specific system actor zorunlu olmalı. Eğer migration sonrası geçiş kolaylığı isteniyorsa bu ancak explicit güvenli config ile local/test ortamında açık olabilir; production default fail-closed olmalı.

**Kabul testleri:**

- Organization setting yoksa global config dolu olsa bile production-like context fail-closed döner.
- Organization setting varsa yalnız o actor resolve edilir.
- Actor başka organization scope içinde kullanılamaz.

---

## P1-1 — Service account self-approval testi gerçek governance akışını kapsamıyor

**Dosya:** `tests/Feature/CustomerCare/CustomerCareOrganizationTest.php`

**Bulgular:**

`service_account_cannot_self_approve_governance` testi sadece `isServiceAccount()` true döndüğünü assert ediyor. Gerçek bir approval/reject/consume akışını denemiyor.

**Beklenen düzeltme:**

Mevcut governance approval servisinden gerçek bir riskli approval request oluşturulmalı ve service account aktörüyle approve edilmeye çalışıldığında fail-closed olduğu test edilmeli.

---

## P1-2 — Commercial service store erişimi bazı public metotlarda actor almıyor

**Dosya:** `app/Services/Support/CustomerCareEntitlementService.php`

**Bulgular:**

`hasEntitlement()` ve `generateBillingExport()` storeId alıyor ama actor/store erişim doğrulaması yapmıyor. Bazı UI yolları ayrıca `TenantContext` kullanıyor; fakat service-level boundary eksik.

**Beklenen düzeltme:**

External/UI/command kullanımlarında service-level actor doğrulaması veya wrapper metotlar netleştirilmeli. En azından export ve plan/entitlement görüntüleme yolları store scoped olmalı.

**Kabul testleri:**

- Yetkisiz kullanıcı başka store için billing export alamaz.
- Yetkisiz kullanıcı başka store entitlement eventlerini okuyamaz.

---

## P1-3 — Kanıt paketleri testlerin yakalamadığı riskleri “geçti” olarak raporluyor

**Dosyalar:**

- `docs/customer-care/dalga-aq-kanit-paketi.md`
- `docs/customer-care/dalga-ar-kanit-paketi.md`
- `docs/customer-care/dalga-as-kanit-paketi.md`
- `walkthrough.md`

**Beklenen düzeltme:**

Revizyon sonrası kanıt paketleri gerçek düzeltmeleri ve yeni testleri içerecek şekilde güncellenmeli. Eski “tamamlandı” ifadeleri korunacaksa, KG01 revizyonunun uygulandığı açıkça belirtilmeli.

---

## Revizyon Sonrası Zorunlu Doğrulama

Revizyon bittikten sonra şu komutlar çalıştırılmalı ve sonuçlar kanıt paketlerine yazılmalı:

```bash
git diff --check
npm run build
./vendor/bin/sail artisan route:list --name=customer-care
./vendor/bin/sail artisan route:list --path=api/customer-care
./vendor/bin/sail artisan list customer-care --raw
./vendor/bin/sail artisan test \
  tests/Feature/CustomerCare/CustomerCareOrganizationTest.php \
  tests/Feature/CustomerCare/CustomerCareEnterpriseApiTest.php \
  tests/Feature/CustomerCare/CustomerCareEntitlementTest.php \
  --no-coverage --compact
./vendor/bin/sail artisan test tests/Feature/CustomerCare --no-coverage --compact
./vendor/bin/sail artisan test --no-coverage --compact
```

---

## Antigravity’ye Gönderilecek Kısa Komut

```text
/Volumes/TWINMOS/zolm reposunda Dalga AQ/AR/AS Kalite Kapısı 01 revizyonunu uygula.

Önce şu dosyayı tamamen oku ve içindeki P0/P1 maddelerini eksiksiz kapat:

/Volumes/TWINMOS/zolm/docs/customer-care/dalga-aqaras-kalite-kapisi-01.md

Yalnız bu kalite kapısı revizyon kapsamını uygula.
Kapsam dışındaki mevcut kullanıcı/Codex değişikliklerine dokunma.
Commit, push veya branch değişikliği yapma.
Dalga AT/AU/AV veya başka kapsama geçme.

Özellikle:
- Enterprise API reply endpointini SupportReplyService / standart outbound guard zincirine bağla.
- API response PII minimization ekle.
- API, Organization ve Commercial UI dropdown/listelerini tenant-scoped yap.
- Billing export PII redaction ekle.
- Organization system actor global fallback davranışını fail-closed hale getir.
- Service account self-approval için gerçek governance testini ekle.
- Commercial service-level store boundary testlerini güçlendir.

Test, build, route, command, scheduler ve git kanıt paketini verdikten sonra dur.
```

---

## Sonuç

Dalga AQ/AR/AS bu haliyle kabul edilmedi. P0/P1 revizyonları kapanmadan Dalga AT/AU/AV uygulanmamalıdır.
