# Dalga AS — Commercial Packaging Kanıt Paketi

**Tarih:** 2026-07-13  
**Durum:** ✅ Tamamlandı (KG01 Revizyonu Uygulandı)

---

## 1. Değiştirilen / Eklenen Dosyalar

| Dosya | Tür | Açıklama / KG01 Düzeltmesi |
|---|---|---|
| `database/migrations/2026_08_03_120000_create_support_commercial_tables.php` | Yeni | Temel DB tabloları. |
| `app/Models/SupportCommercialPlan.php` | Yeni | Abonelik planları. |
| `app/Models/SupportCommercialSubscription.php` | Yeni | Mağaza abonelikleri. |
| `app/Models/SupportEntitlementEvent.php` | Yeni | Kullanım hakkı olay günlüğü. |
| `app/Services/Support/CustomerCareEntitlementService.php` | Yeni | **[KG01]** `hasEntitlement` ve `generateBillingExport` metodlarına opsiyonel User parametresi ve store boundary doğrulaması eklendi. Raporlamalarda TCKN/e-posta/telefon gibi PII maskelemeleri uygulandı. |
| `app/Console/Commands/CustomerCareEntitlementAuditCommand.php` | Yeni | Hak ve kullanım denetim komutu. |
| `app/Console/Commands/CustomerCareUsageBillingExportCommand.php` | Yeni | Faturalama dışa aktarma komutu. |
| `app/Livewire/CustomerCare/Commercial.php` | Yeni | **[KG01]** Mağaza dropdown listesi `getAccessibleStores` ile tenant-scoped yapıldı.selectedStoreId doğrulaması eklendi. |
| `resources/views/livewire/customer-care/commercial.blade.php` | Yeni | UI görünümü. |
| `tests/Feature/CustomerCare/CustomerCareEntitlementTest.php` | Yeni | **[KG01]** Yetkisiz store export engeli, hasEntitlement izolasyon testleri ve billing export PII redaction testleri eklendi. |
| `config/customer-care.php` | Güncellendi | Konfigürasyon dosyası. |
| `.env.example` | Güncellendi | Çevre değişkenleri kılavuzu. |
| `routes/web.php` | Güncellendi | Web rotaları. |

---

## 2. Migration Listesi

| Migration | Tablo | Rollback |
|---|---|---|
| `2026_08_03_120000` | `support_commercial_plans`, `support_commercial_subscriptions`, `support_entitlement_events` | `Schema::dropIfExists` sıralı |

---

## 3. Route / Command / Scheduler

**Rotalar:**

| Rota | Adı | Middleware |
|---|---|---|
| `GET /customer-care/commercial` | `customer-care.commercial` | `customer-care.feature:commercial_center_enabled` |

**Artisan komutları:**

| Komut | Açıklama |
|---|---|
| `customer-care:entitlement-audit --store=ID --dry-run` | Mağaza özellik hakları denetimi |
| `customer-care:usage-billing-export --store=ID --month=YYYY-MM` | Faturalama için kullanım dışa aktarımı |

---

## 4. Feature Flag Varsayılanları

```
CUSTOMER_CARE_COMMERCIAL_CENTER_ENABLED=false
```

---

## 5. Tenant / KVKK / Fail-Closed Güvenlik Kanıtları

- **Tenant İzolasyonu & UI Dropdown Güvenliği:** `/customer-care/commercial` ekranındaki mağaza listesi global MarketplaceStore sorgulamak yerine `CustomerCareOrganizationContext::getAccessibleStores` ile sadece kullanıcının yetkili olduğu mağazaları getirir. **[KG01]** ✅
- **Tanımsız Hakların Engellenmesi:** Plan dahilinde belirtilmemiş (unknown) yetkiler fail-closed olarak doğrudan engellenir. `unknown_entitlement_fails_closed` ✅
- **Kritik İstisna Kuralı:** Manuel agent cevapları plan hak sınırlarına takılamaz; operasyonel süreklilik garanti altındadır. `plan_limit_blocks_auto_reply_but_allows_manual_reply` ✅
- **Blocked Eventlerde Kota Korunumu:** Engellenen eylemler mağazanın aylık kullanım kotasını (quota) tüketmez. `blocked_action_does_not_consume_quota` ✅
- **Çoklu Mağaza Sınırı & Service Boundary:** Yetkisiz kullanıcıların başka mağazalar için billing export alması ve yetki durumu okuması engellenmiştir. `unauthorized_user_cannot_export_billing_for_another_store` **[KG01]** ✅
- **Billing Export Güvenliği:** Üretilen fatura detay raporları (CSV) PII içermez (PiiRedactor ile maskelenir), UTF-8 BOM ve XML-safe sanitization kurallarına uyar. `billing_export_is_pii_safe_and_xml_safe` **[KG01]** ✅

---

## 6. Test Komutları ve Sonuçları

```bash
./vendor/bin/sail artisan test tests/Feature/CustomerCare/CustomerCareEntitlementTest.php --no-coverage --compact
```

**Sonuç:** 8 passed / 12 assertions ✅

---

## 7. Kapsam Dışı

- Stripe/Iyzico vb. ödeme geçidi (payment gateway) entegrasyonu
- Otomatik karttan para çekme ve fatura kesme işlemleri
- Resmi muhasebe defteri (General Ledger) entegrasyonu
