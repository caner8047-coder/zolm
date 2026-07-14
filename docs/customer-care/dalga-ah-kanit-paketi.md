# Dalga AH — Kanıt Paketi
## Enterprise Governance, RBAC ve Onay Akışları

**Tarih:** 2026-07-13  
**Durum:** Kalite Kapısı 01 Revizyonu Tamamlandı

---

## 1. Amaç

Dalga AH; riskli işlemler için çok aşamalı onay mekanizması, self-approval (kendi kendini onaylama) engeli, service-level RBAC kontrolleri ve tüketilen onayların veritabanından silinmeyip `consumed` durumuna çekilerek izlendiği append-only denetim günlüğü altyapısını sağlar.

---

## 2. Değişen Dosyalar

| Dosya | Değişiklik |
|---|---|
| [`SupportRbacService.php`](../../../app/Services/Support/Security/SupportRbacService.php) | P0-1: `manage_roles`, `approve_risk_action` ve `reject_risk_action` yetkileri RBAC matrisine eklendi. P0-2: Onay tüketimi esnasında kayıt silinmeyip `consumed`, `consumed_at` ve `consumed_by` değerleri set edilmektedir. |
| [`Governance.php`](../../../app/Livewire/CustomerCare/Governance.php) | P0-1: Rol atama ve onaylama işlemlerinde service-level `enforcePermission` yetki kontrolleri eklendi. |
| [`SupportApprovalRequest.php`](../../../app/Models/SupportApprovalRequest.php) | P0-2: `consumed_at` ve `consumed_by` alanları `$fillable` ve `$casts` dizilerine dahil edildi. |
| [`CustomerCareGovernanceTest.php`](../../../tests/Feature/CustomerCare/CustomerCareGovernanceTest.php) | Rol atama, yetkisiz onay engelleme ve append-only onay günlüğü testleri eklendi. |
| [`routes/web.php`](../../../routes/web.php) | `/customer-care/governance` rotası `governance_enabled` bayrağı ile korunur. |

---

## 3. Migration / Rollback Kanıtı

Bu dalga için `2026_07_31_100000_create_support_governance_tables.php` tablosu oluşturulmuştur.

### Migration Up:
```bash
php artisan migrate
# support_role_assignments ve support_approval_requests tabloları oluşturuldu.
```

### Rollback:
```bash
php artisan migrate:rollback --path=database/migrations/2026_07_31_100000_create_support_governance_tables.php
# Tablolar başarıyla kaldırıldı.
```

---

## 4. Test İsimleri (8 test / 21 assertion)

```
✓ governance route blocks when flag off
✓ user permissions enforced based on store role
✓ analyst can export but cannot reply
✓ request owner self approve is blocked
✓ cross store approval view and approve is prevented
✓ operator cannot assign roles
✓ unauthorized user cannot approve requests
✓ approval is not deleted after consumption and cannot be reused
```

---

## 5. Feature Flag Varsayılanları

| Flag | ENV | Varsayılan |
|---|---|---|
| `governance_enabled` | `CUSTOMER_CARE_GOVERNANCE_ENABLED` | `false` |

---

## 6. Güvenlik ve Kalite Kapısı Düzeltmeleri (P0-1 & P0-2)

### P0-1 ✅ — Governance Yetki Kontrolleri
- Rol atama ve riskli işlemlerin onayı/reddi gibi kritik yetkiler, service-level RBAC kontrolü (`enforcePermission()`) ile `manage_roles`, `approve_risk_action` ve `reject_risk_action` yetkilerine tabi tutulmuştur. Yetkisi olmayan operatörlerin onay mekanizmasını bypass etmesi engellenmiştir.

### P0-2 ✅ — Append-only Onay Karar Günlüğü
- Onaylanan talepler artık veritabanından silinmemekte; durumları `consumed` olarak güncellenip tüketim zamanı (`consumed_at`) ve tüketen kullanıcı (`consumed_by`) alanları kalıcı olarak kayıt altında tutulmaktadır. Tüketilmiş bir onayın mükerrer kullanımı engellenmiştir.
