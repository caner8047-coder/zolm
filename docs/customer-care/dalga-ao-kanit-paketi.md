# Dalga AO — Experimentation Lab Kanıt Paketi

**Tarih:** 2026-07-13  
**Durum:** ✅ Tamamlandı

---

## 1. Değiştirilen / Eklenen Dosyalar

| Dosya | Tür |
|---|---|
| `database/migrations/2026_08_02_110000_create_support_experiments_tables.php` | Yeni |
| `app/Models/SupportExperiment.php` | Yeni |
| `app/Models/SupportExperimentVariant.php` | Yeni |
| `app/Models/SupportExperimentRun.php` | Yeni |
| `app/Models/SupportExperimentResult.php` | Yeni |
| `app/Services/Support/CustomerCareExperimentService.php` | Yeni |
| `app/Console/Commands/CustomerCareExperimentCommand.php` | Yeni |
| `app/Console/Commands/CustomerCareCompareReleaseCommand.php` | Yeni |
| `app/Livewire/CustomerCare/Experiments.php` | Yeni |
| `resources/views/livewire/customer-care/experiments.blade.php` | Yeni |
| `tests/Feature/CustomerCare/CustomerCareExperimentTest.php` | Yeni |
| `config/customer-care.php` | Güncellendi |
| `.env.example` | Güncellendi |
| `routes/web.php` | Güncellendi |

---

## 2. Migration Listesi

| Migration | Tablo | Rollback |
|---|---|---|
| `2026_08_02_110000` | `support_experiments`, `support_experiment_variants`, `support_experiment_runs`, `support_experiment_results` | `Schema::dropIfExists` sıralı |

---

## 3. Route / Command / Scheduler

**Rotalar:**

| Rota | Adı | Middleware |
|---|---|---|
| `GET /customer-care/experiments` | `customer-care.experiments` | `customer-care.feature:experiments_enabled` |

**Artisan komutları:**

| Komut | Açıklama |
|---|---|
| `customer-care:run-experiment --store=ID --experiment=ID --dry-run` | Deney simülasyonu çalıştırır |
| `customer-care:compare-release --store=ID --current=ID --candidate=ID` | Release paketlerini karşılaştırır |

---

## 4. Feature Flag Varsayılanları

```
CUSTOMER_CARE_EXPERIMENTS_ENABLED=false
```

---

## 5. Tenant / KVKK / Fail-Closed Güvenlik Kanıtları

- **Cross-store engeli:** `cross_store_experiment_is_blocked`, `test_run_experiment_rejects_variant_artifact_from_another_store`, `test_compare_release_rejects_current_artifact_from_another_store`, `test_compare_release_rejects_candidate_artifact_from_another_store` testleri ile onaylandı ✅
- **PII / KVKK maskeleme:** Deney sonuçları ve örnek çıktılar PII içermez (`redacted_response_sample`).
- **Draft/Unapproved Engeli:** Onaylı olmayan (is_current=false) prompt/bilgi paketlerinin deneye girmesi engellenmiştir. `draft_artifact_version_blocks_experiment` ✅
- **Otomatik yayın engeli:** Winner seçilen aday otomatik olarak yayına alınmaz, Release (AM) ve Governance akışından geçmelidir. `winner_candidate_does_not_auto_publish` ✅
- **Dry-run:** Komutlar varsayılan olarak dry-run çalışır ve DB mutasyonu yapmaz. `dry_run_experiment_does_not_write_results` ✅

---

## 6. Test Komutları ve Sonuçları

```bash
./vendor/bin/sail artisan test tests/Feature/CustomerCare/CustomerCareExperimentTest.php --no-coverage --compact
```

**Sonuç:** 9 passed / 14 assertions ✅

---

## 7. Kapsam Dışı

- Gerçek canlı A/B trafik split/dağıtım işlemleri
- Otomatik prompt/kural yayını (publish)
- İnce ayar (fine-tuning) veri toplama pipeline'ı
- Multi-armed bandit / RL (Reinforcement Learning) mekanizmaları
