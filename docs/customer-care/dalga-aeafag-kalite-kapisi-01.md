# ZOLM AI Müşteri İletişim Merkezi — Dalga AE/AF/AG Kalite Kapısı 01

Tarih: 2026-07-13  
İnceleyen: Codex Baş Mühendis  
Karar: **RED / REVİZYON GEREKLİ**

Bu kalite kapısı, Dalga AE (Kalite Denetimi & Skor Kartları), Dalga AF (Enterprise Integration Hub) ve Dalga AG (Canlı Observability / Model Ops) çıktılarının bağımsız teknik incelemesidir.

Önemli not: Dalga AB/AC/AD kalite kapısında açık P0/P1 maddeleri bulunduğu için, AE/AF/AG kodu kendi içinde düzeltilse bile genel pilot/canlı onayı henüz verilemez. Bu rapor yalnız AE/AF/AG kapsamındaki kabul engellerini listeler.

---

## 1. Çalıştırılan Kontroller

```bash
git diff --check
```

Sonuç: Temiz.

```bash
./vendor/bin/sail artisan test \
  tests/Feature/CustomerCare/CustomerCareQualityTest.php \
  tests/Feature/CustomerCare/CustomerCareIntegrationHubTest.php \
  tests/Feature/CustomerCare/CustomerCareOpsTest.php \
  --no-coverage --compact
```

Sonuç:

```text
11 passed (30 assertions)
```

Dalga hedef testleri yeşil; fakat test kapsamı aşağıdaki P0/P1 riskleri yakalamayacak kadar dar.

---

## 2. P0 Kabul Engelleri

### P0-1 — Quality/Ops ekranları feature flag ile fail-closed korunmuyor

Dosyalar:

- `routes/web.php`
- `config/customer-care.php`
- `app/Livewire/CustomerCare/QualityCenter.php`
- `app/Livewire/CustomerCare/OpsCenter.php`

Durum:

- `/customer-care/quality` rotasında `customer-care.feature:*` middleware yok.
- `/customer-care/ops` rotasında `customer-care.feature:*` middleware yok.
- `config/customer-care.php` içinde `quality_center_enabled` ve `ops_center_enabled` gibi varsayılan kapalı flag’ler yok.
- `/customer-care/admin` rotası `admin_center_enabled` middleware’i kullanıyor ancak config tarafında bu flag de görünmüyor.

Risk:

Bu modüller admin-only olsa bile canlı sistemde yeni yüzeyler varsayılan kapalı açılmalı. Projenin müşteri iletişim merkezi mimari standardı “feature flag default false + route middleware fail-closed” şeklinde ilerliyor. Quality/Ops ekranları bu standardı kırıyor.

Beklenen düzeltme:

- `config/customer-care.php` içine varsayılanı `false` olacak şekilde en az şu bayraklar eklenmeli:
  - `quality_center_enabled`
  - `ops_center_enabled`
  - mevcut rota kullanıyorsa `admin_center_enabled`
- `/customer-care/quality` ve `/customer-care/ops` rotaları güvenli feature middleware ile korunmalı.
- Testler:
  - Flag kapalıyken rota 404 dönmeli.
  - Auth yokken login redirect.
  - Admin değilken 403.
  - Flag açık + admin iken 200.

---

### P0-2 — Integration Hub webhook secret düz metin saklanıyor ve boş secret ile HMAC üretilebiliyor

Dosyalar:

- `app/Livewire/CustomerCare/Integrations.php`
- `app/Services/Support/Integration/CustomerCareIntegrationHubService.php`
- `database/migrations/2026_07_30_110000_create_integration_hub_tables.php`

Durum:

- Webhook secret `SupportChannel.config_json.webhook_secret` alanına düz JSON olarak yazılıyor.
- `CustomerCareIntegrationHubService::dispatchEvent()` bu secret’ı config içinden okuyor.
- Secret yoksa akış fail-closed olmuyor; `deliver($delivery, '')` ile boş secret üzerinden imza üretilebiliyor.
- Bu durum HMAC güvenliğini “varmış gibi” gösteren sahte güvenlik üretir.

Risk:

Enterprise Integration Hub dış sistemlere müşteri verisi taşıyan çıkış kapısıdır. Webhook secret/token düz metinde tutulmamalı ve eksik secret durumunda kesinlikle gönderim denenmemelidir.

Beklenen düzeltme:

- Secret/token için şifreli saklama kullanılmalı. Mevcut proje yapısına en uygun seçenek:
  - ayrı encrypted alan,
  - encrypted cast,
  - veya mevcut `IntegrationConnection` güvenli credential pattern’inin kullanımı.
- Secret eksik/boş ise:
  - event/delivery fail-closed olmalı,
  - HTTP isteği yapılmamalı,
  - delivery `failed` veya `dead_letter` durumuna güvenli sebep ile çekilmeli.
- Log/audit kayıtlarında webhook secret, header, imza ve ham response body sızmamalı.
- Testler:
  - Secret düz config JSON içinde saklanmaz.
  - Secret yoksa HTTP çağrısı yapılmaz.
  - HMAC header gerçek secret ile üretilir.
  - Log/last_error PII ve secret içermez.

---

### P0-3 — Budget / provider health guard gerçek otomatik gönderim yoluna bağlanmamış

Dosyalar:

- `app/Services/Support/AI/CustomerCareAiOrchestrator.php`
- `app/Services/Support/SupportReplyService.php`
- `app/Services/Support/CustomerCareAiProviderHealthService.php`
- `tests/Feature/CustomerCare/CustomerCareOpsTest.php`

Durum:

- Budget ve provider health kontrolü `CustomerCareAiOrchestrator::generateDraft()` içinde var.
- Ancak gerçek otomatik cevap gönderim yolu olan `SupportReplyService::sendAiReply()` içinde aynı guard görünmüyor.
- Mevcut test adı `test_budget_exceeded_blocks_auto_reply_but_allows_manual` olsa da test doğrudan draft üretimini çağırıyor; gerçek `sendAiReply()` yolunu doğrulamıyor.

Risk:

Canlıda otomatik yanıtlar draft üretim yolunu bypass eden bir kod patikasından ilerlerse günlük/aylık bütçe aşılmışken AI cevabı gönderebilir. Model Ops dalgasının ana amacı bütçe ve provider sağlığını canlı otomasyon kapısına bağlamaktır; sadece draft tarafındaki kontrol yeterli değil.

Beklenen düzeltme:

- `SupportReplyService::sendAiReply()` içinde, dispatch/message yaratılmadan önce:
  - provider health,
  - daily/monthly budget cap,
  - unknown-cost policy,
  - circuit breaker/automation gate
  aynı karar zincirinde fail-closed çalışmalı.
- Manual temsilci cevabı bütçe limitinden etkilenmemeli.
- Testler:
  - Budget exceeded + `sendAiReply()` → `false`, yeni `SupportMessage` yok, dispatch yok.
  - Provider unhealthy + `sendAiReply()` → fail-closed.
  - Budget exceeded + `sendAgentReply()` → izinli.

---

### P0-4 — Quality Center PII ve cross-store güvenlik sınırları yetersiz

Dosya:

- `app/Livewire/CustomerCare/QualityCenter.php`

Durum:

- `selectItem()` metodu `SupportAiRun::find($id)` ve `SupportMessage::find($id)` kullanıyor; seçilen kaydın `selectedStoreId` mağazasına ait olduğu doğrulanmıyor.
- Preview alanında `prompt_raw`, `response_raw` veya mesaj gövdesi PII redaction yapılmadan gösteriliyor.
- `submitReview()` review kaydını `selectedStoreId` ile oluşturuyor, fakat seçili message/conversation/AI run gerçekten bu mağazaya ait mi doğrulamıyor.
- `decision === 'kb_candidate'` durumunda `SupportKnowledgeSuggestion::create()` içine `proposed_answer` olarak mesaj gövdesi raw yazılıyor.

Risk:

Admin ekranı bile olsa kalite denetimi PII’ye en yakın yüzeylerden biridir. Cross-store yanlış eşleştirme ve raw PII ile bilgi önerisi üretimi kabul edilemez. Daha önceki dalgalarda PII maskeleme ve tenant izolasyonu temel kabul kriteri haline gelmişti.

Beklenen düzeltme:

- `selectItem()` store-scoped resolver kullanmalı.
- AI run/message/conversation seçimi `selectedStoreId` ile doğrulanmalı.
- Preview metinleri `PiiRedactor` ile maskelenmeli.
- `kb_candidate` önerisi oluşturulurken title/content/proposed_answer PII-redacted olmalı.
- Cross-store mismatch durumunda fail-closed davranmalı.
- Testler:
  - Store A ekranında Store B message/AI run seçilemez.
  - Preview raw telefon/e-posta/TCKN/isim göstermez.
  - KB candidate raw PII yazmaz.

---

## 3. P1 Revizyon Maddeleri

### P1-1 — Integration delivery retry store izolasyonu ve idempotency DB garantisi eksik

Dosyalar:

- `app/Livewire/CustomerCare/Integrations.php`
- `app/Services/Support/Integration/CustomerCareIntegrationHubService.php`
- `database/migrations/2026_07_30_110000_create_integration_hub_tables.php`

Durum:

- `retryDelivery(int $deliveryId)` doğrudan `SupportIntegrationDelivery::find($deliveryId)` kullanıyor.
- Delivery’nin bağlı event’inin `selectedStoreId` mağazasına ait olduğu doğrulanmıyor.
- `dispatchEvent()` idempotency kontrolünü uygulama katmanında yapıyor; migration’da `(store_id, idempotency_key)` unique index yok.

Beklenen düzeltme:

- Retry işlemi store-scoped yapılmalı.
- Cross-store delivery retry fail-closed olmalı.
- `support_integration_events` için DB seviyesinde unique `(store_id, idempotency_key)` eklenmeli.
- Race condition testi eklenmeli.

---

### P1-2 — Kalite örnekleme komutu sahte onaylanmış review üretiyor

Dosya:

- `app/Console/Commands/CustomerCareSampleQualityReviewsCommand.php`

Durum:

- Komut `--execute` ile çalıştırıldığında placeholder içerikli, `approved` kararına sahip kalite review kayıtları oluşturuyor.
- Bu, kalite defterini gerçek insan/denetim kararı gibi gösteren yapay veri üretir.

Beklenen düzeltme:

- Komut “review candidate / sampling queue” üretmeli; otomatik `approved` review üretmemeli.
- Persist edilen kayıtlar `pending_review` veya eşdeğer güvenli durumla başlamalı.
- Dry-run varsayılanı korunmalı.
- Test: `--execute` approved/score fabricated review yazmaz.

---

### P1-3 — Ops metriklerinde yanlış isimlendirme ve sahte sıfır maliyet riski var

Dosyalar:

- `app/Livewire/CustomerCare/OpsCenter.php`
- `app/Services/Support/CustomerCareAiProviderHealthService.php`
- `app/Console/Commands/CustomerCareRecomputeAiCostsCommand.php`
- `database/migrations/2026_07_30_120000_create_observability_ledger_tables.php`

Durum:

- OpsCenter “dispatch failure rate” metriğini `SupportAiRun` başarısızlıklarından hesaplıyor; bu dispatch failure değil AI run failure’dır.
- Cost event kayıtlarında maliyet tahmini `null` olabilir; buna rağmen toplam maliyet `0.0000` gibi gösterilirse “bilinmeyen maliyet” sahte sıfır olarak raporlanır.
- Cost event ledger’ı doğrudan `support_ai_run_id` ile ilişkilendirilmiyor; recompute komutu `created_at` üzerinden `updateOrCreate` yapıyor. Aynı zaman damgasına sahip birden fazla run’da çakışma/ezme riski var.

Beklenen düzeltme:

- Metrik adı ve veri kaynağı tutarlı olmalı:
  - AI run failure rate ayrı,
  - dispatch failure rate gerçek dispatch tablosundan ayrı hesaplanmalı.
- Unknown cost, zero cost gibi gösterilmemeli.
- `support_ai_cost_events` içinde `support_ai_run_id` nullable/unique FK veya eşdeğer deterministik kaynak referansı olmalı.
- Recompute komutu timestamp identity kullanmamalı.

---

### P1-4 — AE/AF/AG kanıt paketleri eksik

Mevcut doküman kontrolünde aşağıdaki dosyalar görünmedi:

- `docs/customer-care/dalga-ae-kanit-paketi.md`
- `docs/customer-care/dalga-af-kanit-paketi.md`
- `docs/customer-care/dalga-ag-kanit-paketi.md`

Beklenen düzeltme:

Her dalga için ayrı kanıt paketi yazılmalı:

- değişen dosyalar,
- migration/rollback kanıtı,
- route/command/scheduler çıktısı,
- test isimleri ve sonuçları,
- kabul edilen riskler,
- rollback notları.

---

### P1-5 — Test kapsamı canlı riskleri yakalamıyor

Mevcut hedef test sonucu:

```text
11 passed (30 assertions)
```

Bu üç dalga için çok düşük kapsama işaret ediyor. Şu senaryolar eklenmeli:

- Quality/Ops feature flag off route 404.
- Quality Center cross-store AI run/message seçimi engellenir.
- Quality KB candidate PII redaction.
- Integration Hub secret encrypted / raw config içinde yok.
- Empty secret ile outbound HTTP çağrısı yapılmaz.
- Retry delivery cross-store engellenir.
- DB idempotency unique constraint.
- Budget exceeded `sendAiReply()` gerçek yolunu durdurur.
- Provider unhealthy `sendAiReply()` gerçek yolunu durdurur.
- Manual agent reply budget limitinden etkilenmez.
- Unknown cost UI’da `$0` gibi gösterilmez.

---

## 4. Olumlu Bulgular

- Yeni modül ayrımı genel olarak doğru: Quality, Integration Hub ve Ops yüzeyleri bağımsız düşünülmüş.
- `git diff --check` temiz.
- Hedef testler mevcut haliyle yeşil.
- Integration Hub için retry/dead-letter niyeti doğru yönde.
- Provider health ve budget kavramlarının orkestratöre eklenmesi iyi bir başlangıç.
- PII redaction servisine isim maskeleme eklenmesi doğru yönde.

---

## 5. Kalite Kapısı Kararı

Dalga AE/AF/AG şu haliyle **kabul edilmedi**.

Revizyon sonrası beklenen minimum kanıt:

```bash
git diff --check
./vendor/bin/sail artisan test tests/Feature/CustomerCare --no-coverage --compact
./vendor/bin/sail artisan test --no-coverage --compact
npm run build
./vendor/bin/sail artisan route:list --name=customer-care
./vendor/bin/sail artisan list customer-care --raw
```

Ek olarak migration uygulanma ve rollback kanıtları kanıt paketlerine yazılmalı.

---

## 6. Antigravity’ye Verilecek Kısa Komut

```text
/Volumes/TWINMOS/zolm reposunda Dalga AE/AF/AG Kalite Kapısı 01 revizyonlarını uygula.

Önce şu dosyayı tamamen oku ve içindeki P0/P1 maddelerini eksiksiz düzelt:

/Volumes/TWINMOS/zolm/docs/customer-care/dalga-aeafag-kalite-kapisi-01.md

Yalnız AE/AF/AG revizyon kapsamını uygula.
AB/AC/AD açık kalite kapısı maddelerine dokunma; onlar ayrı revizyon konusudur.
Kapsam dışındaki mevcut kullanıcı değişikliklerine dokunma.
Commit, push veya branch değişikliği yapma.
Yeni dalgaya geçme.

Zorunlu kanıt:
- git diff --check
- CustomerCare testleri
- full test suite
- npm run build
- route:list --name=customer-care
- artisan list customer-care --raw
- migration/rollback kanıtı
- güncel dalga-ae/af/ag kanıt paketleri

Kanıt paketini verdikten sonra dur.
```

