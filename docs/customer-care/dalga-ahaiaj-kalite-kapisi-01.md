# ZOLM AI Müşteri İletişim Merkezi — Dalga AH/AI/AJ Kalite Kapısı 01

Tarih: 2026-07-13  
İnceleyen: Codex Baş Mühendis  
Karar: **RED / REVİZYON GEREKLİ**

Bu rapor, Dalga AH (Enterprise Governance), Dalga AI (Compliance Center v2) ve Dalga AJ (Production Reliability) uygulamasının bağımsız kalite kapısı incelemesidir.

Önemli: Bu dalga promptunda açıkça “Dalga AB/AC/AD ve AE/AF/AG kalite kapılarında açık P0/P1 varsa uygulamaya başlama” ön koşulu vardı. Mevcut kod incelemesinde Dalga AE/AF/AG Kalite Kapısı 02’de listelenen bazı P0 maddelerin hâlâ kapanmadığı görülmüştür. Bu nedenle AH/AI/AJ sadece kendi içinde değil, süreç ön koşulu açısından da kabul edilemez.

---

## 1. Çalıştırılan Kontroller

```bash
git diff --check
```

Sonuç: Temiz.

```bash
./vendor/bin/sail artisan test \
  tests/Feature/CustomerCare/CustomerCareGovernanceTest.php \
  tests/Feature/CustomerCare/CustomerCareComplianceTest.php \
  tests/Feature/CustomerCare/CustomerCareReliabilityTest.php \
  --no-coverage --compact
```

Sonuç:

```text
13 passed (33 assertions)
```

Hedef testlerin yeşil olması olumlu; fakat test kapsamı canlı güvenlik yollarını yakalamıyor.

---

## 2. P0 Ön Koşul İhlali

### P0-0 — AE/AF/AG Kalite Kapısı 02 açıkken AH/AI/AJ uygulanmış

Dosyalar:

- `docs/customer-care/dalga-aeafag-kalite-kapisi-02.md`
- `app/Services/Support/Integration/CustomerCareIntegrationHubService.php`
- `app/Livewire/CustomerCare/QualityCenter.php`
- `app/Console/Commands/CustomerCareSampleQualityReviewsCommand.php`

Durum:

AH/AI/AJ promptu önceki açık P0/P1 maddeleri kapanmadan uygulanmamalıydı. Mevcut kodda hâlâ şu örnekler görülüyor:

- Integration Hub secret çözümlemesinde decrypt başarısızsa plaintext fallback kullanılıyor.
- QualityCenter `submitReview()` hâlâ `selectedConversationId` / `selectedMessageId` public property’lerine güveniyor.
- Quality sampling command çıktısında raw prompt/body özetleri PII redaction olmadan tabloya yazılıyor.

Risk:

Yeni governance/compliance/reliability katmanı, alttaki açık güvenlik borçlarının üzerine inşa edilmiş oluyor. Bu durumda test sayısı artsa bile canlı kabul verilemez.

Beklenen düzeltme:

- Önce `docs/customer-care/dalga-aeafag-kalite-kapisi-02.md` tamamen kapatılmalı.
- Sonra AH/AI/AJ revizyonu yeniden değerlendirilmelidir.

---

## 3. AH — Governance P0/P1 Bulguları

### P0-1 — Governance UI’da rol atama ve onay işlemleri service-level permission guard olmadan çalışıyor

Dosyalar:

- `app/Livewire/CustomerCare/Governance.php`
- `app/Services/Support/Security/SupportRbacService.php`
- `tests/Feature/CustomerCare/CustomerCareGovernanceTest.php`

Durum:

- `Governance::mount()` admin ve operator rollerine sayfayı açıyor.
- `assignRole()` içinde `manage_roles` veya eşdeğer bir permission guard yok.
- Bu nedenle sayfaya girebilen bir operator, Livewire payload ile kendine veya başkasına `owner/admin` rolü atayabilir.
- `approveRequest()` içinde de onaylayan kişinin ilgili store’da onay yetkisi olup olmadığı doğrulanmıyor; yalnız self-approval engeli var.

Risk:

RBAC dalgasının ana amacı service-level enforcement iken rol atama ve approval gibi en kritik aksiyonlar UI seviyesinde korunuyor gibi duruyor ama servis/aksiyon seviyesinde yetki kontrolü eksik. Bu doğrudan privilege escalation riskidir.

Beklenen düzeltme:

- Permission matrix’e ayrı izinler eklenmeli:
  - `manage_roles`
  - `approve_risk_action`
  - `reject_risk_action`
- `assignRole()`, `approveRequest()`, `rejectRequest()` içinde `SupportRbacService::enforcePermission()` zorunlu olmalı.
- Operator default olarak rol atayamaz/onaylayamaz.
- Testler:
  - Operator route’a girse bile rol atayamaz.
  - Agent/supervisor yetkisiz approval yapamaz.
  - Sadece owner/admin veya tanımlı supervisor permission ile onay yapabilir.

---

### P0-2 — Approval tüketimi append-only değil; approved request siliniyor

Dosya:

- `app/Services/Support/Security/SupportRbacService.php`

Durum:

`enforceApproval()` içinde onay bulununca:

```php
$approvedRequest->delete();
```

ile approval kaydı siliniyor.

Risk:

Governance dalgası append-only karar defteri hedefliyordu. Onay kararının silinmesi audit trail’i yok eder. Kim, neyi, ne zaman onayladı sorusu geriye dönük cevaplanamaz.

Beklenen düzeltme:

- Approval request silinmemeli.
- Kullanıldıysa `status=consumed`, `consumed_at`, `consumed_by`, `consumed_action_ref` gibi append-only alanlar veya ayrı `support_approval_decisions` tablosu kullanılmalı.
- Migration’da veya modelde append-only karar geçmişi korunmalı.
- Test:
  - Approval tüketildikten sonra kayıt silinmez.
  - Aynı approval ikinci kez kullanılamaz.
  - History ekranında consumed/approved karar görünür.

---

## 4. Ortak P0 — Outbound çift gönderim riski

### P0-3 — `sendAgentReply()` aynı dispatch’i iki kez göndermeye çalışıyor

Dosya:

- `app/Services/Support/SupportReplyService.php`

Durum:

`sendAgentReply()` içinde aynı dispatch için iki ardışık çağrı var:

```php
$success = $outboxService->sendDispatch($dispatch);
$success = $outboxService->sendDispatch($dispatch);
```

Risk:

Bu, idempotency doğru çalışmayan veya adapter tarafında terminal state kontrolü zayıf olan kanallarda aynı temsilci cevabının iki kez dış sisteme gönderilmesine neden olabilir. Reliability dalgası açısından bu doğrudan kabul engelidir.

Beklenen düzeltme:

- Çift çağrı kaldırılmalı.
- Test:
  - `sendAgentReply()` bir temsilci cevabı için `SupportOutboxService::sendDispatch()` yalnız bir kez çağırır.
  - Aynı dispatch terminal durumdaysa tekrar gönderilmez.

---

## 5. AI — Compliance P0/P1 Bulguları

### P0-4 — DSR export PII içerdiği halde approval gerektirmiyor ve raw customer id dosya adına yazılıyor

Dosyalar:

- `app/Livewire/CustomerCare/Compliance.php`
- `app/Services/Support/Compliance/CustomerCareComplianceService.php`

Durum:

- `exportDsr()` yalnız `run_compliance` permission kontrolü yapıyor.
- DSR access/export çıktısı raw PII içerdiği halde approval workflow yok.
- Dosya adı `dsr-export-{$dsr->customer_id}.json` şeklinde raw customer id içeriyor.
- Export işlemi için açık bir audit/event kaydı görünmüyor.

Risk:

Data subject access export, bilerek PII içeren yüksek riskli işlemdir. Bu işlem approval ve audit olmadan yapılırsa hem KVKK/GDPR hem de iç denetim açısından kabul edilemez.

Beklenen düzeltme:

- DSR export için `enforceApproval()` veya eşdeğer riskli işlem onayı zorunlu olmalı.
- Export filename raw customer id içermemeli; masked/hash/ref id kullanılmalı.
- Export işlemi append-only audit/event olarak yazılmalı.
- Testler:
  - Approval yokken DSR export indirme başlamaz.
  - Approval sonrası export yapılır ve audit yazılır.
  - Filename raw customer id içermez.

---

### P0-5 — Data lineage ve consent loglarında raw customer id saklanıyor/loglanıyor

Dosyalar:

- `app/Services/Support/Compliance/CustomerCareComplianceService.php`
- `app/Services/Support/Compliance/CustomerCareConsentService.php`
- `database/migrations/2026_07_31_110000_create_support_compliance_tables.php`

Durum:

- `support_data_lineage_events.customer_id` raw customer id saklıyor.
- `CustomerCareConsentService::hasConsent()` log context içinde raw `customer_id` yazıyor.
- Prompt “Data lineage raw webhook payload göstermez” diyordu; fakat müşteri kimliğinin kendisi de PII/identifier kabul edilmelidir.

Risk:

Lineage ve log katmanı uzun süre tutulan operasyonel izdir. Raw müşteri identifier’ı burada kalıcı şekilde tutulması veri minimizasyonu ve sızıntı riskini artırır.

Beklenen düzeltme:

- Lineage için raw customer id yerine hash/token veya masked identifier kullanılmalı.
- Consent block logları raw customer_id yazmamalı.
- UI’da lineage araması gerekiyorsa input raw alınabilir ama saklanan/çıkan değer masked/hash olmalı.
- Test:
  - Lineage event raw customer id içermez.
  - Consent block log context raw customer id içermez.

---

## 6. AJ — Reliability P0/P1 Bulguları

### P0-6 — Dead-letter replay command mutating default çalışıyor ve approval/RBAC enforce etmiyor

Dosya:

- `app/Console/Commands/CustomerCareReplayDeadlettersCommand.php`

Durum:

Komut imzası:

```php
customer-care:replay-deadletters {--store= : Store ID} {--type=dispatch : dispatch or integration} {--dry-run : Dry run only}
```

`--dry-run` verilmezse komut doğrudan mutasyon yapıyor:

- exhausted dispatch kayıtlarını `pending` yapıyor,
- attempt count sıfırlıyor,
- pending dispatch processing tetikliyor,
- integration dead-letter kayıtlarını pending yapıp tekrar gönderiyor.

Komut seviyesinde actor/RBAC/approval yok. Livewire UI approval istese bile CLI doğrudan bypass edebiliyor.

Risk:

Dead-letter replay yüksek riskli canlı operasyonudur. Varsayılan dry-run olmalı ve gerçek replay explicit `--execute` gibi bilinçli flag + system actor/approval denetimi gerektirmelidir.

Beklenen düzeltme:

- Komut dry-run default olmalı; mutasyon için `--execute` veya benzeri zorunlu olmalı.
- CLI için deterministic system actor ve approval kontrolü netleştirilmeli.
- Yetkisiz/approval yoksa replay yapmamalı.
- Test:
  - Flag verilmeden command hiçbir kayıt değiştirmez.
  - Approval yokken `--execute` replay yapmaz.
  - Approval varken sadece selected store kayıtları replay edilir.

---

### P0-7 — Integration dead-letter replay plaintext secret fallback’i geri getiriyor

Dosya:

- `app/Console/Commands/CustomerCareReplayDeadlettersCommand.php`

Durum:

Integration replay içinde secret çözümlemesi yine şu pattern ile çalışıyor:

```php
try {
    $secret = Crypt::decryptString($rawSecret);
} catch (DecryptException $e) {
    $secret = $rawSecret;
}
```

Bu, AE/AF/AG Kalite Kapısı 02’de kapatılması istenen plaintext fallback riskini reliability komutuna tekrar taşıyor.

Beklenen düzeltme:

- Replay path sadece decrypt edilebilen encrypted secret kabul etmeli.
- Plaintext/invalid secret ile HTTP çağrısı yapmamalı.
- Test:
  - Integration replay plaintext secret ile HTTP çağrısı yapmaz.
  - Invalid ciphertext ile fail-closed.

---

### P1-1 — Rate limit unknown channel’da permissive fallback kullanıyor ve key/channel_type karışıklığı var

Dosyalar:

- `app/Services/Support/Reliability/CustomerCareRateLimiter.php`
- `app/Services/Support/SupportOutboxService.php`
- `app/Livewire/CustomerCare/Reliability.php`
- `app/Console/Commands/CustomerCareRateLimitReportCommand.php`

Durum:

- `SupportOutboxService` rate limit kontrolüne `$channel->key` gönderiyor.
- Rate limiter config anahtarları ise `whatsapp`, `trendyol`, `meta`, `google_reviews`, `web_chat`.
- `Reliability` UI ve rate-limit command bazı yerlerde `channel_type`, bazı yerlerde `key` kullanıyor.
- Bilinmeyen kanal config’inde güvenli fail-closed yerine varsayılan `100/3600` fallback var.

Risk:

Kanal key/type eşleşmesi oturmazsa limit yanlış sayaçtan okunur. Bilinmeyen kanal ise açık limit ile gönderime devam eder; prompt “limit yoksa limitsiz varsayma; explicit config gerekir” diyordu.

Beklenen düzeltme:

- Tek canonical rate-limit key belirlenmeli.
- `SupportChannel` key/type mapping açık ve testli olmalı.
- Unknown channel için fail-closed veya explicit config required davranışı olmalı.
- Test:
  - Unknown channel rate limit explicit config olmadan external send yapmaz.
  - WhatsApp/trendyol/meta/google/web_chat gerçek key mapping ile sayılır.

---

### P1-2 — Queue health veri yokken “normal/healthy” gibi görünüyor

Dosya:

- `app/Services/Support/Reliability/CustomerCareQueueHealthService.php`

Durum:

Hiç veri yoksa servis:

```php
['backpressure' => false]
```

dönüyor. Bu, veri yok / unknown ile healthy/normal durumunu karıştırabilir.

Beklenen düzeltme:

- `status: unknown|healthy|backpressure` gibi ayrık durum dönmeli.
- UI “veri yok” durumunu “normal” gibi göstermemeli.
- Test:
  - Hiç queue verisi yokken status unknown/no_data olur.

---

## 7. P1 — Kanıt Paketleri Eksik

Mevcut dosya kontrolünde aşağıdaki kanıt paketleri bulunmadı:

- `docs/customer-care/dalga-ah-kanit-paketi.md`
- `docs/customer-care/dalga-ai-kanit-paketi.md`
- `docs/customer-care/dalga-aj-kanit-paketi.md`

Beklenen düzeltme:

- Her dalga için ayrı kanıt paketi oluşturulmalı.
- İçerik:
  - değişen dosyalar,
  - migration apply/rollback,
  - route/command/scheduler çıktıları,
  - test listesi,
  - kalan riskler,
  - rollback notları.

---

## 8. Test Kapsamı Eksik

Mevcut hedef test sonucu:

```text
13 passed (33 assertions)
```

Bu üç kurumsal dalga için oldukça dar. En az şu testler eklenmeli:

- Operator role assignment yapamaz.
- Operator/supervisor yetkisiz approval yapamaz.
- Approval consumed olduktan sonra silinmez ve tekrar kullanılamaz.
- `sendAgentReply()` dispatch’i tek kez gönderir.
- DSR export approval yokken engellenir.
- DSR export filename raw customer id içermez.
- Lineage event raw customer id içermez.
- Consent block log raw customer id içermez.
- Dead-letter replay command default dry-run’dır.
- Dead-letter replay approval yokken `--execute` çalışmaz.
- Integration replay plaintext/invalid secret ile HTTP çağrısı yapmaz.
- Unknown channel rate limit explicit config olmadan fail-closed.
- Queue health no-data durumunu healthy gibi göstermez.

---

## 9. Kalite Kapısı Kararı

Dalga AH/AI/AJ şu haliyle **kabul edilmedi**.

Bu dalgalar doğru ürün yönünü temsil ediyor; özellikle RBAC, compliance ve reliability yüzeyleri ZOLM’ü kurumsal SaaS seviyesine taşıyacak. Ancak mevcut implementasyon, kritik operasyonlarda UI-only güvenlik ve mutating command riskleri taşıyor. Bu katman “gösterge paneli” değil, canlı sistem frenleri olduğu için tolerans düşük tutulmalıdır.

---

## 10. Antigravity’ye Verilecek Kısa Komut

```text
/Volumes/TWINMOS/zolm reposunda Dalga AH/AI/AJ Kalite Kapısı 01 revizyonlarını uygula.

Önce şu dosyayı tamamen oku ve içindeki P0/P1 maddelerini eksiksiz düzelt:

/Volumes/TWINMOS/zolm/docs/customer-care/dalga-ahaiaj-kalite-kapisi-01.md

Önce açık AE/AF/AG Kalite Kapısı 02 P0 maddelerinin gerçekten kapandığını doğrula; açık kaldıysa AH/AI/AJ revizyonuna geçme, durumu raporla.

Yalnız AH/AI/AJ Kalite Kapısı 01 kapsamını uygula.
Kapsam dışındaki mevcut kullanıcı değişikliklerine dokunma.
Commit, push veya branch değişikliği yapma.
Yeni dalgaya geçme.

Zorunlu kanıt:
- git diff --check
- AH/AI/AJ hedef testleri
- CustomerCare testleri
- full test suite
- npm run build
- route:list --name=customer-care
- artisan list customer-care --raw
- schedule:list
- migration apply/rollback kanıtı
- güncel dalga-ah/ai/aj kanıt paketleri
- güncel walkthrough

Kanıt paketini verdikten sonra dur.
```

