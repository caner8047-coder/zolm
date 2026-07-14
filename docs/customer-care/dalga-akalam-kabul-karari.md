# ZOLM AI Müşteri İletişim Merkezi — Dalga AK/AL/AM Kabul Kararı

**Tarih:** 2026-07-13  
**İnceleyen:** Codex — Baş mühendis kontrolü  
**Önceki kalite kapısı:** `docs/customer-care/dalga-akalam-kalite-kapisi-01.md`  
**Karar:** ✅ **Dalga AK/AL/AM kabul edildi**

Bu karar; Launch Orchestrator, Projection Reconciliation ve Release/Artifact Lifecycle katmanlarının repo içinde görünür teslimata dönüştüğünü, müşteri hizmetleri modülünün mevcut güvenlik kapılarıyla uyumlu çalıştığını ve bir sonraki katmanlara temel olacak şekilde kabul edildiğini kayıt altına alır.

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
- Route listesi: ✅ 20 customer-care route aktif
- Command listesi: ✅ 30 customer-care komutu aktif
- Scheduler: ✅ `support-process-outbox`, `customer-care-pilot-monitor`, `customer-care-reconciliation` görevleri listeleniyor

---

## Kabul Edilen Kapsam

### 1. AK — Launch Orchestrator kabul edildi

Pilot/canary lansman akışı; checklist, readiness, governance onayı ve rollback adımlarıyla yönetilebilir hale getirildi.

Kabul edilen davranışlar:

- Lansman state geçişleri kontrollü ilerliyor.
- Governance approval olmadan kritik geçişler açılmıyor.
- Rollback otomatik modları kapatıp bekleyen AI dispatch kayıtlarını iptal ediyor.
- CLI dry-run mutasyon yapmadan sonuç üretiyor.

### 2. AL — Projection Reconciliation kabul edildi

Kanal projeksiyonları için drift tespiti, idempotent backfill ve onarım akışı kuruldu.

Kabul edilen davranışlar:

- Backfill idempotent çalışıyor.
- Disabled kanal fail-closed kapanıyor.
- Raw webhook/PII support message gövdesine sızmıyor.
- Cross-store mismatch authorization exception ile engelleniyor.
- Repair dry-run mutasyon yapmıyor.

### 3. AM — Release ve Artifact Lifecycle kabul edildi

Prompt, policy ve bilgi paketi gibi runtime artifact sürümleri için publish/rollback ve preflight hattı kuruldu.

Kabul edilen davranışlar:

- Draft sürüm runtime context’e girmiyor; yalnız published sürüm kullanılıyor.
- Preflight PII ve prompt-injection tespitlerinde fail-closed davranıyor.
- Rollback önceki sürüme dönebiliyor.
- Release akışı sonraki experimentation ve governance katmanlarına hazır.

---

## Kalan Not / Sonraki Hardening

Bu kabulü bloke etmeyen P2 takip notları:

1. Launch ve release ekranlarında canlı pilot öncesi son kullanıcı metinleri ayrıca ürün dili kontrolünden geçirilebilir.
2. Reconciliation sonuçları ileride daha ayrıntılı operasyon analitiğine bağlanabilir.
3. PHPUnit 12 uyumu için bazı testlerde doc-comment metadata yerine attribute kullanımına geçilmesi önerilir.

---

## Sonuç

Dalga AK/AL/AM kabul edilmiştir.

Bu katmanla ZOLM AI Müşteri İletişim Merkezi; pilot/canary yönetimi, kanal projeksiyon güvenilirliği ve artifact release yaşam döngüsü açısından modüler, geri alınabilir ve denetlenebilir bir zemine ulaşmıştır.
