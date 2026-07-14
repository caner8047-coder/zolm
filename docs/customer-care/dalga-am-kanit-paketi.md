# Dalga AM Kanıt Paketi — Knowledge / Policy / Prompt Release Management

## 1. Değiştirilen/Eklenen Dosyalar
- [`database/migrations/2026_08_01_120000_create_support_release_tables.php`](../../database/migrations/2026_08_01_120000_create_support_release_tables.php): Release packages, items, events, ve artifact versions tabloları.
- [`app/Models/SupportReleasePackage.php`](../../app/Models/SupportReleasePackage.php): Model.
- [`app/Models/SupportReleasePackageItem.php`](../../app/Models/SupportReleasePackageItem.php): Model.
- [`app/Models/SupportReleaseEvent.php`](../../app/Models/SupportReleaseEvent.php): Model.
- [`app/Models/SupportArtifactVersion.php`](../../app/Models/SupportArtifactVersion.php): Model.
- [`app/Services/Support/CustomerCareReleaseService.php`](../../app/Services/Support/CustomerCareReleaseService.php): Lifecycle yönetimi, preflight kontrolleri (PII, Prompt Injection, Golden Eval) ve rollback.
- [`app/Console/Commands/CustomerCareReleasePreflightCommand.php`](../../app/Console/Commands/CustomerCareReleasePreflightCommand.php): `customer-care:release-preflight` komutu.
- [`app/Console/Commands/CustomerCareReleaseRollbackCommand.php`](../../app/Console/Commands/CustomerCareReleaseRollbackCommand.php): `customer-care:release-rollback` komutu.
- [`app/Livewire/CustomerCare/Releases.php`](../../app/Livewire/CustomerCare/Releases.php): Livewire Component.
- [`resources/views/livewire/customer-care/releases.blade.php`](../../resources/views/livewire/customer-care/releases.blade.php): Görünüm.
- [`tests/Feature/CustomerCare/CustomerCareReleaseTest.php`](../../tests/Feature/CustomerCare/CustomerCareReleaseTest.php): Testler.

## 2. Migration Listesi ve Rollback Notu
- Migration: `2026_08_01_120000_create_support_release_tables`
- Rollback: `php artisan migrate:rollback`

## 3. Route, Command ve Scheduler Listesi
- Route: `/customer-care/releases` (`customer-care.releases`)
- Commands:
  - `customer-care:release-preflight {--package=}`
  - `customer-care:release-rollback {--package=} {--execute}`

## 4. Feature Flag Varsayılanları
- `CUSTOMER_CARE_RELEASE_CENTER_ENABLED=false` (Varsayılan kapalı).

## 5. Tenant, KVKK ve Fail-Closed Güvenlik Kanıtları
- Yayın öncesinde prompt şablonları taranarak PII sızıntısı ve Prompt Injection girişimleri preflight aşamasında algılanır.
- Unicode encoding (JSON_UNESCAPED_UNICODE) kullanılarak Türkçe karakter kaçışlarının aramaları etkilemesi engellenmiştir.
- Sadece onaylı (published) artifact sürümleri runtime tarafından kullanılabilir; taslak (draft) veya reddedilmiş paketler runtime'a sızamaz.

## 6. Test Sonuçları
- Test Komutu: `./vendor/bin/sail artisan test tests/Feature/CustomerCare/CustomerCareReleaseTest.php --no-coverage --compact`
- Sonuç: `PASS` (5 passed)

## 7. Bilinen Kapsam Dışı Maddeler
- Otomatik prompt optimizasyonu veya fine-tuning pipeline'ları kapsam dışıdır.
