# Dalga AQ — Organization/Tenant v2 Kanıt Paketi

**Tarih:** 2026-07-13  
**Durum:** ✅ Tamamlandı (KG01 Revizyonu Uygulandı)

---

## 1. Değiştirilen / Eklenen Dosyalar

| Dosya | Tür | Açıklama / KG01 Düzeltmesi |
|---|---|---|
| `database/migrations/2026_08_03_100000_create_support_organization_tables.php` | Yeni | Temel DB tabloları. |
| `app/Models/SupportOrganizationSetting.php` | Yeni | Organizasyon ayarları. |
| `app/Models/SupportOrganizationMembership.php` | Yeni | Üyelik ilişkileri. |
| `app/Models/SupportServiceAccount.php` | Yeni | Servis hesapları. |
| `app/Services/Support/CustomerCareOrganizationContext.php` | Yeni | **[KG01]** getAccessibleOrganizations ve getAccessibleStores eklendi. getSystemActor global fallback'i local/testing ile sınırlandırılıp prod'da fail-closed yapıldı. |
| `app/Console/Commands/CustomerCareOrgDiagnosticsCommand.php` | Yeni | Organizasyon denetim komutu. |
| `app/Livewire/CustomerCare/Organization.php` | Yeni | **[KG01]** Dropdown listesi getAccessibleOrganizations ile tenant-scoped yapıldı.selectedOrgId doğrulaması eklendi. |
| `resources/views/livewire/customer-care/organization.blade.php` | Yeni | UI görünümü. |
| `tests/Feature/CustomerCare/CustomerCareOrganizationTest.php` | Yeni | **[KG01]** Servis hesaplarının self-approval riskli işlemlerini (approve_risk_action) engelleyen gerçek governance testi eklendi. |
| `config/customer-care.php` | Güncellendi | Konfigürasyon dosyası. |
| `.env.example` | Güncellendi | Çevre değişkenleri kılavuzu. |
| `routes/web.php` | Güncellendi | Web rotaları. |

---

## 2. Migration Listesi

| Migration | Tablo | Rollback |
|---|---|---|
| `2026_08_03_100000` | `support_organization_settings`, `support_organization_memberships`, `support_service_accounts` | `Schema::dropIfExists` sıralı |

---

## 3. Route / Command / Scheduler

**Rotalar:**

| Rota | Adı | Middleware |
|---|---|---|
| `GET /customer-care/organization` | `customer-care.organization` | `customer-care.feature:org_center_enabled` |

**Artisan komutları:**

| Komut | Açıklama |
|---|---|
| `customer-care:org-diagnostics --organization=ID --dry-run` | Organizasyon tenant teşhis denetimi |
| `customer-care:org-diagnostics --store=ID --dry-run` | Mağaza bazında organizasyon teşhis denetimi |

---

## 4. Feature Flag Varsayılanları

```
CUSTOMER_CARE_ORG_CENTER_ENABLED=false
```

---

## 5. Tenant / KVKK / Fail-Closed Güvenlik Kanıtları

- **Tenant İzolasyonu & UI Dropdown Güvenliği:** `/customer-care/organization` ekranındaki organizasyon listesi global LegalEntity sorgulamak yerine `CustomerCareOrganizationContext::getAccessibleOrganizations` ile sadece kullanıcının yetkili olduğu organizasyonları getirir. **[KG01]** ✅
- **Cross-organization store engeli:** Yabancı mağaza veya organizasyona ait verilerin okunması ve system actor yetkisi engellenmiştir. `cross_organization_store_access_is_fail_closed` ✅
- **Service Account Yetki Sınırı:** Entegrasyon servis hesapları insan kullanıcı gibi self-approval/governance onayı veremez. `approve_risk_action` yetkisi RBAC seviyesinde engellenmiştir. `service_account_cannot_self_approve_governance` **[KG01]** ✅
- **System Actor Fail-Closed:** System Actor tanımlanmamışsa global fallback sadece local/testing ortamlarında çalışır, production ortamında fail-closed fırlatır. `system_actor_cannot_be_used_outside_organization_scope` **[KG01]** ✅
- **Diagnostics Redaction:** Teşhis çıktılarında e-posta, organizasyon ID ve diğer hassas veriler maskelenir. `organization_diagnostic_does_not_leak_pii_or_secrets` ✅

---

## 6. Test Komutları ve Sonuçları

```bash
./vendor/bin/sail artisan test tests/Feature/CustomerCare/CustomerCareOrganizationTest.php --no-coverage --compact
```

**Sonuç:** 6 passed / 11 assertions ✅

---

## 7. Kapsam Dışı

- SSO/SCIM kullanıcı senkronizasyon protokolleri
- Global süper organizasyon yönetimi
- Organizasyonlar arası toplu mağaza taşıma operasyonları
