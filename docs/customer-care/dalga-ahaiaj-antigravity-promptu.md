# ZOLM AI Müşteri İletişim Merkezi — Dalga AH/AI/AJ Antigravity Uygulama Promptu

**Tarih:** 2026-07-13  
**Hazırlayan:** Codex — Baş mühendis planı  
**Ön koşul:** Dalga AB/AC/AD ve Dalga AE/AF/AG kalite kapıları kabul edilmeden uygulanmamalıdır. Açık P0/P1 kalite kapısı varsa bu promptu uygulamaya başlama; durumu raporla ve dur.  
**Kapsam:** Dalga AH, AI, AJ uygulanacak; sonraki dalgalara geçilmeyecek.

---

## 0. Genel Talimatlar

/Volumes/TWINMOS/zolm reposunda çalış.

Önce şu dosyaları tamamen oku:

- `AGENTS.md`
- `docs/customer-care/dalga-abacad-kalite-kapisi-01.md`
- `docs/customer-care/dalga-aeafag-kalite-kapisi-01.md`
- `docs/customer-care/dalga-aeafag-antigravity-promptu.md`
- `docs/customer-care/kvkk-retention-policy.md`
- `docs/customer-care/integration-hub-contract.md`
- `docs/customer-care/adr/002-tenant-ve-organizasyon-siniri.md`
- `docs/customer-care/adr/003-generic-outbound-dispatch.md`
- `docs/customer-care/adr/006-human-ownership-state-machine.md`
- `docs/customer-care/adr/007-ai-shadow-golden-eval-ledger.md`

Kurallar:

- Commit, push veya branch değişikliği yapma.
- Kapsam dışındaki kullanıcı değişikliklerine dokunma.
- Açık kalite kapısı P0/P1 düzeltmelerini bu prompt kapsamında çözmeye çalışma; bu dalgalar yalnız önceki kapılar kapandıktan sonra uygulanır.
- Feature flag varsayılanlarını kapalı tut.
- Otomatik yanıtı global olarak açma.
- Testlerde canlı dış API çağrısı yapma.
- Sahte başarı üretme: gerçek provider/connector yoksa capabilities unavailable ve işlem fail-closed olmalı.
- Tenant/store izolasyonu her query ve action seviyesinde doğrulanmalı.
- PII/KVKK verisi log, export, webhook, observability, review veya prompt alanlarına raw sızmamalı.
- Yeni UI varsa ZOLM Kurumsal Açık Panel Sistemi ve mobil responsive kurallarına uy.
- CSV/Excel export varsa ZOLM export kurallarını uygula.
- Dalga AK veya başka kapsama geçme.

---

# Dalga AH — Enterprise Governance, RBAC ve Onay Akışları

## Amaç

Müşteri İletişim Merkezi’nin “her admin her şeyi yapar” seviyesinden kurumsal yönetişim seviyesine çıkması. Bu dalga rol bazlı izinler, iki aşamalı onay gereken riskli işlemler ve append-only karar defteri kurar.

## Kapsam

1. Mevcut yetki modelini incele:
   - `User`, `LegalEntity`, `Store`, mevcut policy/middleware yapıları.
   - Var olan role/permission sistemi varsa onu genişlet; yoksa müşteri iletişim merkezi için minimal, store scoped yapı kur.
2. Feature flag:
   - `CUSTOMER_CARE_GOVERNANCE_ENABLED=false`
   - Varsayılan kapalı.
3. Roller:
   - owner,
   - admin,
   - supervisor,
   - agent,
   - analyst,
   - auditor.
4. Permission matrix:
   - inbox görüntüleme,
   - agent reply gönderme,
   - AI draft üretme,
   - automatic mode açma/kapatma,
   - public channel auto reply yönetme,
   - knowledge article publish,
   - integration webhook yönetme,
   - secret rotate,
   - quality review approve,
   - analytics/export,
   - anonymization/retention çalıştırma,
   - circuit breaker force close/open.
5. Yeni tablolar gerekiyorsa minimal tut:
   - `support_role_assignments`
   - `support_approval_requests`
   - `support_approval_decisions`
6. Riskli işlemler için approval workflow:
   - public comment automatic reply açma,
   - Google negatif yorum automatic açma,
   - webhook secret rotate/create,
   - knowledge article publish,
   - force close circuit breaker,
   - anonymization `--force`,
   - budget cap yükseltme.
7. No self-approval:
   - Request’i oluşturan kullanıcı kendi request’ini onaylayamaz.
   - En az owner/admin/supervisor rol ayrımı doğrulanmalı.
8. Service-level enforcement:
   - Sadece UI’da buton gizlemek yeterli değildir.
   - Kritik servis metotları actor + permission doğrulamalı.
9. UI:
   - `/customer-care/governance`
   - Rol matrisi,
   - bekleyen onaylar,
   - geçmiş kararlar,
   - riskli aksiyon katalogu.
   - Mobilde kart görünümü; desktop’ta tablo + sağ detay paneli.
10. Audit:
   - Tüm approval request/decision append-only kaydedilmeli.
   - PII redacted.
   - Cross-store sızıntı yok.
11. Tests:
   - Feature flag kapalı route 404.
   - Agent integration secret göremez/değiştiremez.
   - Analyst export görebilir ama reply gönderemez.
   - Public auto reply enable approval gerektirir.
   - Request owner self-approve edemez.
   - Cross-store approval okunamaz/onaylanamaz.
   - Service-level permission guard UI bypass’ını engeller.

## Kapsam Dışı

- HR/performance prim sistemi.
- Tam kurumsal SSO/OIDC.
- Mevcut kullanıcı yönetimini kökten değiştirmek.

---

# Dalga AI — Compliance Center v2: KVKK/GDPR, Consent ve Data Lineage

## Amaç

ZOLM kullanan firmaya “müşteri verisini güvenle yönetiyoruz” kanıtını vermek. Bu dalga kişisel veri talepleri, kanal bazlı izin/consent, legal hold ve veri soy ağacı görünürlüğü kurar.

Not: Dalga adı `AI` harf sırası içindir; yapay zeka motoru anlamına gelmez.

## Kapsam

1. Feature flag:
   - `CUSTOMER_CARE_COMPLIANCE_CENTER_ENABLED=false`
2. Data subject request modeli:
   - access/export,
   - rectification note,
   - anonymization request,
   - delete request değerlendirme,
   - legal hold.
3. Yeni tablolar gerekiyorsa:
   - `support_data_subject_requests`
   - `support_consent_records`
   - `support_legal_holds`
   - `support_data_lineage_events`
4. Consent ledger:
   - Kanal bazlı:
     - WhatsApp,
     - web chat,
     - Instagram/Facebook DM,
     - Google review,
     - marketplace.
   - Consent yoksa pazarlama/proaktif mesaj fail-closed.
   - Operasyonel müşteri hizmeti cevabı ile pazarlama/proaktif mesaj ayrımı yapılmalı.
5. Legal hold:
   - Legal hold aktifse anonymization/delete işlemi engellenmeli.
   - Engelleme audit’e yazılmalı.
6. Data lineage:
   - Bir müşteri mesajının hangi tablolara/projeksiyonlara/AI run’lara/dispatch’lere etki ettiği maskeli olarak izlenebilmeli.
   - Raw payload gösterme yok.
7. Secure export:
   - Data subject access export PII içerir; bu nedenle:
     - sadece yetkili role,
     - approval gerekebilir,
     - audit zorunlu,
     - export dosyasında XML kontrol karakterleri temiz,
     - UTF-8/BOM kuralları uygulanmalı.
   - Internal analytics export ise PII redacted kalmalı.
8. Commands:
   - `customer-care:compliance-report --store=ID --dry-run`
   - `customer-care:retention-scan --store=ID --dry-run`
   - `customer-care:consent-audit --store=ID --dry-run`
9. UI:
   - `/customer-care/compliance`
   - DSR kuyruğu,
   - consent durumu,
   - legal hold listesi,
   - retention scan sonuçları,
   - data lineage arama.
10. Tests:
   - Feature flag kapalı route 404.
   - Legal hold anonymization’ı engeller.
   - Consent yoksa proaktif/satış mesajı engellenir.
   - Operasyonel reply consent eksikliğinden yanlış bloklanmaz.
   - DSR export yetkisiz kullanıcıya kapalı.
   - Export encoding/XML sanitization doğru.
   - Cross-store DSR/consent/legal hold sızmaz.
   - Data lineage raw webhook payload göstermez.

## Kapsam Dışı

- Hukuki belge üretimi.
- Otomatik DPA/Sözleşme imzalama.
- Gerçek e-devlet/KVKK portal entegrasyonu.

---

# Dalga AJ — Production Reliability, Queue Backpressure ve Dead-Letter Operasyonları

## Amaç

Müşteri İletişim Merkezi’ni yüksek hacimli canlı kullanımda güvenilir hale getirmek: queue sağlığı, rate limit, backpressure, dead-letter replay, idempotency ve operasyonel kurtarma araçları. Bu dalga “çalışıyor”dan “yük altında kontrollü çalışıyor” seviyesine çıkarır.

## Kapsam

1. Feature flag:
   - `CUSTOMER_CARE_RELIABILITY_CENTER_ENABLED=false`
2. Queue/worker health:
   - Generic dispatch,
   - integration delivery,
   - AI eval,
   - suggestion generation,
   - webhook projection işleri için queue lag ve failure görünürlüğü.
3. Backpressure policy:
   - Store/channel bazlı eşikler:
     - pending dispatch count,
     - retry backlog,
     - dead-letter count,
     - provider rate limit.
   - Eşik aşılırsa:
     - auto reply durur veya copilot’a düşer,
     - manual reply etkilenmez,
     - readiness/pilot dashboard uyarı verir.
4. Rate limiting:
   - Channel bazlı gönderim limiti:
     - Trendyol,
     - WhatsApp,
     - Meta comment/DM,
     - Google review,
     - web chat.
   - Gerçek platform limitleri bilinmiyorsa güvenli default ve config.
   - Limit yoksa limitsiz varsayma; explicit config gerekir.
5. Dead-letter replay:
   - Replay sadece yetkili role/approval ile.
   - Terminal başarısız kayıtlar otomatik silinmez.
   - Replay idempotency key’i korur.
   - Cross-store replay engellenir.
6. Idempotency hardening:
   - External message IDs,
   - webhook event IDs,
   - support dispatch idempotency,
   - integration event idempotency için DB unique garantileri gözden geçirilmeli.
   - Uygulama katmanı kontrolü tek başına yeterli değil.
7. Commands:
   - `customer-care:queue-health --store=ID`
   - `customer-care:replay-deadletters --store=ID --type=dispatch --dry-run`
   - `customer-care:rate-limit-report --store=ID`
8. UI:
   - `/customer-care/reliability`
   - Queue lag,
   - backlog,
   - dead-letter,
   - replay kuyruğu,
   - rate limit durumları,
   - backpressure kararları.
9. Scheduler:
   - Gerekirse 5/15 dakikalık health scan.
   - Scheduler tekrar eden duplicate job yaratmamalı.
10. Tests:
   - Feature flag kapalı route 404.
   - Backpressure auto reply engeller, manual reply engellemez.
   - Dead-letter replay cross-store engellenir.
   - Replay idempotency duplicate dispatch üretmez.
   - Rate limit aşılırsa external send yapılmaz.
   - DB unique constraint duplicate webhook/projection event’i engeller.
   - Queue health veri yoksa sahte “healthy” üretmez; unknown/no data ayrı gösterilir.
   - Scheduler kayıtları beklenen komutları listeler.

## Kapsam Dışı

- Redis zorunluluğu dayatmak.
- Kubernetes/Horizon zorunlu kurulum.
- Canlı platform limitlerini varsayarak agresif gönderim yapmak.
- Otomatik dead-letter silme.

---

## Ortak Kabul Kriterleri

Bu üç dalga tamamlandığında Antigravity şu kanıtları vermeden durmamalıdır:

```bash
git diff --check
./vendor/bin/sail artisan test tests/Feature/CustomerCare --no-coverage --compact
./vendor/bin/sail artisan test --no-coverage --compact
npm run build
./vendor/bin/sail artisan route:list --name=customer-care
./vendor/bin/sail artisan list customer-care --raw
./vendor/bin/sail artisan schedule:list
```

Ek kanıtlar:

- Migration apply ve rollback çıktıları.
- Yeni route ve command envanteri.
- Yeni feature flag varsayılanlarının kapalı olduğunu gösteren test.
- `docs/customer-care/dalga-ah-kanit-paketi.md`
- `docs/customer-care/dalga-ai-kanit-paketi.md`
- `docs/customer-care/dalga-aj-kanit-paketi.md`
- Güncel `walkthrough.md`

---

## Antigravity’ye Verilecek Kısa Komut

```text
/Volumes/TWINMOS/zolm reposunda Dalga AH/AI/AJ görevlerini uygula.

Önce şu dosyayı tamamen oku ve içindeki talimatları eksiksiz uygula:

/Volumes/TWINMOS/zolm/docs/customer-care/dalga-ahaiaj-antigravity-promptu.md

Ön koşul kontrolü yap:
Dalga AB/AC/AD ve AE/AF/AG kalite kapılarında açık P0/P1 varsa uygulamaya başlama; durumu raporla ve dur.

Yalnız Dalga AH/AI/AJ kapsamını uygula.
Kapsam dışındaki mevcut kullanıcı değişikliklerine dokunma.
Commit, push veya branch değişikliği yapma.
Dalga AK veya başka kapsama geçme.
Test, build, route, command, scheduler, migration/rollback ve git kanıt paketini verdikten sonra dur.
```

