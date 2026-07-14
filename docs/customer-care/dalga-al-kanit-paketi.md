# Dalga AL Kanıt Paketi — Projection Backfill & Data Reconciliation

## 1. Değiştirilen/Eklenen Dosyalar
- [`database/migrations/2026_08_01_110000_create_support_reconciliation_tables.php`](../../database/migrations/2026_08_01_110000_create_support_reconciliation_tables.php): Cursors, runs, ve findings tabloları.
- [`app/Models/SupportProjectionCursor.php`](../../app/Models/SupportProjectionCursor.php): Model.
- [`app/Models/SupportReconciliationRun.php`](../../app/Models/SupportReconciliationRun.php): Model.
- [`app/Models/SupportReconciliationFinding.php`](../../app/Models/SupportReconciliationFinding.php): Model.
- [`app/Services/Support/CustomerCareReconciliationService.php`](../../app/Services/Support/CustomerCareReconciliationService.php): Drift tespiti, idempotent backfill ve tenant isolation.
- [`app/Console/Commands/CustomerCareReconcileCommand.php`](../../app/Console/Commands/CustomerCareReconcileCommand.php): `customer-care:reconcile-projections` komutu.
- [`app/Console/Commands/CustomerCareRepairProjectionCommand.php`](../../app/Console/Commands/CustomerCareRepairProjectionCommand.php): `customer-care:repair-projection` komutu.
- [`app/Livewire/CustomerCare/Reconciliation.php`](../../app/Livewire/CustomerCare/Reconciliation.php): Livewire Component.
- [`resources/views/livewire/customer-care/reconciliation.blade.php`](../../resources/views/livewire/customer-care/reconciliation.blade.php): Görünüm.
- [`tests/Feature/CustomerCare/CustomerCareReconciliationTest.php`](../../tests/Feature/CustomerCare/CustomerCareReconciliationTest.php): Testler.

## 2. Migration Listesi ve Rollback Notu
- Migration: `2026_08_01_110000_create_support_reconciliation_tables`
- Rollback: `php artisan migrate:rollback`

## 3. Route, Command ve Scheduler Listesi
- Route: `/customer-care/reconciliation` (`customer-care.reconciliation`)
- Commands:
  - `customer-care:reconcile-projections {--store=} {--all-accessible} {--dry-run} {--execute}`
  - `customer-care:repair-projection {--store=} {--finding=} {--dry-run} {--execute}`
- Scheduler:
  - `customer-care:reconcile-projections --store=1` (dailyAt 04:30)

## 4. Feature Flag Varsayılanları
- `CUSTOMER_CARE_RECONCILIATION_CENTER_ENABLED=false` (Varsayılan kapalı).

## 5. Tenant, KVKK ve Fail-Closed Güvenlik Kanıtları
- Veri senkronizasyonu ve backfill işlemlerinde cross-store IDOR güvenlik kontrolleri yapılır.
- webhook/API verilerinin projeksiyonu sırasında raw payload'ların mesaj gövdesine sızması önlenir.
- Hatalı veya sapmış (drift) verilerin düzeltilmesi (repair) işlemlerinde governance onay akışları zorunlu tutulur.

## 6. Test Sonuçları
- Test Komutu: `./vendor/bin/sail artisan test tests/Feature/CustomerCare/CustomerCareReconciliationTest.php --no-coverage --compact`
- Sonuç: `PASS` (6 passed)

## 7. Bilinen Kapsam Dışı Maddeler
- Otomatik veri tamir işlemi (dry-run/execute ve onay akışı zorunludur) kapsam dışıdır.
