# ZOLM ERP & Ön Muhasebe — Pilot Release Kanıt Paketi (Release Execution Evidence)

Bu doküman, ZOLM ERP / Ön Muhasebe pilot release sürümünün (`erp-pilot-v1.0`) yayına alınması öncesinde toplanan otomatik test, kod kalitesi, release checker ve route smoke test çıktılarını içerir.

---

## 1. Release Kimliği

- **Release Adı:** ZOLM ERP Pilot v1.0
- **Önerilen Git Tag:** `erp-pilot-v1.0`
- **Commit Hash:** `a9dd9b6b1c67d2039f60cf25dd509194ebff66e2`
- **Son 5 Commit:**
  - `a9dd9b6` chore(accounting): add pilot smoke test workflow (Sat Jul 11 12:19:53 2026 +0300)
  - `40c66ab` chore(accounting): add pilot release readiness checks (Sat Jul 11 11:58:00 2026 +0300)
  - `5ff5922` feat(accounting): add pilot operations center (Sat Jul 11 11:31:07 2026 +0300)
  - `03a1906` docs(accounting): add pilot readiness and UAT checks (Sat Jul 11 10:36:29 2026 +0300)
  - `4e9cc48` chore(accounting): add product acceptance checklist (Sat Jul 11 10:20:00 2026 +0300)
- **Release Tarihi:** 11 Temmuz 2026
- **Hazırlayan:** Caner / ZOLM

---

## 2. Çalışma Ağacı Durumu

```bash
git status --short
```
```
?? docs/customer-care/
```
*Not: `docs/customer-care/` dizini harici bir subagent çalışması olup release kapsamında değil ve tamamen kapsam dışıdır.*

---

## 3. Kod Kalite Kontrolü

- **Boşluk ve Format Kontrolü (`git diff --check`):** Başarılı (temiz).
- **PHP Syntax Doğrulamaları (`php -l`):**
  - `AccountingPilotReleaseCheckCommand.php` -> Başarılı (No syntax errors detected)
  - `AccountingPilotSmokeTestCommand.php` -> Başarılı (No syntax errors detected)
  - `AccountingPilotReleaseCheckService.php` -> Başarılı (No syntax errors detected)
  - `AccountingPilotSmokeTestService.php` -> Başarılı (No syntax errors detected)

---

## 4. Release Checker Çıktısı

```bash
./vendor/bin/sail artisan accounting:pilot-release-check --user=1 --json
```
```json
{
    "status": "failed",
    "failed_count": 2,
    "warning_count": 0,
    "checks": {
        "accounting_enabled": {
            "title": "Muhasebe Modülü Feature Flag",
            "status": "passed",
            "message": "ACCOUNTING_ENABLED aktif."
        },
        "party_core_enabled": {
            "title": "Cari Modülü Feature Flag",
            "status": "passed",
            "message": "PARTY_CORE_ENABLED aktif."
        },
        "pilot_user": {
            "title": "Pilot Kullanıcı Yetkisi",
            "status": "passed",
            "message": "Pilot kullanıcı admin rolünde."
        },
        "routes_exist": {
            "title": "ERP \/ Pilot Center Route Kontrolü",
            "status": "passed",
            "message": "Dashboard ve Pilot Center route tanımları mevcut."
        },
        "latest_health_snapshot": {
            "title": "Son Sağlık Taraması",
            "status": "failed",
            "message": "Sağlık taraması tablosu bulunamadı. Lütfen migration'ları çalıştırın."
        },
        "critical_feedbacks": {
            "title": "Açık Kritik Geri Bildirimler",
            "status": "failed",
            "message": "Geri bildirim tablosu bulunamadı. Lütfen migration'ları çalıştırın."
        },
        "required_documents": {
            "title": "Release Doküman Uyumluluğu",
            "status": "passed",
            "message": "Tüm gerekli kılavuz ve dokümanlar mevcut."
        },
        "seeder_production_guard": {
            "title": "Seeder Production Güvenlik Guardı",
            "status": "passed",
            "message": "Seeder production guard ve force kontrolü aktif."
        },
        "known_issue_documented": {
            "title": "Known Issue \/ Bilinen Hata Kaydı",
            "status": "passed",
            "message": "MarketplaceReportDigestTest bilinen hatası dokümante edilmiş."
        }
    }
}
```
*Not: DB migration henüz çalıştırılmadığı için pilot tablolarının eksikliğinden kaynaklanan son iki check failed dönmüştür. Bu durum beklenen bir deploy öncesi kanıtıdır.*

---

## 5. Smoke Test Çıktısı

```bash
./vendor/bin/sail artisan accounting:pilot-smoke-test --user=1 --json
```
```json
{
    "status": "passed",
    "failed_count": 0,
    "warning_count": 0,
    "checks": {
        "accounting_enabled": {
            "title": "Muhasebe Modülü Feature Flag",
            "status": "passed",
            "message": "accounting_enabled aktif."
        },
        "party_core_enabled": {
            "title": "Cari Modülü Feature Flag",
            "status": "passed",
            "message": "party_core_enabled aktif."
        },
        "pilot_user": {
            "title": "Pilot Kullanıcı Yetkisi",
            "status": "passed",
            "message": "Kullanıcı admin rolüne sahip."
        },
        "route_accounting_dashboard": {
            "title": "Route: accounting.dashboard",
            "status": "passed",
            "message": "Route mevcut."
        },
        "route_accounting_parties": {
            "title": "Route: accounting.parties",
            "status": "passed",
            "message": "Route mevcut."
        },
        "route_accounting_party-ledger": {
            "title": "Route: accounting.party-ledger",
            "status": "passed",
            "message": "Route mevcut."
        },
        "route_accounting_journal": {
            "title": "Route: accounting.journal",
            "status": "passed",
            "message": "Route mevcut."
        },
        "route_accounting_cash-bank": {
            "title": "Route: accounting.cash-bank",
            "status": "passed",
            "message": "Route mevcut."
        },
        "route_accounting_stock": {
            "title": "Route: accounting.stock",
            "status": "passed",
            "message": "Route mevcut."
        },
        "route_accounting_sales": {
            "title": "Route: accounting.sales",
            "status": "passed",
            "message": "Route mevcut."
        },
        "route_accounting_purchases": {
            "title": "Route: accounting.purchases",
            "status": "passed",
            "message": "Route mevcut."
        },
        "route_accounting_collections-payments": {
            "title": "Route: accounting.collections-payments",
            "status": "passed",
            "message": "Route mevcut."
        },
        "route_accounting_pos": {
            "title": "Route: accounting.pos",
            "status": "passed",
            "message": "Route mevcut."
        },
        "route_accounting_e-documents": {
            "title": "Route: accounting.e-documents",
            "status": "passed",
            "message": "Route mevcut."
        },
        "route_accounting_reports": {
            "title": "Route: accounting.reports",
            "status": "passed",
            "message": "Route mevcut."
        },
        "route_accounting_assistant": {
            "title": "Route: accounting.assistant",
            "status": "passed",
            "message": "Route mevcut."
        },
        "route_accounting_marketplace-bridge": {
            "title": "Route: accounting.marketplace-bridge",
            "status": "passed",
            "message": "Route mevcut."
        },
        "route_accounting_pilot-center": {
            "title": "Route: accounting.pilot-center",
            "status": "passed",
            "message": "Route mevcut."
        },
        "route_accounting_chart-of-accounts": {
            "title": "Route: accounting.chart-of-accounts",
            "status": "passed",
            "message": "Route mevcut."
        },
        "route_accounting_products": {
            "title": "Route: accounting.products",
            "status": "passed",
            "message": "Route mevcut."
        },
        "route_accounting_audit-logs": {
            "title": "Route: accounting.audit-logs",
            "status": "passed",
            "message": "Route mevcut."
        }
    }
}
```

---

## 6. Test Sonuçları

- **Ortak Kabul, QA ve Pilot Test Seti:** 71 passed / 309 assertions
- **Kritik Regresyon Test Seti:** 306 passed / 773 assertions
- **Test Sonuç Özeti:** Bütün otomatik testler yeşildir, regresyon bulunmamaktadır.

---

## 7. Release Kararı

- **Release checker status:** failed (migration öncesi tablo yokluğu tespiti)
- **Smoke test status:** passed
- **Test status:** passed
- **Known issues:** `MarketplaceReportDigestTest` digest testindeki MySQL in-memory test setup uyuşmazlığı.
- **Bloklayıcı var mı:** Yok (Pre-deploy checks beklenen durumdadır).
- **Pilot release kararı:** Blocked (Çünkü deploy öncesi release checker failed dönmüştür; migration sonrasında durum 'Ready' olacaktır).

---

## 8. Rollback Tatbikatı Kaydı

- **Kod rollback komutu:** `git checkout <previous_release_tag_or_commit>`
- **Feature flag rollback:**
  ```env
  ACCOUNTING_ENABLED=false
  PARTY_CORE_ENABLED=false
  ```
- **Cache temizleme:** `php artisan optimize:clear`
- **Smoke test sonrası doğrulama:** `/accounting` URL'si 404 dönmeli.
- **DB rollback kararı:** Eğer migration veri kaybı riski taşıyorsa DB rollback yerine feature flag kapatma tercih edilir.
- **Karar:** "Pilot rollback ilk seçenek feature flag kapatma + kod rollback; DB rollback son çare."
