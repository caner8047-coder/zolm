# ZOLM AI Müşteri İletişim Merkezi — Dalga AN/AO/AP Kabul Kararı

**Tarih:** 2026-07-13  
**İnceleyen:** Codex — Baş mühendis kontrolü  
**Önceki kalite kapıları:** `docs/customer-care/dalga-anaoap-kalite-kapisi-01.md`, `docs/customer-care/dalga-anaoap-kalite-kapisi-02.md`  
**Karar:** ✅ **Dalga AN/AO/AP Kalite Kapısı 02 revizyonu kabul edildi**

Bu karar; Customer Success, Experimentation Lab ve Security Assurance katmanlarında açılan P0/P1 maddelerinin kapatıldığını ve modülün pilot-ready kabul seviyesine geldiğini kayıt altına alır.

---

## Bağımsız Doğrulama Kanıtları

Çalıştırılan kontroller:

```bash
git diff --check
npm run build
./vendor/bin/sail artisan route:list --name=customer-care
./vendor/bin/sail artisan list customer-care --raw
./vendor/bin/sail artisan schedule:list
./vendor/bin/sail artisan test tests/Feature/CustomerCare --no-coverage --compact
./vendor/bin/sail artisan test --no-coverage --compact
```

Sonuçlar:

- `git diff --check`: ✅ temiz
- `npm run build`: ✅ başarılı
- Customer Care test paketi: ✅ `346 passed / 1139 assertions`
- Full test suite: ✅ `1805 passed / 7200 assertions`
- Route listesi: ✅ `success`, `experiments`, `security` dahil 20 customer-care route aktif
- Command listesi: ✅ `success-snapshot`, `run-experiment`, `compare-release`, `security-audit`, `evidence-pack` dahil 30 customer-care komutu aktif
- Scheduler: ✅ customer-care ve support outbox görevleri listeleniyor

---

## Kabul Edilen Revizyon Maddeleri

### 1. P0 — Experiment artifact store izolasyonu kapandı

`CustomerCareExperimentService` içinde experiment variant ve release karşılaştırma artifact yüklemeleri store-scoped hale getirildi.

Kabul edilen davranışlar:

- Başka mağazaya ait artifact version experiment içinde kullanılamıyor.
- Current/candidate release karşılaştırmalarında cross-store artifact sızıntısı engelleniyor.
- Testler `CustomerCareExperimentTest` içinde üç ayrı negatif senaryoyla kapsanıyor.

### 2. P0 — Security audit dry-run artık veritabanına yazmıyor

`CustomerCareSecurityService::runAudit()` dry-run modunda run, finding ve evidence kayıtlarını kalıcı olarak yazmıyor; yalnız in-memory model/ilişki nesneleriyle raporlama yapıyor.

Kabul edilen davranışlar:

- Dry-run gerçek audit ledger’ını kirletmiyor.
- `--execute` ile çalıştırıldığında run/finding/evidence kayıtları kalıcı olarak yazılıyor.
- Evidence ve finding içerikleri PII/secret sızıntısına karşı mevcut redaction hattını kullanıyor.

### 3. P1 — Success snapshot dry-run gerçek hesaplama yapıyor ama persist etmiyor

`CustomerCareSuccessService` içinde persist etmeyen `calculateSnapshotData()` ayrıldı. `customer-care:success-snapshot` dry-run modu bu hesaplamayı kullanıyor.

Kabul edilen davranışlar:

- Dry-run “boş mesaj” üretmek yerine gerçek sağlık skorunu hesaplıyor.
- Dry-run veritabanına snapshot yazmıyor.
- Execute yolu mevcut kalıcı snapshot davranışını koruyor.

### 4. P1 — Walkthrough teslimat özeti güncellendi

`walkthrough.md` artık AK/AL/AM ve AN/AO/AP teslimat özetini, rota/komut kapsamını ve güncel Customer Care test sonucunu içeriyor.

Güncel doğrulanan değer:

- `346 passed / 1139 assertions`

---

## Kabul Edilen Kapsam

### AN — Customer Success kabul edildi

Portfolio sağlık skoru, success task takibi ve PII-masked/şifreli not defteri pilot sonrası müşteri başarı operasyonu için yeterli seviyede çalışıyor.

### AO — Experimentation Lab kabul edildi

Shadow/offline deneyler canlı trafiği bölmeden çalışıyor; draft/rejected artifact kullanımı engelleniyor ve winner candidate otomatik publish edilmiyor.

### AP — Security Assurance kabul edildi

Security audit, threat model ve evidence pack üretimi secret/PII sızdırmadan denetim çıktısı üretebiliyor.

---

## Kalan Not / Sonraki Hardening

Bu kabulü bloke etmeyen P2 takip notları:

1. PHPUnit 12 geçişi için doc-comment test metadata uyarıları attribute formatına taşınmalı.
2. Customer Success portfolio listeleme ileride büyük müşteri sayılarında pagination ve indexed query ile optimize edilmeli.
3. Security evidence pack ileride imzalı/versiyonlu rapor formatına taşınabilir.

---

## Sonuç

Dalga AN/AO/AP Kalite Kapısı 02 revizyonu kabul edilmiştir.

Bu kabul ile ZOLM AI Müşteri İletişim Merkezi; müşteri başarı yönetimi, güvenli deney laboratuvarı ve denetim/evidence üretimi açısından pilot-ready seviyeye ulaşmıştır.
