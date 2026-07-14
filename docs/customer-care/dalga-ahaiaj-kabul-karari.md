# ZOLM AI Müşteri İletişim Merkezi — Dalga AH/AI/AJ Kabul Kararı

**Tarih:** 2026-07-13  
**İnceleyen:** Codex — Baş mühendis kontrolü  
**Önceki kalite kapısı:** `docs/customer-care/dalga-ahaiaj-kalite-kapisi-01.md`  
**Karar:** ✅ **Dalga AH/AI/AJ Kalite Kapısı 01 revizyonu kabul edildi**

Bu karar; Enterprise Governance, Compliance Center v2 ve Production Reliability katmanları için açılan P0/P1 maddelerinin revizyon sonrası bağımsız inceleme sonucudur.

Ön koşul olarak `docs/customer-care/dalga-aeafag-kalite-kapisi-02.md` kapsamındaki kritik maddelerin kapandığı ayrıca doğrulanmıştır.

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
- AH/AI/AJ odaklı hedef paket: ✅ `56 passed / 160 assertions`
- Customer Care test paketi: ✅ `303 passed / 1051 assertions`
- Full test suite: ✅ `1762 passed / 7112 assertions`
- Route listesi: ✅ `governance`, `compliance`, `reliability` rotaları feature middleware altında görünüyor
- Command listesi: ✅ compliance, consent, queue-health, rate-limit-report ve dead-letter replay komutları listeleniyor

---

## Kabul Edilen Revizyon Maddeleri

### 1. AH — Governance/RBAC service-level guard kabul edildi

`SupportRbacService` ve `Governance` akışlarında:

- rol atama `manage_roles` yetkisine bağlandı,
- riskli işlem onay/red süreçleri `approve_risk_action` / `reject_risk_action` yetkileriyle korunuyor,
- cross-store ve self-approval sınırları testlerle kapsanıyor.

### 2. AH — Approval append-only tüketim modeli kabul edildi

Onay kaydı silinmeden:

- `status = consumed`,
- `consumed_at`,
- `consumed_by`

alanlarıyla işaretleniyor. Mükerrer onay kullanımı engelleniyor ve denetim izi korunuyor.

### 3. AH/AJ — `sendAgentReply()` double-dispatch riski kapandı

`SupportReplyService` içinde temsilci yanıtı için mükerrer `sendDispatch()` çağrısı görünmüyor. Temsilci yanıtları governance, budget veya otomasyon kapılarından yanlışlıkla etkilenmeden tek gönderim yolundan ilerliyor.

### 4. AI — DSR export onay ve güvenli dosya adı kabul edildi

Compliance akışında:

- `export`, `anonymize`, `delete` gibi riskli DSR işlemleri governance approval gerektiriyor,
- export dosya adı ham müşteri ID içermiyor,
- export indirme işlemi audit log’a kaydediliyor.

### 5. AI — Data lineage ve consent log hash standardı kabul edildi

Lineage ve consent kayıtlarında ham `customer_id` yerine SHA-256 hash kullanılıyor. UI tarafındaki lineage araması da server-side hashlenerek çalışıyor.

### 6. AJ — Dead-letter replay güvenli varsayılan kabul edildi

`customer-care:replay-deadletters` komutu:

- varsayılan dry-run çalışıyor,
- mutate etmek için `--execute` gerekiyor,
- execute sırasında system actor ve governance approval kontrolü yapıyor,
- integration delivery retry akışında plaintext secret fallback kullanmıyor.

### 7. AJ — Rate limiter ve queue health kabul edildi

Reliability katmanında:

- bilinmeyen kanal tipi fail-closed kapanıyor,
- canonical kanal tipi eşleştirmeleri yapılıyor,
- veri yokken queue health `unknown` dönüyor ve UI’da nötr durum olarak gösteriliyor.

---

## Kalan Not / Sonraki Hardening

Bu kabulü bloke etmeyen P2 takip notları:

1. Dead-letter replay execute akışında kullanılan permission adı şu an compliance yetkisi üzerinden korunuyor. İleride `replay_deadletters` veya `manage_reliability` gibi daha semantik ayrı bir permission’a taşınması daha temiz olur.
2. Compliance legal-hold block audit detaylarında müşteri referansı tamamen hash standardına çekilebilir. Lineage/consent tarafındaki ana P0 sızıntı kapandı.

---

## Sonuç

Dalga AH/AI/AJ Kalite Kapısı 01 revizyonu kabul edilmiştir.

Bu katmanla birlikte ZOLM AI Müşteri İletişim Merkezi; enterprise governance, KVKK/compliance ve production reliability açısından pilot öncesi daha olgun bir güvenlik zeminine ulaşmıştır.
