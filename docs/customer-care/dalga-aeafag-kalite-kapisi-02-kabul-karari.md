# ZOLM AI Müşteri İletişim Merkezi — Dalga AE/AF/AG Kalite Kapısı 02 Kabul Kararı

**Tarih:** 2026-07-13  
**İnceleyen:** Codex — Baş mühendis kontrolü  
**Önceki kalite kapısı:** `docs/customer-care/dalga-aeafag-kalite-kapisi-02.md`  
**Karar:** ✅ **Dalga AE/AF/AG Kalite Kapısı 02 kabul edildi**

Bu karar; kalite denetimi, enterprise integration hub ve observability/model ops katmanlarında daha önce açılan P0/P1 maddelerinin revizyon sonrası bağımsız doğrulamasını kayıt altına alır.

---

## Bağımsız Doğrulama Kanıtları

Çalıştırılan kontroller:

```bash
git diff --check
npm run build
./vendor/bin/sail artisan route:list --name=customer-care
./vendor/bin/sail artisan list customer-care --raw
./vendor/bin/sail artisan schedule:list
./vendor/bin/sail artisan test \
  tests/Feature/CustomerCare/CustomerCareQualityTest.php \
  tests/Feature/CustomerCare/CustomerCareIntegrationHubTest.php \
  tests/Feature/CustomerCare/CustomerCareOpsTest.php \
  tests/Feature/CustomerCare/CustomerCareGovernanceTest.php \
  tests/Feature/CustomerCare/CustomerCareComplianceTest.php \
  tests/Feature/CustomerCare/CustomerCareReliabilityTest.php \
  --no-coverage --compact
./vendor/bin/sail artisan test tests/Feature/CustomerCare --no-coverage --compact
./vendor/bin/sail artisan test --no-coverage --compact
```

Sonuçlar:

- `git diff --check`: ✅ temiz
- `npm run build`: ✅ başarılı
- AE/AF/AG + AH/AI/AJ hedef güvenlik testleri: ✅ `56 passed / 160 assertions`
- Customer Care test paketi: ✅ `303 passed / 1051 assertions`
- Full test suite: ✅ `1762 passed / 7112 assertions`
- Route listesi: ✅ `quality`, `integrations`, `ops` ve sonraki governance/compliance/reliability rotaları feature middleware altında görünüyor
- Command listesi: ✅ kalite, integration, ops ve reliability komutları listeleniyor

---

## Kabul Edilen Maddeler

### 1. Quality/Ops feature flag koruması kapandı

`config/customer-care.php`, `.env.example` ve `routes/web.php` üzerinde:

- `quality_center_enabled`
- `ops_center_enabled`
- `integration_hub_enabled`

bayrakları varsayılan kapalı şekilde tanımlandı. İlgili rotalar `customer-care.feature:*` middleware koruması altında.

### 2. Integration Hub secret güvenliği kabul edildi

Integration Hub webhook secret akışında:

- boş secret ile dış istek yapılmıyor,
- geçersiz/çözülemeyen secret fail-closed kapanıyor,
- plaintext fallback kaldırılmış durumda,
- tekrar kayıtta encrypted secret çift şifrelenmiyor.

Kapsayan testler:

- `CustomerCareIntegrationHubTest`

### 3. Quality Center store izolasyonu kabul edildi

`QualityCenter` seçili item, AI run ve mesaj bağlamını public Livewire property değerlerine güvenmeden server-side yeniden çözüyor. Cross-store manipülasyon denemeleri fail-closed kapanıyor.

Kapsayan testler:

- `CustomerCareQualityTest`

### 4. Quality sampling PII çıktısı kabul edildi

`customer-care:sample-quality-reviews` komutu:

- dry-run varsayılan çalışıyor,
- execute modunda system actor gerektiriyor,
- terminal çıktısını `PiiRedactor` ve XML kontrol karakter temizliğiyle güvenli hale getiriyor.

### 5. Ops maliyet ve health davranışı kabul edildi

Ops katmanında:

- bilinmeyen maliyet `0` gibi gösterilmiyor,
- hesaplanamayan kayıtlar UI’da açık uyarı ile gösteriliyor,
- budget/health kontrolleri otomatik AI gönderim yoluna bağlanmış durumda.

---

## Kalan Not / Sonraki Hardening

Kalite kapısını bloke etmeyen P2 takip notu:

- Compliance legal-hold block audit kayıtlarında `details_json` içine yazılan müşteri referansları da ileride lineage/consent loglarıyla aynı SHA-256 hash standardına çekilmeli. Mevcut kalite kapısının hedefi lineage ve consent loglarındaki ham `customer_id` sızıntısını kapatmaktı; bu hedef doğrulandı.

---

## Sonuç

Dalga AE/AF/AG Kalite Kapısı 02 revizyonu kabul edilmiştir.

Bu kabul, AH/AI/AJ kabulü için ön koşul olan enterprise kalite/entegrasyon/ops katmanlarının yeterli güvenlik seviyesine ulaştığını gösterir.
