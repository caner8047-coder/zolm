# ZOLM AI Müşteri İletişim Merkezi — Dalga AT/AU/AV Antigravity Uygulama Promptu

**Tarih:** 2026-07-13  
**Hazırlayan:** Codex — Baş mühendis planı  
**Ön koşul:** Dalga AQ/AR/AS kalite kapısı baş mühendis tarafından kabul edilmeden uygulanmamalıdır. Açık P0/P1 kalite kapısı varsa bu promptu uygulamaya başlama; durumu raporla ve dur.  
**Kapsam:** Dalga AT, AU, AV uygulanacak; sonraki dalgalara geçilmeyecek.

> Baş mühendis notu: Bu üçlü artık “özellik ekleme” dalgası değil; müşteri temsilcisi verimliliği, entegrasyon sertifikasyonu ve production go-live kapanış katmanıdır. Amaç modülü canlı pilot / ilk müşteri kullanımı için daha işletilebilir hale getirmektir.

---

## 0. Genel Talimatlar

/Volumes/TWINMOS/zolm reposunda çalış.

Önce şu dosyaları tamamen oku:

- `AGENTS.md`
- `docs/customer-care/dalga-akalam-kabul-karari.md`
- `docs/customer-care/dalga-anaoap-kabul-karari.md`
- `docs/customer-care/dalga-aqaras-antigravity-promptu.md`
- Eğer varsa `docs/customer-care/dalga-aqaras-kabul-karari.md`
- `docs/customer-care/adr/002-tenant-ve-organizasyon-siniri.md`
- `docs/customer-care/adr/003-generic-outbound-dispatch.md`
- `docs/customer-care/adr/006-human-ownership-state-machine.md`
- `docs/customer-care/kvkk-retention-policy.md`
- `docs/customer-care/integration-hub-contract.md`
- `docs/customer-care/enterprise-api-contract.md`
- `docs/customer-care/pilot-runbook.md`

Kurallar:

- Commit, push veya branch değişikliği yapma.
- Kapsam dışındaki kullanıcı/Codex değişikliklerine dokunma.
- Açık kalite kapısı varsa uygulamaya başlama; sadece blokaj raporu ver.
- Feature flag varsayılanlarını kapalı tut.
- Otomatik yanıtı global veya mağaza bazında kendiliğinden açma.
- Testlerde canlı dış API çağrısı yapma; fake/mock kullan.
- Sahte başarı, sahte connector sertifikası, sahte go-live onayı, sahte tenant izolasyonu veya fabricated KPI üretme.
- Tenant/store/organization izolasyonu query, command, service ve UI action seviyesinde doğrulanmalı.
- PII/KVKK verisi log, export, certification report, production readiness report veya evidence pack içine raw sızmamalı.
- Yeni UI varsa ZOLM Kurumsal Açık Panel Sistemi ve mobil responsive kurallarına uy.
- CSV/Excel export varsa ZOLM export kurallarını uygula.
- Dalga AW veya başka kapsama geçme.

---

# Dalga AT — Agent Workspace v2, Makrolar ve Temsilci Verimliliği

## Amaç

Müşteri İletişim Merkezi’nin temsilci tarafını gerçek operasyona yaklaştırmak: kayıtlı cevap şablonları, dahili notlar, görev/presence görünürlüğü ve güvenli temsilci üretkenlik araçları. Bu dalga otomatik yanıt davranışını genişletmez; insan temsilcinin hızlı ve kontrollü çalışmasını sağlar.

## Kapsam

1. Feature flag:
   - `CUSTOMER_CARE_AGENT_WORKSPACE_ENABLED=false`
   - Varsayılan kapalı.
2. Rota:
   - `/customer-care/agent-workspace`
   - route adı: `customer-care.agent-workspace`
   - `auth` + customer-care feature middleware ile korunmalı.
3. Makro / kayıtlı cevap altyapısı:
   - `support_reply_macros`
   - `support_reply_macro_versions` veya append-only değişiklik geçmişi.
   - Store/organization scoped.
   - Macro alanları: title, body, category, channel_scope, language, is_active, variables_schema.
   - PII redaction ve prompt-injection guard.
   - Makro gövdesi dış link/telefon/IBAN gibi kanal policy ihlallerine karşı validate edilmeli.
4. Makro kullanımı:
   - Temsilci makroyu seçince cevap taslağı olarak doldurulur.
   - Makro kullanımı doğrudan gönderim yapmaz.
   - Gönderim hâlâ `SupportReplyService::sendAgentReply()` üzerinden policy/outbox guard ile yapılmalı.
5. Dahili notlar:
   - `support_internal_notes`
   - PII masked/encrypted storage.
   - Customer’a gönderilmez, outbound dispatch oluşturmaz.
   - Store/organization scope zorunlu.
6. Presence / collision görünürlüğü:
   - Aynı konuşmayı görüntüleyen/üzerinde çalışan temsilciler için soft presence kaydı.
   - Gönderimi kilitleme zorunlu değil; mevcut ownership/human lock state machine bozulmamalı.
   - Presence TTL ile temizlenmeli.
7. Saved views:
   - Temsilci kişisel filtre görünümleri: kanal, durum, ownership, SLA, priority.
   - Cross-store view sızıntısı engellenmeli.
8. UI:
   - ZOLM Kurumsal Açık Panel tasarımı.
   - Sol: saved views / queue filter.
   - Orta: konuşma üretkenlik yüzeyi.
   - Sağ: makrolar, notlar, presence, son aksiyonlar.
   - Mobilde paneller dikey ve 44px touch hedefleriyle kullanılabilir olmalı.
9. Commands:
   - `customer-care:macro-audit --store=ID --dry-run`
   - Makro policy ihlali ve PII taraması yapar; dry-run default.
10. Tests:
    - Feature flag kapalı route 404.
    - Cross-store macro görüntüleme/kullanma fail-closed.
    - Makro body PII ve prompt injection içerirse kaydedilmez veya maskelenir.
    - Policy ihlal eden macro agent reply gönderiminde dispatch oluşturmaz.
    - Dahili not outbound message/dispatch oluşturmaz.
    - Dahili not PII masked/encrypted saklanır.
    - Presence TTL ve store isolation çalışır.
    - Saved view cross-store sızdırmaz.
    - Agent reply manuel olduğu için auto-reply quota/budget kapılarına takılmaz.

## Kapsam Dışı

- Canlı chat websocket altyapısı.
- Yeni AI otomatik yanıt yeteneği.
- Temsilci vardiya/maaş/İK planlama modülü.
- Global inbox davranışını kıracak büyük refactor.

---

# Dalga AU — Connector Certification, Sandbox ve Entegrasyon Sağlık Karnesi

## Amaç

ZOLM’ün desteklediği tüm müşteri kanallarını canlıya almadan önce deterministic sertifikasyon testlerinden geçirmek. Burada amaç “provider varmış gibi başarı üretmek” değil; gerçek connector/provider hazır değilse bunu açıkça unavailable/fail-closed göstermek.

## Kapsam

1. Feature flag:
   - `CUSTOMER_CARE_CONNECTOR_CERTIFICATION_ENABLED=false`
   - Varsayılan kapalı.
2. Rota:
   - `/customer-care/certification`
   - route adı: `customer-care.certification`
3. Certification servisleri:
   - `CustomerCareConnectorCertificationService`
   - Kanal bazında health, capability, policy, outbound dry-run, inbound simulation, tenant boundary, secret hygiene kontrolleri.
4. Tablolar gerekiyorsa:
   - `support_connector_certification_runs`
   - `support_connector_certification_checks`
   - Append-only sonuç mantığı.
5. Desteklenecek kanal profilleri:
   - Trendyol
   - Hepsiburada
   - N11
   - WhatsApp
   - Meta Social
   - Google Business Profile
   - Web Chat
   - Enterprise API
6. Sertifikasyon check örnekleri:
   - Feature flag durumu.
   - Channel enabled/configured.
   - Connector interface bind edilmiş mi?
   - Capability available mı?
   - `canReply()` beklenen sonucu veriyor mu?
   - Outbox dry-run fake sent üretmiyor mu?
   - Public comment/review automatic default kapalı mı?
   - Webhook/HMAC secret encrypted mı?
   - Token/plain secret response/log/export içinde yok mu?
   - Cross-store external ID fail-closed mu?
7. Sandbox inbound simulation:
   - Gerçek dış API çağrısı yok.
   - Signed fixture payload kullan.
   - Web chat için HMAC doğrulama zorunlu.
   - Meta/GBP gibi public alanlarda auto-reply default kapalı kalmalı.
8. UI:
   - ZOLM açık panel.
   - Store/channel seçimi.
   - Sertifikasyon matrisi: pass/warn/fail/unknown.
   - Son çalıştırmalar, evidence linkleri, remediation önerileri.
9. Commands:
   - `customer-care:certify-connectors --store=ID --dry-run`
   - `customer-care:simulate-channel-event --store=ID --channel=web_chat --fixture=... --dry-run`
10. Docs:
   - `docs/customer-care/connector-certification-runbook.md`
   - Kanal bazında live öncesi minimum kriterler.
11. Tests:
   - Feature flag kapalı route 404.
   - Connector yoksa pass değil fail/unavailable.
   - Sertifikasyon dry-run DB mutasyonunu sınırlı/append-only yapar; outbound dispatch oluşturmaz.
   - Web chat invalid signature simulation fail-closed.
   - Meta/GBP connector bound değilse send capability unavailable.
   - Cross-store channel certification erişimi engellenir.
   - Report/evidence PII veya secret içermez.
   - Command dry-run default çalışır.

## Kapsam Dışı

- Gerçek provider credential doğrulamasını canlı API’ye bağlamak.
- Connector eksikse adapter içinde sahte başarı üretmek.
- Kanalı otomatik enabled yapmak.
- Public reply automatic mode açmak.

---

# Dalga AV — Production Go-Live Center, Final Readiness ve Freeze Evidence

## Amaç

Müşteri İletişim Merkezi’ni canlı pilot / ilk müşteri yayınına almadan önce tek merkezli, denetlenebilir ve geri alınabilir final kapıdan geçirmek. Bu dalga canlı ayarları kendiliğinden değiştirmez; readiness, approval, freeze snapshot ve rollback drill kanıtı üretir.

## Kapsam

1. Feature flag:
   - `CUSTOMER_CARE_PRODUCTION_CENTER_ENABLED=false`
   - Varsayılan kapalı.
2. Rota:
   - `/customer-care/production`
   - route adı: `customer-care.production`
3. Final readiness service:
   - `CustomerCareProductionReadinessService`
   - Aşağıdaki alt kontrolleri toplar:
     - Launch readiness
     - Connector certification
     - Security audit
     - Compliance/legal hold/DSR risk
     - Entitlement/commercial status
     - Enterprise API token hygiene
     - AI provider health
     - Budget status
     - Outbox/queue health
     - Circuit breaker state
     - Golden eval freshness
     - Knowledge stale-data risk
     - Public channel auto-reply guards
4. Freeze snapshot:
   - `support_production_readiness_runs`
   - `support_production_freeze_snapshots`
   - Store/organization scoped.
   - Snapshot immutable/append-only mantıkla saklanmalı.
   - Raw PII/secret yok.
5. Governance:
   - Production go-live approval gerektirir.
   - Self-approval yok.
   - Approval consumed append-only modelle işaretlenir.
6. Rollback drill:
   - `customer-care:production-rollback-drill --store=ID --dry-run`
   - Gerçek mutasyon yapmaz.
   - Rollback path, pending AI dispatch count, channel mode değişiklik planı ve circuit breaker state’i raporlar.
7. Final evidence pack:
   - `customer-care:production-evidence-pack --store=ID`
   - Markdown rapor üretir.
   - Route/command/scheduler inventory, latest test command önerileri, readiness summary, certification summary, security/compliance summary içerir.
   - PII/secret/token maskeli olmalı.
8. UI:
   - ZOLM açık panel.
   - Üstte final readiness skoru.
   - Alt kontroller pass/warn/fail/unknown.
   - Freeze snapshot geçmişi.
   - Approval durumu.
   - Rollback drill çıktısı.
   - “Go-live için uygun değil” durumlarında açık remediation listesi.
9. Tests:
   - Feature flag kapalı route 404.
   - Cross-store production readiness erişimi engellenir.
   - Missing connector certification varsa production ready false.
   - Critical security finding varsa ready false.
   - Stale golden eval varsa ready false.
   - Governance approval olmadan freeze/go-live kabul edilmez.
   - Freeze snapshot raw PII/secret içermez.
   - Rollback drill dry-run mutasyon yapmaz.
   - Evidence pack Markdown/UTF-8/XML safe ve PII-free.
   - Agent/manual reply davranışları bu dalgada etkilenmez.

## Kapsam Dışı

- `.env` veya production config değerlerini runtime’da değiştirmek.
- Otomatik canlıya alma.
- Gerçek ödeme/fatura kesme.
- Kubernetes, server provisioning veya CI/CD pipeline kurmak.

---

## Kabul Kanıtları

Uygulama bittikten sonra şu komutlar çalıştırılacak ve sonuçlar rapora yazılacak:

```bash
git diff --check
npm run build
./vendor/bin/sail artisan route:list --name=customer-care
./vendor/bin/sail artisan list customer-care --raw
./vendor/bin/sail artisan schedule:list
./vendor/bin/sail artisan test tests/Feature/CustomerCare --no-coverage --compact
./vendor/bin/sail artisan test --no-coverage --compact
```

Dalga AT/AU/AV için ayrı kanıt paketleri oluştur:

- `docs/customer-care/dalga-at-kanit-paketi.md`
- `docs/customer-care/dalga-au-kanit-paketi.md`
- `docs/customer-care/dalga-av-kanit-paketi.md`

`walkthrough.md` dosyasını da Dalga AT/AU/AV özetiyle güncelle.

İş bitince dur; kalite kapısı için Codex baş mühendis kontrolünü bekle.

---

## Antigravity’ye Gönderilecek Kısa Komut

```text
/Volumes/TWINMOS/zolm reposunda Dalga AT/AU/AV görevlerini uygula.

Önce şu dosyayı tamamen oku ve içindeki talimatları eksiksiz uygula:

/Volumes/TWINMOS/zolm/docs/customer-care/dalga-atauav-antigravity-promptu.md

Yalnız Dalga AT/AU/AV kapsamını uygula.
Kapsam dışındaki mevcut kullanıcı/Codex değişikliklerine dokunma.
Commit, push veya branch değişikliği yapma.
Dalga AW veya başka kapsama geçme.
Test, build, route, command, scheduler ve git kanıt paketini verdikten sonra dur.
```
