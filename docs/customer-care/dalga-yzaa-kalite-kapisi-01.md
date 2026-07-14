# ZOLM AI Müşteri İletişim Merkezi — Dalga Y/Z/AA Kalite Kapısı 01

**Tarih:** 2026-07-13  
**İnceleyen:** Codex — Baş mühendis  
**Karar:** ❌ **Kabul edilmedi — P0/P1 revizyon gerekli**

Dalga Y/Z/AA; onboarding, plan/usage metering ve admin/launch raporlama katmanlarını eklemiş durumda. Testlerin yeşil geçmesi ve UI/route/command yüzeylerinin oluşması olumlu. Ancak lansman raporu ve kota altyapısında pilot öncesi kapatılması gereken güvenlik ve doğruluk açıkları var.

Bu kalite kapısı yalnız Dalga Y/Z/AA kapsamını inceler. Önceki `dalga-stu-vwx-kalite-kapisi-01.md` içindeki public kanal blokajları ayrıca geçerliliğini korur.

---

## Çalıştırılan Kontroller

```bash
git diff --check
npm run build
./vendor/bin/sail artisan route:list --name=customer-care
./vendor/bin/sail artisan list customer-care --raw
./vendor/bin/sail artisan schedule:list
./vendor/bin/sail artisan test tests/Feature/CustomerCare --no-coverage --compact
./vendor/bin/sail artisan test \
  tests/Feature/CustomerCare/CustomerCareOnboardingTest.php \
  tests/Feature/CustomerCare/CustomerCareUsageTest.php \
  tests/Feature/CustomerCare/CustomerCareAdminCenterTest.php \
  tests/Feature/CustomerCare/CustomerCareSettingsTest.php \
  --no-coverage --compact
```

Sonuç:

- `git diff --check`: ✅ temiz
- `npm run build`: ✅ başarılı
- Customer Care testleri: ✅ `205 passed / 787 assertions`
- Y/Z/AA hedef testleri: ✅ `28 passed / 66 assertions`
- Route listesi: ✅ onboarding ve admin rotaları görünüyor
- Command listesi: ✅ `customer-care:usage-report` ve `customer-care:pilot-launch-report` görünüyor
- Scheduler listesi: ✅ mevcut customer-care pilot monitor kaydı duruyor

Not: Testler yeşil; fakat aşağıdaki P0/P1 davranışları test kapsamına alınmamış.

---

## P0-1 — Pilot launch report ham `last_error` değerlerini Markdown’a yazıyor

**Dosya:** `app/Console/Commands/CustomerCarePilotLaunchReportCommand.php`

Komut, son dispatch hatalarını doğrudan rapora yazıyor:

```php
$latestErrors = SupportDispatch::where('status', 'failed')
    ->whereHas('conversation', fn($q) => $q->where('store_id', $storeId))
    ->latest()
    ->limit(5)
    ->pluck('last_error')
    ->filter()
    ->toArray();

foreach ($latestErrors as $idx => $err) {
    $md .= ($idx + 1) . ". {$err}\n";
}
```

`last_error` dış API hata gövdesi, müşteri mesajı, telefon, e-posta, adres, sipariş no veya webhook hata detayları içerebilir. Rapor `docs/customer-care/pilot-launch-report-store-{id}.md` altına kalıcı dosya olarak yazıldığı için ham PII sızıntısı riski oluşur.

### Beklenen düzeltme

- `PiiRedactor::maskPii()` launch report içindeki tüm serbest metin alanlarında uygulanmalı.
- XML kontrol karakterleri temizlenmeli.
- Markdown tablo/başlık kırabilecek pipe/newline gibi karakterler normalize edilmeli.
- Hata detayları mümkünse kısa ve redakte edilmiş özet olarak yazılmalı.

### Zorunlu test

- Failed `SupportDispatch.last_error` içine telefon/e-posta/TCKN ve XML kontrol karakteri koy.
- `customer-care:pilot-launch-report --store=ID` çalıştır.
- Oluşan Markdown içinde ham PII olmadığını, XML kontrol karakteri kalmadığını ve redakte edilmiş değerlerin bulunduğunu doğrula.

---

## P1-1 — Store audit export, store dışı global action kayıtlarını da alabilir

**Dosya:** `app/Livewire/CustomerCare/AdminCenter.php`

Mevcut sorgu:

```php
$actions = SupportAgentAction::whereHas('conversation', function ($q) use ($storeId) {
    $q->where('store_id', $storeId);
})->orWhereNull('conversation_id')->latest()->get();
```

`orWhereNull('conversation_id')` tüm global/system action kayıtlarını, seçili store ile ilişkisi olup olmadığına bakmadan store export’una dahil edebilir. Bu kayıtların `details_json` içinde başka mağazalara ait kanal, store, actor veya operasyon detayları bulunabilir.

Admin kullanıcının geniş yetkili olması, store bazlı export dosyasına başka mağaza kayıtlarının karışmasını haklı çıkarmaz. Dosya adı ve UI aksiyonu store-scoped export vaat ediyor.

### Beklenen düzeltme

Global action kayıtları dahil edilecekse yalnız seçili store ile deterministik ilişkisi olanlar alınmalı. Örnek seçenekler:

- `conversation_id` üzerinden store scoped kayıtlar,
- `details_json->store_id = $storeId`,
- `details_json->channel_id` üzerinden seçili store’a ait kanal,
- veya global action kayıtları store export dışında ayrı “global audit export” olarak ele alınmalı.

### Zorunlu test

- Store A ve Store B için iki global `SupportAgentAction` oluştur.
- Store A export’u alındığında Store B global action detayının CSV’de bulunmadığını doğrula.
- Store A ile ilişkili global action varsa dahil olduğunu doğrula.

---

## P1-2 — Usage service bilinmeyen metriklerde sessiz şekilde 99.999 limit veriyor

**Dosya:** `app/Services/Support/CustomerCareUsageService.php`

Mevcut davranış:

```php
default => 99999,
```

Bilinmeyen veya typo içeren bir metrik adı, quota sisteminde sessizce çok yüksek limit alıyor. Bu, ileride `auto_reply` / `auto_replies`, `ai_draft` / `ai_drafts` gibi küçük isim hatalarında kota enforcement’ın fark edilmeden bypass edilmesine yol açar.

### Beklenen düzeltme

- Desteklenen metrikler explicit allowlist olmalı.
- Bilinmeyen metrik için `InvalidArgumentException` veya fail-closed `allowed=false` dönülmeli.
- `agent_replies` gibi limit dışı ama raporlanacak metrikler explicit şekilde `unlimited` veya `null limit` semantiğiyle tanımlanmalı; `99999` sihirli değeri kullanılmamalı.

### Zorunlu testler

- `checkLimit($storeId, 'unknown_metric')` fail-closed davranır veya exception fırlatır.
- `agent_replies` raporda görünür ama auto-reply kotasından etkilenmez.
- Typo metrik kota bypass edemez.

---

## P1-3 — Usage ledger yalnız mutable summary tablo; audit edilebilir event yok

**Dosyalar:**

- `database/migrations/2026_07_28_110000_create_support_usages_table.php`
- `app/Services/Support/CustomerCareUsageService.php`

Dalga Z promptu “append-only veya özet tablo + event table yaklaşımı” istiyordu. Mevcut uygulama yalnız `support_usages` özet tablosunu artırıyor.

Bu MVP için kota sayaçlarını çalıştırır; fakat SaaS metering ve müşteri itirazı durumunda “hangi olay bu kotayı yaktı?” sorusuna cevap vermez.

### Beklenen düzeltme

Seçeneklerden biri seçilmeli:

1. `support_usage_events` append-only tablo eklenir; her increment için event kaydı yazılır.
2. Eğer bu fazda event table ertelenecekse ADR/kanıt paketinde açıkça “MVP summary-only” kararı yazılır ve mutable sayacın sınırlılığı kabul edilir.

Baş mühendis tercihi: küçük bir `support_usage_events` tablosu eklenmesi.

### Zorunlu testler

- AI draft success → summary count artar ve usage event yazılır.
- Blocked/failed auto reply → event yazılmaz.
- Manual reply → `agent_replies` event’i yazılır ama auto reply limitinden etkilenmez.

---

## P1-4 — Onboarding “automatic çalışıyor” mesajı gerçek kanal ayarıyla senkron değil

**Dosyalar:**

- `app/Livewire/CustomerCare/Onboarding.php`
- `resources/views/livewire/customer-care/onboarding.blade.php`

Onboarding tamamlandığında `SupportOnboardingState.recommended_mode` güncelleniyor. Ancak kanalın gerçek `config_json.automation_settings.ai_mode` değeri güncellenmiyor.

Blade tarafında ise:

```blade
Kurulum başarıyla tamamlandı. Otomasyon şu an <strong>{{ strtoupper($recommendedMode) }}</strong> modunda çalışıyor.
```

Bu ifade kullanıcının otomasyonun gerçekten aktive edildiğini sanmasına yol açabilir. Mevcut davranış “önerilen mod kaydedildi” ise UI metni bunu söylemeli; eğer gerçekten aktivasyon hedefleniyorsa kanal ayarı da güvenli gate’lerden geçerek yazılmalı.

### Beklenen düzeltme

Seçeneklerden biri uygulanmalı:

1. Onboarding yalnız öneri kaydediyorsa UI metni “önerilen mod” olarak değiştirilmeli.
2. Onboarding gerçek aktivasyon yapacaksa Settings’teki aynı gate mantığı tekrar kullanılarak kanal `automation_settings.ai_mode` güncellenmeli.

### Zorunlu test

- Onboarding completed + automatic seçildiğinde beklenen davranış net test edilmeli:
  - ya kanal config’i automatic olur,
  - ya da UI/state bunun yalnız öneri olduğunu açıkça gösterir.

---

## P2-1 — Launch report kapsamı route/command özetini eksik veriyor

**Dosya:** `app/Console/Commands/CustomerCarePilotLaunchReportCommand.php`

Prompt, launch report içinde readiness + route/command + eval + quota + circuit + policy + outbox özetini istiyordu. Mevcut rapor readiness, circuit, quota, operasyon ve son hata bölümlerini içeriyor; route/command ve eval bölümleri ayrı başlık olarak görünmüyor.

### Beklenen düzeltme

- Report’a kısa “Route & Command Inventory” bölümü eklenmeli.
- Eval bölümü readiness içine gömülmek yerine ayrı özet olarak verilmeli:
  - son eval skoru,
  - run tarihi,
  - pass/fail,
  - stale durumu.

---

## Olumlu Notlar

- Onboarding route feature flag ile kapalı geliyor.
- Onboarding store selection IDOR testi mevcut.
- Marka sesi adımında `BrandVoiceService` üzerinden PII redaction ve prompt injection guard korunmuş.
- Readiness fail ise automatic mode tamamlanamıyor.
- AI draft / auto reply / knowledge suggestion kota çağrıları ana akışlara bağlanmış.
- Manual agent reply, auto reply kotasından etkilenmiyor.
- Admin route admin role + feature flag ile korunmuş.
- CSV audit export BOM ve PII redaction açısından doğru yönde.
- Build ve Customer Care testleri yeşil.

---

## Antigravity’ye Verilecek Revizyon Komutu

```text
/Volumes/TWINMOS/zolm reposunda Dalga Y/Z/AA Kalite Kapısı 01 revizyonlarını uygula.

Önce şu dosyayı tamamen oku ve içindeki P0/P1 maddeleri eksiksiz düzelt:

/Volumes/TWINMOS/zolm/docs/customer-care/dalga-yzaa-kalite-kapisi-01.md

Kurallar:
- Commit, push veya branch değişikliği yapma.
- Kapsam dışındaki mevcut kullanıcı değişikliklerine dokunma.
- Feature flag varsayılanlarını kapalı tut.
- Otomatik yanıtı global olarak açma.
- Testlerde canlı dış API çağrısı yapma.
- Launch report içine ham PII, ham API hata gövdesi veya XML kontrol karakteri yazma.
- Store audit export seçili mağaza dışı global action kayıtlarını içermesin.
- Usage service bilinmeyen metriklerde sessiz 99999 limit vermesin; explicit allowlist/fail-closed kullan.
- Usage metering için append-only event ledger ekle veya gerekçeli ADR/kanıt kararıyla summary-only sınırlılığını açıkça belgele.
- Onboarding automatic mesajı gerçek kanal ayarıyla senkron olsun veya UI metni “önerilen mod” olarak düzeltilsin.
- Dalga AB/AC veya başka kapsama geçme.

Revizyon sonunda şu kanıtları ver:
- git status --short
- git diff --check
- npm run build
- ./vendor/bin/sail artisan route:list --name=customer-care
- ./vendor/bin/sail artisan list customer-care --raw
- ./vendor/bin/sail artisan schedule:list
- ./vendor/bin/sail artisan test tests/Feature/CustomerCare --no-coverage --compact
- ./vendor/bin/sail artisan test --no-coverage --compact
- Düzeltme dosya-test eşleşmeleri

Kanıt paketlerini güncelle:
- docs/customer-care/dalga-y-kanit-paketi.md
- docs/customer-care/dalga-z-kanit-paketi.md
- docs/customer-care/dalga-aa-kanit-paketi.md
- walkthrough.md

İş bitince dur; kalite kapısı onayını Codex baş mühendis incelemesine bırak.
```

---

## Sonuç

Dalga Y/Z/AA iyi bir ürünleşme katmanı eklemiş; ancak lansman raporu ve usage metering temelinde revizyon gerekiyor. P0/P1 maddeler kapanmadan pilot launch veya admin raporlama kabulü verilmemelidir.
