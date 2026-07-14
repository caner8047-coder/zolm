# Dalga AK Kanıt Paketi — Production Launch Orchestrator

## 1. Değiştirilen/Eklenen Dosyalar
- [`database/migrations/2026_08_01_100000_create_support_launch_tables.php`](../../database/migrations/2026_08_01_100000_create_support_launch_tables.php): Launch plans, steps, ve events tabloları.
- [`app/Models/SupportLaunchPlan.php`](../../app/Models/SupportLaunchPlan.php): Model.
- [`app/Models/SupportLaunchPlanStep.php`](../../app/Models/SupportLaunchPlanStep.php): Model.
- [`app/Models/SupportLaunchEvent.php`](../../app/Models/SupportLaunchEvent.php): Model.
- [`app/Services/Support/CustomerCareLaunchService.php`](../../app/Services/Support/CustomerCareLaunchService.php): State machine, checklist kontrolleri, emergency rollback ve tenant isolation.
- [`app/Console/Commands/CustomerCareLaunchCheckCommand.php`](../../app/Console/Commands/CustomerCareLaunchCheckCommand.php): `customer-care:launch-check` komutu.
- [`app/Console/Commands/CustomerCareLaunchRollbackCommand.php`](../../app/Console/Commands/CustomerCareLaunchRollbackCommand.php): `customer-care:launch-rollback` komutu.
- [`app/Livewire/CustomerCare/Launch.php`](../../app/Livewire/CustomerCare/Launch.php): Livewire Component.
- [`resources/views/livewire/customer-care/launch.blade.php`](../../resources/views/livewire/customer-care/launch.blade.php): Görünüm.
- [`tests/Feature/CustomerCare/CustomerCareLaunchTest.php`](../../tests/Feature/CustomerCare/CustomerCareLaunchTest.php): Testler.

## 2. Migration Listesi ve Rollback Notu
- Migration: `2026_08_01_100000_create_support_launch_tables`
- Rollback: `php artisan migrate:rollback` (veya adım bazlı rollback).

## 3. Route, Command ve Scheduler Listesi
- Route: `/customer-care/launch` (`customer-care.launch`)
- Commands:
  - `customer-care:launch-check {--store=} {--all-accessible}`
  - `customer-care:launch-rollback {--store=} {--execute}`

## 4. Feature Flag Varsayılanları
- `CUSTOMER_CARE_LAUNCH_CENTER_ENABLED=false` (Varsayılan kapalı).

## 5. Tenant, KVKK ve Fail-Closed Güvenlik Kanıtları
- Pilot/canary lansmanlarında mağaza bazlı `TenantContext::enforceStoreAccess($storeId)` doğrulaması yapılır.
- Rollback komutu ve acil durdurma işlemlerinde system actor kontrolü zorunludur.
- Veri sızıntısını önlemek amacıyla log ve event detaylarında ham PII (kişisel veri) bulunmamaktadır.

## 6. Test Sonuçları
- Test Komutu: `./vendor/bin/sail artisan test tests/Feature/CustomerCare/CustomerCareLaunchTest.php --no-coverage --compact`
- Sonuç: `PASS` (5 passed)

## 7. Bilinen Kapsam Dışı Maddeler
- Otomatik pilot devreye alma veya canary trafiği yönlendirme kuralları kapsam dışıdır.
