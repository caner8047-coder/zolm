# Dalga AV — Production Go-Live Center Kanıt Paketi

**Tarih:** 2026-07-13  
**Durum:** ✅ Tamamlandı

---

## 1. Değiştirilen / Eklenen Dosyalar

| Dosya | Tür | Açıklama |
|---|---|---|
| `database/migrations/2026_08_04_130000_create_support_production_readiness_runs_table.php` | Yeni | Canlı hazırlık denetimleri ve dondurulmuş konfigürasyon (freeze snapshot) tabloları. |
| `app/Models/SupportProductionReadinessRun.php` | Yeni | Hazırlık çalışması Eloquent modeli. |
| `app/Models/SupportProductionFreezeSnapshot.php` | Yeni | Freeze snapshot Eloquent modeli (encrypted cast). |
| `app/Services/Support/CustomerCareProductionReadinessService.php` | Yeni | Canlıya geçiş puanlama algoritması, freeze snapshot yönetimi, iki aşamalı onay ve rollback drill servis mantığı. |
| `app/Console/Commands/CustomerCareProductionRollbackDrillCommand.php` | Yeni | Geri dönme tatbikatı CLI komutu (dry-run). |
| `app/Console/Commands/CustomerCareProductionEvidencePackCommand.php` | Yeni | Canlıya geçiş öncesi hazırlık kanıt CLI komutu. |
| `app/Livewire/CustomerCare/Production.php` | Yeni | Canlıya geçiş merkezi Livewire component sınıfı (dropdown store-scoping dahil). |
| `resources/views/livewire/customer-care/production.blade.php` | Yeni | ZOLM açık panel kurallarına uygun Blade görünümü. |
| `tests/Feature/CustomerCare/CustomerCareProductionReadinessTest.php` | Yeni | Canlıya geçiş merkezi özellik test paketi. |
| `config/customer-care.php` | Güncellendi | `production_center_enabled` özellik bayrağı eklendi. |
| `.env.example` | Güncellendi | `CUSTOMER_CARE_PRODUCTION_CENTER_ENABLED` bayrağı eklendi. |
| `routes/web.php` | Güncellendi | `/customer-care/production` rotası eklendi. |

---

## 2. Migration Listesi

| Migration | Tablo | Rollback |
|---|---|---|
| `2026_08_04_130000` | `support_production_readiness_runs`, `support_production_freeze_snapshots` | `Schema::dropIfExists` sıralı |

---

## 3. Route / Command / Scheduler

**Rotalar:**

| Rota | Adı | Middleware |
|---|---|---|
| `GET /customer-care/production` | `customer-care.production` | `customer-care.feature:production_center_enabled` |

**Artisan komutları:**

| Komut | Açıklama |
|---|---|
| `customer-care:production-rollback-drill --store=ID --dry-run` | Üretim geri alma tatbikatı (Rollback Drill) raporunu dry-run olarak üretir |
| `customer-care:production-evidence-pack --store=ID` | Güvenlik, sertifikasyon ve hazırlık kanıt paketini (Evidence Pack) üretir |

---

## 4. Feature Flag Varsayılanları

```
CUSTOMER_CARE_PRODUCTION_CENTER_ENABLED=false
```

---

## 5. Tenant / KVKK / Fail-Closed Güvenlik Kanıtları

- **Eksik Sertifikasyon ve Plan Kontrolleri:** Eğer mağazanın aktif kanalları için geçmiş bir sertifikasyon (`pass` statülü) yoksa hazırlık skoru 90'ın altına düşer ve canlı durumu otomatik olarak `not_ready` fırlatır. `readiness_check_detects_missing_certification_and_golden_eval` ✅
- **Kritik Güvenlik Bulguları Engeli:** Mağazanın açık ve çözülmemiş en ufak bir kritik güvenlik bulgusu (`severity = critical` ve `status = open`) varsa canlıya geçiş durumu doğrudan engellenir. `readiness_fails_when_unresolved_critical_security_finding_exists` ✅
- **Stale Golden Evaluation Koruması:** Yapay zeka modeli golden dataset değerlendirmesi en geç `golden_eval_max_age_days` (varsayılan 7 gün) içerisinde koşturulmamışsa canlı durumu engellenir. `readiness_checks_stale_golden_evaluation` ✅
- **Dondurulmuş Snapshot Şifrelemesi (PII-Safe):** Freeze snapshot verileri veritabanında tamamen şifrelenmiş (`encrypted` cast) olarak tutulur. Ayrıca snapshot içerisinde yer alabilecek webhook secret vb. entegrasyon şifreleri kayıttan önce redacted yapılarak PII/sır sızıntısı engellenir. `configuration_freeze_snapshot_is_encrypted_and_pii_safe` ✅
- **İki Aşamalı Onay Mekanizması (Governance):** Canlıya geçiş ve freeze snapshot onaylama işlemlerinde self-approval kesinlikle engellenmiştir. Değerlendirmeyi başlatan temsilci kendi talebini onaylayamaz. `governance_blocks_self_approval_for_freeze_snapshots`, `governance_allows_other_user_approval` ✅
- **Rollback Drill (Dry-run):** Geri dönme tatbikatı hiçbir veri mutasyonu gerçekleştirmeden otomasyon CB durumu ve rollback yollarını raporlar. `rollback_drill_returns_correct_metadata` ✅

---

## 6. Test Komutları ve Sonuçları

```bash
./vendor/bin/sail artisan test tests/Feature/CustomerCare/CustomerCareProductionReadinessTest.php --no-coverage --compact
```

**Sonuç:** 15 passed / 31 assertions ✅
