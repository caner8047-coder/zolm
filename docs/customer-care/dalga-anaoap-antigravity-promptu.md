# ZOLM AI Müşteri İletişim Merkezi — Dalga AN/AO/AP Antigravity Uygulama Promptu

**Tarih:** 2026-07-13  
**Hazırlayan:** Codex — Baş mühendis planı  
**Ön koşul:** Dalga AK/AL/AM kalite kapısı kabul edilmeden uygulanmamalıdır. Açık P0/P1 kalite kapısı varsa bu promptu uygulamaya başlama; durumu raporla ve dur.  
**Kapsam:** Dalga AN, AO, AP uygulanacak; sonraki dalgalara geçilmeyecek.

---

## 0. Genel Talimatlar

/Volumes/TWINMOS/zolm reposunda çalış.

Önce şu dosyaları tamamen oku:

- `AGENTS.md`
- `docs/customer-care/dalga-akalam-antigravity-promptu.md`
- `docs/customer-care/dalga-ahaiaj-kabul-karari.md`
- `docs/customer-care/pilot-runbook.md`
- `docs/customer-care/kvkk-retention-policy.md`
- `docs/customer-care/integration-hub-contract.md`
- `docs/customer-care/adr/002-tenant-ve-organizasyon-siniri.md`
- `docs/customer-care/adr/003-generic-outbound-dispatch.md`
- `docs/customer-care/adr/006-human-ownership-state-machine.md`
- `docs/customer-care/adr/007-ai-shadow-golden-eval-ledger.md`

Kurallar:

- Commit, push veya branch değişikliği yapma.
- Kapsam dışındaki kullanıcı değişikliklerine dokunma.
- Feature flag varsayılanlarını kapalı tut.
- Otomatik yanıtı global veya mağaza bazında kendiliğinden açma.
- Testlerde canlı dış API çağrısı yapma.
- Sahte başarı, sahte KPI, sahte health score veya fabricated benchmark üretme.
- Tenant/store izolasyonu query, command, service ve UI action seviyesinde doğrulanmalı.
- PII/KVKK verisi log, export, webhook, success dashboard, experiment sonucu veya security evidence içine raw sızmamalı.
- Yeni UI varsa ZOLM Kurumsal Açık Panel Sistemi ve mobil responsive kurallarına uy.
- CSV/Excel export varsa ZOLM export kurallarını uygula.
- Dalga AQ veya başka kapsama geçme.

---

# Dalga AN — Customer Success, Portfolio Health ve Çok Mağazalı Operasyon Merkezi

## Amaç

ZOLM kullanan firmanın veya ajans/holding yapısının birden fazla mağazayı tek ekrandan sağlıklı biçimde yönetebilmesi. Bu dalga müşteri başarı merkezi kurar: hangi mağaza hazır, hangi kanalda risk var, hangi ekip gecikiyor, hangi otomasyon kapalı, hangi kota doluyor?

Bu dalga ham müşteri mesajı veya kişisel veri göstermez. Sadece store-scoped, redacted ve gerçek metriklerden üretilmiş operasyon sinyali gösterir.

## Kapsam

1. Feature flag:
   - `CUSTOMER_CARE_SUCCESS_CENTER_ENABLED=false`
   - Varsayılan kapalı.
2. Portfolio kapsamı:
   - Kullanıcının erişebildiği legal entity / store sınırı dışına çıkma yok.
   - ZOLM global super-admin varsayma; mevcut yetki modeli ne ise onunla çalış.
3. Health score bileşenleri:
   - launch/readiness durumu,
   - son golden eval skoru,
   - circuit breaker durumu,
   - provider health,
   - queue health,
   - açık konuşma / SLA ihlal sayısı,
   - policy block oranı,
   - unresolved handoff sayısı,
   - quota/budget doluluk oranı,
   - integration delivery error oranı,
   - release/reconciliation pending riskleri.
4. Sahte veri yasağı:
   - Veri yoksa skor uydurma.
   - Eksik metrik `unknown` veya `insufficient_data` olmalı.
   - UI’da gri/nötr empty state gösterilmeli.
5. Yeni tablo gerekiyorsa minimal:
   - `support_success_snapshots`
   - `support_success_tasks`
   - `support_success_notes`
6. Snapshot:
   - Store scoped.
   - PII redacted.
   - Hesaplama tarihi ve kullanılan kaynak metrikleri saklanmalı.
   - Eski snapshot “current truth” gibi sunulmamalı; stale ise belirtilmeli.
7. Success task:
   - Örnek görevler:
     - “Golden eval yenile”
     - “Queue backlog kontrol et”
     - “Webhook secret rotate gerekiyor”
     - “Policy block oranı yüksek”
     - “SLA ihlalleri artıyor”
   - Otomatik task açılabilir ama otomatik düzeltme yapamaz.
   - Task kapatma append-only action/audit yazmalı.
8. UI:
   - `/customer-care/success`
   - Çok mağazalı portfolio tablosu,
   - mağaza health kartları,
   - risk/task listesi,
   - son snapshot ve stale uyarıları,
   - mobilde kart görünümü, desktop’ta tablo + sağ detay paneli.
9. Artisan commands:
   - `customer-care:success-snapshot --store=ID --dry-run`
   - `customer-care:success-snapshot --all-accessible --dry-run`
   - Mutasyon için `--execute` zorunlu.
10. Tests:
    - Feature flag kapalı route 404.
    - Kullanıcı yalnız erişebildiği store’ları görür.
    - Cross-store success snapshot okunamaz.
    - Veri yokken fake health score üretilmez.
    - Stale snapshot current gibi gösterilmez.
    - Success task kapatma audit yazar.
    - Command dry-run veri değiştirmez.
    - PII success note/snapshot/export içine raw sızmaz.
    - Health score gerçek metriklerden hesaplanır, unknown metric skoru şişirmez.

## Kapsam Dışı

- CRM satış fırsatı pipeline’ı.
- Faturalama/tahsilat modülü.
- ZOLM iç destek ekibi için global super-admin paneli.
- Temsilci maaş/performans primi sistemi.

---

# Dalga AO — Experimentation Lab, Shadow Karşılaştırma ve Güvenli Optimizasyon

## Amaç

AI cevap kalitesini körlemesine değiştirmek yerine, knowledge/policy/prompt release’lerini kontrollü, ölçülebilir ve geri alınabilir deneylerle karşılaştırmak. Bu dalga canlı trafiği rastgele bölmez; varsayılan olarak offline/shadow deney üretir.

Hiçbir deney kendi başına policy, prompt, knowledge veya automation mode’u canlıya alamaz.

## Kapsam

1. Feature flag:
   - `CUSTOMER_CARE_EXPERIMENTS_ENABLED=false`
   - Varsayılan kapalı.
2. Deney türleri:
   - golden dataset karşılaştırması,
   - shadow transcript replay,
   - policy rule variant karşılaştırması,
   - prompt/system instruction variant karşılaştırması,
   - knowledge package variant karşılaştırması,
   - channel-specific answer template karşılaştırması.
3. Yeni tablolar gerekiyorsa:
   - `support_experiments`
   - `support_experiment_variants`
   - `support_experiment_runs`
   - `support_experiment_results`
4. Deney lifecycle:
   - draft,
   - ready,
   - running,
   - completed,
   - cancelled,
   - archived.
5. Girdi kaynakları:
   - published/current artifact version,
   - approved release package,
   - golden eval case,
   - redacted historical transcript.
   - Draft/rejected artifact runtime veya experiment context’e izinsiz girmemeli.
6. Ölçümler:
   - kaynak uyumu,
   - hallucination guard sonucu,
   - policy violation sayısı,
   - brand voice uyumu,
   - human accepted / edited / rejected oranı,
   - latency,
   - tahmini maliyet,
   - confidence distribution.
7. Güvenlik:
   - Deney sonuçları PII redacted olmalı.
   - Raw prompt/response saklanacaksa redacted ve store scoped olmalı.
   - Provider yoksa veya API key yoksa deney fake success dönmez.
   - Public channel deneyleri daha sıkı policy/eval guard kullanmalı.
8. Winner selection:
   - Sistem “winner candidate” önerebilir.
   - Otomatik publish yok.
   - Winner publish ancak Dalga AM release workflow + governance approval ile olur.
9. UI:
   - `/customer-care/experiments`
   - Deney listesi,
   - variant karşılaştırması,
   - sonuç kartları,
   - policy/PII/hallucination failure listesi,
   - winner candidate uyarısı.
10. Artisan commands:
    - `customer-care:run-experiment --store=ID --experiment=ID --dry-run`
    - `customer-care:compare-release --store=ID --current=ID --candidate=ID --dry-run`
11. Tests:
    - Feature flag kapalı route 404.
    - Draft/rejected artifact deneyde izinsiz kullanılamaz.
    - Cross-store experiment okunamaz/çalıştırılamaz.
    - Provider yoksa fake success yok.
    - PII experiment result içine raw sızmaz.
    - Winner candidate otomatik publish yapmaz.
    - Public channel variant policy violation’da başarısız sayılır.
    - Dry-run komut DB mutasyonu yapmaz.
    - Latency/cost yoksa fake zero metric gösterilmez.

## Kapsam Dışı

- Gerçek canlı A/B traffic split.
- Otomatik prompt publish.
- Fine-tuning pipeline.
- Multi-armed bandit / reinforcement learning.

---

# Dalga AP — Security Assurance, Threat Model ve Audit Evidence Pack

## Amaç

ZOLM AI Müşteri İletişim Merkezi’ni kurumsal müşteriye, yatırımcıya veya denetçiye anlatılabilir güvenlik kanıtlarıyla desteklemek. Bu dalga teknik güvenlik kontrollerini, threat model’i, evidence pack üretimini ve güvenli audit export’u kurar.

Bu dalga harici pentest yerine geçmez; harici pentest’e hazırlık ve iç güvenlik kanıt paketidir.

## Kapsam

1. Feature flag:
   - `CUSTOMER_CARE_SECURITY_CENTER_ENABLED=false`
   - Varsayılan kapalı.
2. Security audit run modeli:
   - Yeni tablolar gerekiyorsa:
     - `support_security_audit_runs`
     - `support_security_findings`
     - `support_security_evidence_items`
3. Kontrol kategorileri:
   - route feature flag coverage,
   - RBAC/service-level permission guard,
   - tenant/store isolation,
   - approval workflow,
   - secret encryption ve decrypt fail-closed,
   - webhook HMAC/signature,
   - rate limit/backpressure,
   - raw payload leakage,
   - PII redaction,
   - export encoding/XML sanitization,
   - provider fake-success/fail-open,
   - circuit breaker / launch rollback,
   - release artifact approval,
   - reconciliation repair approval.
4. Threat model dokümanı:
   - `docs/customer-care/security-threat-model.md`
   - Varlıklar,
   - trust boundary’ler,
   - ana tehditler:
     - IDOR,
     - prompt injection,
     - data leakage,
     - fake provider success,
     - webhook spoofing,
     - replay attack,
     - secret leakage,
     - cross-tenant analytics,
     - unsafe export,
     - automatic reply runaway.
   - Mitigation ve test eşleşmeleri.
5. Evidence pack:
   - `customer-care:security-audit --store=ID --dry-run`
   - `customer-care:evidence-pack --store=ID --format=markdown`
   - Evidence redacted olmalı.
   - Ham secret, ham müşteri mesajı, ham webhook payload veya raw prompt yok.
   - Markdown tablo yapısını bozacak karakterler temizlenmeli.
6. UI:
   - `/customer-care/security`
   - Audit run listesi,
   - finding severity,
   - kontrol kategorileri,
   - evidence preview,
   - export butonu,
   - remediation task bağlantısı.
7. Severity:
   - critical: cross-store sızıntı, secret leakage, fail-open automatic reply.
   - high: PII export/log sızıntısı, approval bypass, webhook signature bypass.
   - medium: stale config, missing schedule guard, unknown health.
   - low: dokümantasyon veya hardening önerisi.
8. Remediation:
   - Security center kendisi kod düzeltmez.
   - Finding’den success task veya governance request açabilir.
   - Mutasyonlar approval ve RBAC gerektirir.
9. Tests:
   - Feature flag kapalı route 404.
   - Security audit cross-store veri okuyamaz.
   - Secret/evidence export raw token içermez.
   - Evidence pack UTF-8/XML/Markdown güvenli.
   - Route coverage check gerçek route listesine bakar, hardcoded success dönmez.
   - Provider fake-success kontrolü config’e bakar ve eksik provider’da finding üretir.
   - Critical finding varsa audit summary healthy demez.
   - Dry-run audit DB mutasyonu yapmaz.
   - Security finding remediation task store scoped.

## Kapsam Dışı

- Harici pentest yürütmek.
- SOC2/ISO27001 resmi sertifikasyon süreci.
- WAF/CDN ayarı.
- Production secret rotation işlemini otomatik yapmak.

---

## Kanıt Paketleri

Dalga AN/AO/AP için ayrı kanıt paketleri oluştur:

- `docs/customer-care/dalga-an-kanit-paketi.md`
- `docs/customer-care/dalga-ao-kanit-paketi.md`
- `docs/customer-care/dalga-ap-kanit-paketi.md`

Her pakette şunlar olsun:

1. Değiştirilen/eklenen dosyalar.
2. Migration listesi ve rollback notu.
3. Route/command/scheduler listesi.
4. Feature flag varsayılanları.
5. Tenant/KVKK/fail-closed güvenlik kanıtları.
6. Test komutları ve sonuçları.
7. Bilinen kapsam dışı maddeler.

`walkthrough.md` dosyasını da Dalga AN/AO/AP özetiyle güncelle.

---

## Zorunlu Doğrulama Komutları

En az şunları çalıştır:

```bash
git diff --check
npm run build
./vendor/bin/sail artisan route:list --name=customer-care
./vendor/bin/sail artisan list customer-care --raw
./vendor/bin/sail artisan schedule:list
./vendor/bin/sail artisan test tests/Feature/CustomerCare --no-coverage --compact
```

Mümkünse full suite:

```bash
./vendor/bin/sail artisan test --no-coverage --compact
```

Full suite uzun sürerse en az Customer Care paketi + ilgili regresyon testlerini çalıştır ve full suite’in çalıştırılamama sebebini açıkça raporla.

---

## Antigravity’ye Verilecek Kısa Komut

```text
/Volumes/TWINMOS/zolm reposunda Dalga AN/AO/AP görevlerini uygula.

Önce şu dosyayı tamamen oku ve içindeki talimatları eksiksiz uygula:

/Volumes/TWINMOS/zolm/docs/customer-care/dalga-anaoap-antigravity-promptu.md

Yalnız Dalga AN/AO/AP kapsamını uygula.
Kapsam dışındaki mevcut kullanıcı değişikliklerine dokunma.
Commit, push veya branch değişikliği yapma.
Dalga AQ veya başka kapsama geçme.
Test, build, route, command, scheduler ve git kanıt paketini verdikten sonra dur.
```
