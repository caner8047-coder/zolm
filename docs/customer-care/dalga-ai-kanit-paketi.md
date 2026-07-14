# Dalga AI — Kanıt Paketi
## Compliance Center, KVKK-GDPR, Consent ve Data Lineage

**Tarih:** 2026-07-13  
**Durum:** Kalite Kapısı 01 Revizyonu Tamamlandı

---

## 1. Amaç

Dalga AI; veri koruma ve uyumluluk gereksinimlerine yönelik olarak DSR (Kişisel Veri Talepleri), onay (consent) blokajı ve veri soy ağacı (data lineage) takibi altyapısını sağlar.

---

## 2. Değişen Dosyalar

| Dosya | Değişiklik |
|---|---|
| [`Compliance.php`](../../../app/Livewire/CustomerCare/Compliance.php) | P0-4: DSR export talebi için onay kontrolü eklendi; export dosya adı maskelenerek PII sızıntısı önlendi. Lineage sorgulamalarında arama parametresi sha256 ile hashlenerek veritabanı sorgusu yapılır. |
| [`CustomerCareComplianceService.php`](../../../app/Services/Support/Compliance/CustomerCareComplianceService.php) | P0-5: `logLineageEvent()` fonksiyonu raw customer_id değerini veritabanına yazmadan önce sha256 ile hashler. |
| [`CustomerCareConsentService.php`](../../../app/Services/Support/Compliance/CustomerCareConsentService.php) | P0-5: Consent engelleme loglarında raw customer_id yerine hashli değer loglanır. |
| [`CustomerCareComplianceTest.php`](../../../tests/Feature/CustomerCare/CustomerCareComplianceTest.php) | DSR export onay kontrolü, maskeli dosya adı testi ve hashli lineage sorgu testleri eklendi. |
| [`routes/web.php`](../../../routes/web.php) | `/customer-care/compliance` rotası `compliance_enabled` bayrağı ile korunur. |

---

## 3. Migration / Rollback Kanıtı

Bu dalga için `2026_07_31_110000_create_support_compliance_tables.php` tablosu oluşturulmuştur.

### Migration Up:
```bash
php artisan migrate
# support_data_subject_requests, support_consent_records, support_legal_holds ve support_data_lineage_events tabloları oluşturuldu.
```

### Rollback:
```bash
php artisan migrate:rollback --path=database/migrations/2026_07_31_110000_create_support_compliance_tables.php
# Tablolar başarıyla kaldırıldı.
```

---

## 4. Test İsimleri (7 test / 17 assertion)

```
✓ compliance route blocks when flag off
✓ legal hold blocks anonymization
✓ marketing blocked when consent missing but operational allowed
✓ dsr export is xml sanitized and utf8 bom
✓ dsr export request requires risk approval
✓ dsr export filename is masked and logs action
✓ lineage logs customer id as hashed and supports search
```

---

## 5. Feature Flag Varsayılanları

| Flag | ENV | Varsayılan |
|---|---|---|
| `compliance_enabled` | `CUSTOMER_CARE_COMPLIANCE_ENABLED` | `false` |

---

## 6. Güvenlik ve Kalite Kapısı Düzeltmeleri (P0-4 & P0-5)

### P0-4 ✅ — DSR Export Yetkilendirme ve Maskeleme
- DSR export talebi oluşturma (`createDsr()`) işlemi artık riskli işlem onayına (`enforceApproval()`) tabidir.
- Export dosyasının ismi raw customer_id içermemekte, yerine `dsr_{dsrId}_{customer_id_hash_short}.json` formatında maskeli ref id kullanılmaktadır. Export indirme işlemi de `SupportAgentAction` tablosuna audit event olarak kaydedilir.

### P0-5 ✅ — Lineage ve Consent Loglarında PII Maskeleme
- Veri soy ağacı (`support_data_lineage_events`) tablosunda ve log dosyalarında raw customer_id saklanmamakta, sha256 ile hashlenerek anonimleştirilmektedir.
- UI'daki lineage arama fonksiyonu aranan ham ID'yi otomatik olarak hashleyerek veritabanında doğru olay zincirine ulaşılmasını sağlar.
