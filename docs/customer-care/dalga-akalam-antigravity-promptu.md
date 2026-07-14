# ZOLM AI Müşteri İletişim Merkezi — Dalga AK/AL/AM Antigravity Uygulama Promptu

**Tarih:** 2026-07-13  
**Hazırlayan:** Codex — Baş mühendis planı  
**Ön koşul:** Dalga AH/AI/AJ kalite kapısı kabul edilmeden uygulanmamalıdır. Açık P0/P1 kalite kapısı varsa bu promptu uygulamaya başlama; durumu raporla ve dur.  
**Kapsam:** Dalga AK, AL, AM uygulanacak; sonraki dalgalara geçilmeyecek.

---

## 0. Genel Talimatlar

/Volumes/TWINMOS/zolm reposunda çalış.

Önce şu dosyaları tamamen oku:

- `AGENTS.md`
- `docs/customer-care/dalga-ahaiaj-kabul-karari.md`
- `docs/customer-care/dalga-aeafag-kalite-kapisi-02-kabul-karari.md`
- `docs/customer-care/pilot-runbook.md`
- `docs/customer-care/kvkk-retention-policy.md`
- `docs/customer-care/integration-hub-contract.md`
- `docs/customer-care/adr/001-support-projection-cekirdegi.md`
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
- Sahte başarı üretme: gerçek provider/connector yoksa capabilities unavailable ve işlem fail-closed olmalı.
- Tenant/store izolasyonu query, command, service ve UI action seviyesinde doğrulanmalı.
- PII/KVKK verisi log, export, webhook, launch raporu, reconciliation raporu veya release notlarına raw sızmamalı.
- Yeni UI varsa ZOLM Kurumsal Açık Panel Sistemi ve mobil responsive kurallarına uy.
- CSV/Excel export varsa ZOLM export kurallarını uygula.
- Dalga AN veya başka kapsama geçme.

---

# Dalga AK — Production Launch Orchestrator ve Kontrollü Rollout Merkezi

## Amaç

Müşteri İletişim Merkezi’ni “hazır görünüyor” seviyesinden “kontrollü biçimde canlıya alınabilir” seviyesine taşımak. Bu dalga; pilot açılış kararı, canary yüzdesi, rollout adımları, go/no-go kontrolleri, rollback ve emergency stop akışlarını tek release orkestrasyon merkezinde toplar.

Bu dalga hiçbir mağazayı otomatik canlıya almaz. Yalnız güvenli launch state machine ve kontrol yüzeyi kurar.

## Kapsam

1. Feature flag:
   - `CUSTOMER_CARE_LAUNCH_CENTER_ENABLED=false`
   - Varsayılan kapalı.
2. Launch state machine:
   - `draft`
   - `readiness_failed`
   - `ready_for_approval`
   - `approved`
   - `canary`
   - `paused`
   - `rolled_back`
   - `completed`
3. Yeni tablolar gerekiyorsa minimal ve store scoped:
   - `support_launch_plans`
   - `support_launch_plan_steps`
   - `support_launch_events`
4. Launch plan içeriği:
   - store_id,
   - hedef kanallar,
   - başlangıç modu: `manual`, `copilot`, `automatic` ama automatic için ek gate zorunlu,
   - canary yüzdesi veya konuşma limiti,
   - allowed conversation categories,
   - rollback kriterleri,
   - approver bilgisi,
   - immutable snapshot: readiness, golden eval, circuit, budget, quota, compliance, queue health.
5. Go/no-go checklist:
   - `CustomerCarePilotReadinessService`,
   - latest golden eval,
   - circuit breaker closed veya safe state,
   - provider health,
   - budget/quota,
   - policy engine enabled,
   - integration secret health,
   - compliance/legal hold conflict yok,
   - queue health unknown/critical ise fail-closed.
6. Governance approval:
   - Launch plan `canary` veya `completed` aşamasına geçmeden önce riskli işlem onayı gerektirmeli.
   - Self-approval yasak.
   - Approval append-only kalmalı.
7. Rollout guard:
   - Launch plan approved olsa bile `CustomerCareAutomationGate` bypass edilmemeli.
   - Gate tek gerçek otomasyon kapısı olarak kalmalı.
   - Launch plan yalnız ek üst katman guard’ı olmalı.
8. Rollback:
   - rollback işlemi:
     - ilgili mağazada automatic mode’u kapatır veya copilot/manual moda çeker,
     - pending AI dispatch’leri güvenli şekilde iptal eder,
     - agent dispatch’leri iptal etmez,
     - `support_launch_events` içine append-only kayıt yazar.
9. UI:
   - `/customer-care/launch`
   - Launch plan listesi,
   - readiness snapshot,
   - go/no-go checklist,
   - canary limitleri,
   - rollback paneli,
   - event timeline.
10. Artisan command:
    - `customer-care:launch-check --store=ID --dry-run`
    - `customer-care:launch-rollback --store=ID --plan=ID --dry-run`
    - Dry-run varsayılan; mutasyon için `--execute` zorunlu.
11. Tests:
    - Feature flag kapalı route 404.
    - Readiness failed ise launch plan approved/canary olamaz.
    - Queue health `unknown` veya `critical` ise go/no-go fail-closed.
    - Approval olmadan canary başlatılamaz.
    - Self-approval launch için engellenir.
    - Rollback AI pending dispatch iptal eder, agent dispatch’i korur.
    - Launch snapshot store scoped ve immutable davranır.
    - Cross-store launch plan okunamaz/değiştirilemez.
    - Dry-run command veri değiştirmez.

## Kapsam Dışı

- Gerçek production feature flag’leri otomatik açmak.
- Gerçek müşteri mesajı otomatik göndermek.
- CI/CD deployment pipeline kurmak.
- Billing plan yükseltme/satın alma akışı.

---

# Dalga AL — Projection Backfill, Data Reconciliation ve Recovery Center

## Amaç

Farklı kanallardan gelen mesaj, yorum, soru ve cevap projeksiyonlarının zaman içinde drift üretmesini engellemek. Webhook kaçırma, tekrar event, yarım kalmış import, provider geçici hata veya eski verinin sonradan gelmesi gibi durumlarda support inbox’ın güvenli biçimde kendini toparlamasını sağlamak.

Bu dalga canlı dış API’ye bağlanmak zorunda değildir; mevcut connector/provider contract’larını kullanır, yoksa fake success üretmez.

## Kapsam

1. Feature flag:
   - `CUSTOMER_CARE_RECONCILIATION_CENTER_ENABLED=false`
   - Varsayılan kapalı.
2. Reconciliation hedefleri:
   - `support_conversations`
   - `support_messages`
   - `support_dispatches`
   - marketplace question projection,
   - WhatsApp projection,
   - Meta social projection,
   - Google review projection,
   - web chat projection.
3. Yeni tablolar gerekiyorsa:
   - `support_projection_cursors`
   - `support_reconciliation_runs`
   - `support_reconciliation_findings`
4. Cursor modeli:
   - store_id,
   - channel_id,
   - channel_type,
   - cursor_key,
   - last_seen_external_id,
   - last_synced_at,
   - checksum/hash snapshot,
   - status.
5. Idempotent backfill:
   - event_id/external_id/message hash ile duplicate engeli.
   - Raw payload support message’a yazılmaz.
   - Provider unavailable ise fail-closed, “başarılı sync” uydurma yok.
6. Drift detection:
   - missing conversation,
   - missing message,
   - duplicate projection,
   - orphan dispatch,
   - channel/store mismatch,
   - stale cursor,
   - failed projection.
7. Repair actions:
   - `dry_run` default.
   - Repair mutasyonu için:
     - `--execute`,
     - system actor,
     - governance approval veya güvenli permission,
     - append-only audit.
   - Raw data restore yok; yalnız projection kaydı düzeltme/yeniden projeksiyon.
8. UI:
   - `/customer-care/reconciliation`
   - Run listesi,
   - bulgu grupları,
   - risk seviyesi,
   - dry-run preview,
   - repair queue,
   - cursor health.
9. Artisan commands:
   - `customer-care:reconcile-projections --store=ID --channel=... --dry-run`
   - `customer-care:repair-projection --finding=ID --dry-run`
10. Scheduler:
    - Varsayılan kapalı veya no-op.
    - Eğer schedule eklenirse feature flag ve dry-run/safe mode kontrolü şart.
11. Tests:
    - Backfill duplicate message üretmez.
    - Cross-store external_id IDOR engellenir.
    - Missing provider fake success dönmez.
    - Raw webhook payload support message’a sızmaz.
    - Drift finding store scoped.
    - Repair dry-run veri değiştirmez.
    - Repair execute approval olmadan fail-closed.
    - Cursor unknown state UI’da healthy gibi gösterilmez.
    - Scheduler varsa feature flag kapalıyken mutasyon yapmaz.

## Kapsam Dışı

- Tüm eski production verisini gerçek API’den çekmek.
- Kanal provider’larının eksik production connector’larını tamamlamak.
- Veri silme/anonymization işlemleri; bunlar compliance/retention alanında kalır.

---

# Dalga AM — Knowledge, Policy ve Prompt Release Management

## Amaç

Bilgi merkezi, marka sesi, kanal policy kuralları ve AI prompt ayarlarının “elle değiştirildi ve canlıya düştü” riskini kapatmak. Bu dalga; taslak, inceleme, onay, yayınlama, rollback ve etki ölçümü olan release lifecycle kurar.

Amaç fine-tuning değildir. Amaç, ZOLM’ün müşteriye verdiği cevap davranışının kontrollü ve geri alınabilir şekilde yönetilmesidir.

## Kapsam

1. Feature flag:
   - `CUSTOMER_CARE_RELEASE_CENTER_ENABLED=false`
   - Varsayılan kapalı.
2. Release edilen artifact tipleri:
   - knowledge article,
   - brand voice config,
   - policy rule,
   - prompt/system instruction template,
   - channel-specific answer template.
3. Yeni tablolar gerekiyorsa:
   - `support_release_packages`
   - `support_release_package_items`
   - `support_release_events`
   - `support_artifact_versions`
4. Versioning:
   - Her artifact immutable version snapshot olarak saklanmalı.
   - “current” pointer ayrı tutulmalı.
   - Rollback previous approved version’a dönebilmeli.
5. Approval:
   - Knowledge publish, policy rule publish ve prompt değişikliği governance approval gerektirir.
   - Self-approval yasak.
6. Pre-release checks:
   - PII redaction check,
   - prompt injection check,
   - policy conflict check,
   - golden eval smoke check,
   - channel public/private risk classification.
7. Staged release:
   - draft,
   - review,
   - approved,
   - staged,
   - published,
   - rolled_back,
   - rejected.
8. AI context integration:
   - Runtime AI context yalnız published/current artifact version’larını kullanmalı.
   - Draft veya rejected artifact otomatik prompt/context içine girmemeli.
9. UI:
   - `/customer-care/releases`
   - Release package listesi,
   - artifact diff görünümü,
   - preflight check sonuçları,
   - approval durumu,
   - publish/rollback butonları.
10. Artisan commands:
    - `customer-care:release-preflight --store=ID --package=ID`
    - `customer-care:release-rollback --store=ID --package=ID --dry-run`
11. Tests:
    - Draft artifact runtime AI context’e girmez.
    - Published version runtime context’te kullanılır.
    - Rejected/pending package publish edilemez.
    - Approval olmadan policy/prompt publish fail-closed.
    - Self-approval publish engellenir.
    - PII içeren release item preflight’ta bloklanır veya maskelenir.
    - Prompt injection içeren release item bloklanır.
    - Rollback previous approved version’a döner.
    - Cross-store release package okunamaz/yayınlanamaz.
    - Release diff/export PII raw sızdırmaz.

## Kapsam Dışı

- LLM fine-tuning pipeline.
- Prompt marketplace.
- Çok ortamlı deployment sistemi (staging/prod sunucuları).
- Gerçek A/B experimentation motoru; yalnız release lifecycle ve temel smoke/eval check.

---

## Kanıt Paketleri

Dalga AK/AL/AM için ayrı kanıt paketleri oluştur:

- `docs/customer-care/dalga-ak-kanit-paketi.md`
- `docs/customer-care/dalga-al-kanit-paketi.md`
- `docs/customer-care/dalga-am-kanit-paketi.md`

Her pakette şunlar olsun:

1. Değiştirilen/eklenen dosyalar.
2. Migration listesi ve rollback notu.
3. Route/command/scheduler listesi.
4. Feature flag varsayılanları.
5. Tenant/KVKK/fail-closed güvenlik kanıtları.
6. Test komutları ve sonuçları.
7. Bilinen kapsam dışı maddeler.

`walkthrough.md` dosyasını da Dalga AK/AL/AM özetiyle güncelle.

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
/Volumes/TWINMOS/zolm reposunda Dalga AK/AL/AM görevlerini uygula.

Önce şu dosyayı tamamen oku ve içindeki talimatları eksiksiz uygula:

/Volumes/TWINMOS/zolm/docs/customer-care/dalga-akalam-antigravity-promptu.md

Yalnız Dalga AK/AL/AM kapsamını uygula.
Kapsam dışındaki mevcut kullanıcı değişikliklerine dokunma.
Commit, push veya branch değişikliği yapma.
Dalga AN veya başka kapsama geçme.
Test, build, route, command, scheduler ve git kanıt paketini verdikten sonra dur.
```
