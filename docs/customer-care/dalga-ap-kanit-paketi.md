# Dalga AP — Security Assurance Kanıt Paketi

**Tarih:** 2026-07-13  
**Durum:** ✅ Tamamlandı

---

## 1. Değiştirilen / Eklenen Dosyalar

| Dosya | Tür |
|---|---|
| `database/migrations/2026_08_02_120000_create_support_security_tables.php` | Yeni |
| `app/Models/SupportSecurityAuditRun.php` | Yeni |
| `app/Models/SupportSecurityFinding.php` | Yeni |
| `app/Models/SupportSecurityEvidenceItem.php` | Yeni |
| `app/Services/Support/CustomerCareSecurityService.php` | Yeni |
| `app/Console/Commands/CustomerCareSecurityCommand.php` | Yeni |
| `app/Console/Commands/CustomerCareEvidencePackCommand.php` | Yeni |
| `app/Livewire/CustomerCare/Security.php` | Yeni |
| `resources/views/livewire/customer-care/security.blade.php` | Yeni |
| `tests/Feature/CustomerCare/CustomerCareSecurityTest.php` | Yeni |
| `docs/customer-care/security-threat-model.md` | Yeni |
| `config/customer-care.php` | Güncellendi |
| `.env.example` | Güncellendi |
| `routes/web.php` | Güncellendi |

---

## 2. Migration Listesi

| Migration | Tablo | Rollback |
|---|---|---|
| `2026_08_02_120000` | `support_security_audit_runs`, `support_security_findings`, `support_security_evidence_items` | `Schema::dropIfExists` sıralı |

---

## 3. Route / Command / Scheduler

**Rotalar:**

| Rota | Adı | Middleware |
|---|---|---|
| `GET /customer-care/security` | `customer-care.security` | `customer-care.feature:security_center_enabled` |

**Artisan komutları:**

| Komut | Açıklama |
|---|---|
| `customer-care:security-audit --store=ID --dry-run` | Teknik güvenlik denetimi çalıştırır |
| `customer-care:evidence-pack --store=ID --format=markdown` | Denetçiler için redacted kanıt paketi üretir |

---

## 4. Feature Flag Varsayılanları

```
CUSTOMER_CARE_SECURITY_CENTER_ENABLED=false
```

---

## 5. Tenant / KVKK / Fail-Closed Güvenlik Kanıtları

- **Tenant izolasyon:** `TenantContext::enforceStoreAccess()` ile tüm denetim ve kanıt paketleri mağaza düzeyinde sınırlandırılır.
- **Cross-store engeli:** `cross_store_audit_is_blocked` testi ile onaylandı ✅
- **Gizli Verilerin Korunması:** Webhook secret'ları ve API anahtarları gibi kritik parametreler DB'de `Crypt::encryptString` ile şifreli saklanır. `evidence_data_is_encrypted_in_database` ✅
- **Redacted Kanıt Raporu:** `generateEvidencePack()` çıktısında kesinlikle raw secret, token veya PII bulunmaz; mağaza ID vb. hassas alanlar maskelenir. `evidence_pack_does_not_contain_raw_secrets` ✅
- **Fail-Closed Kontrolleri:** API anahtarı eksik olduğunda veya kritik risk tespit edildiğinde denetim durumunun healthy denmesi engellenir. `audit_with_critical_findings_is_marked_critical` ✅
- **Dry-run:** Komutlar varsayılan olarak dry-run çalışır ve veritabanı mutasyonuna (save/update) yol açmaz. `dry_run_audit_does_not_block_execution`, `test_security_audit_dry_run_does_not_persist_run_findings_or_evidence`, `test_security_audit_execute_persists_run_findings_and_evidence` ✅

---

## 6. Test Komutları ve Sonuçları

```bash
./vendor/bin/sail artisan test tests/Feature/CustomerCare/CustomerCareSecurityTest.php --no-coverage --compact
```

**Sonuç:** 9 passed / 23 assertions ✅

---

## 7. Kapsam Dışı

- Harici sızma testi (pentest) yürütülmesi
- SOC2/ISO27001 gibi resmi/harici sertifikasyon süreçleri
- WAF / CDN / Firewall seviyesinde ağ güvenlik yapılandırmaları
- Canlı ortamda webhook/API secret rotation işleminin otomatik tetiklenmesi
