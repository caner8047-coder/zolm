# ZOLM AI Müşteri İletişim Merkezi — Dalga AW/AX/AY Antigravity Uygulama Promptu

**Tarih:** 2026-07-13  
**Hazırlayan:** Codex — Baş mühendis planı  
**Ön koşul:** Dalga AQ/AR/AS ve Dalga AT/AU/AV kalite kapıları baş mühendis tarafından kabul edilmeden uygulanmamalıdır. Açık P0/P1 kalite kapısı varsa bu promptu uygulamaya başlama; durumu raporla ve dur.  
**Kapsam:** Dalga AW, AX, AY uygulanacak; sonraki dalgalara geçilmeyecek.

> Baş mühendis notu: Bu üçlü, canlıya çıkmış veya canlıya çıkmaya hazır Müşteri İletişim Merkezi’nin post-launch ürünleşme katmanıdır: müşteri geri bildirimi, geliştirici/partner ekosistemi ve olay yönetimi. Otomatik yanıtı genişletmek değil; sistemi ölçülebilir, denetlenebilir ve sürdürülebilir hale getirmek amaçlanır.

---

## 0. Genel Talimatlar

/Volumes/TWINMOS/zolm reposunda çalış.

Önce şu dosyaları tamamen oku:

- `AGENTS.md`
- `docs/customer-care/dalga-aqaras-kalite-kapisi-01.md`
- Eğer varsa `docs/customer-care/dalga-aqaras-kabul-karari.md`
- `docs/customer-care/dalga-atauav-antigravity-promptu.md`
- Eğer varsa `docs/customer-care/dalga-atauav-kabul-karari.md`
- `docs/customer-care/enterprise-api-contract.md`
- `docs/customer-care/connector-certification-runbook.md`
- `docs/customer-care/pilot-runbook.md`
- `docs/customer-care/security-threat-model.md`
- `docs/customer-care/kvkk-retention-policy.md`

Kurallar:

- Commit, push veya branch değişikliği yapma.
- Kapsam dışındaki kullanıcı/Codex değişikliklerine dokunma.
- Açık kalite kapısı varsa uygulamaya başlama; sadece blokaj raporu ver.
- Feature flag varsayılanlarını kapalı tut.
- Otomatik yanıtı global veya mağaza bazında kendiliğinden açma.
- Testlerde canlı dış API çağrısı yapma; fake/mock kullan.
- Sahte CSAT/NPS, sahte partner sertifikası, sahte incident kapanışı, sahte SLO veya fabricated post-launch KPI üretme.
- Tenant/store/organization izolasyonu query, command, service ve UI action seviyesinde doğrulanmalı.
- PII/KVKK verisi feedback, partner report, incident report, export veya evidence pack içine raw sızmamalı.
- Yeni UI varsa ZOLM Kurumsal Açık Panel Sistemi ve mobil responsive kurallarına uy.
- CSV/Excel export varsa ZOLM export kurallarını uygula.
- Dalga AZ veya başka kapsama geçme.

---

# Dalga AW — Voice of Customer, CSAT/NPS ve Feedback Loop

## Amaç

Müşteri İletişim Merkezi’nin gerçekten müşteri memnuniyetini artırıp artırmadığını ölçmek: konuşma sonrası CSAT/NPS, olumsuz geri bildirim sınıflandırması, kalite merkezine feedback aktarımı ve bilgi bankası öneri döngüsü. Bu dalga müşteriyle yeni bir otomatik pazarlama iletişimi başlatmaz; yalnız izinli ve güvenli feedback toplama altyapısı kurar.

## Kapsam

1. Feature flag:
   - `CUSTOMER_CARE_FEEDBACK_CENTER_ENABLED=false`
   - Varsayılan kapalı.
2. Rota:
   - `/customer-care/feedback`
   - route adı: `customer-care.feedback`
   - `auth` + customer-care feature middleware ile korunmalı.
3. Tablolar:
   - `support_feedback_requests`
   - `support_feedback_responses`
   - `support_feedback_insights`
   - Append-only response mantığı; müşteri cevabı değişirse yeni response event.
4. Feedback request:
   - Conversation resolved/closed sonrası oluşturulabilir.
   - Kanal consent ve policy kuralları geçmeden müşteri mesajı gönderilmez.
   - Public comment/review kanallarında otomatik feedback isteme default kapalı.
   - Handoff/human-owned konuşmalarda otomatik feedback request yaratma ancak temsilci onayıyla mümkün.
5. Feedback response:
   - CSAT 1-5
   - NPS 0-10
   - Serbest metin yorum
   - Kanal ve conversation scope
   - PII redaction zorunlu.
6. Insight service:
   - `CustomerCareFeedbackService`
   - Ortalama CSAT/NPS,
   - negatif feedback oranı,
   - tekrar eden konu başlıkları,
   - agent/AI kaynaklı ayrım,
   - fake metric üretmeden empty state.
7. Quality/Knowledge bağlantısı:
   - Düşük CSAT ve serbest metin feedback kalite inceleme adayı oluşturabilir.
   - Bilgi bankası önerisi oluşturulacaksa PII/prompt-injection guard uygulanmalı.
   - Hiçbir öneri otomatik publish edilmez.
8. UI:
   - ZOLM açık panel.
   - CSAT/NPS KPI,
   - son feedback’ler,
   - düşük skor queue,
   - insight tags,
   - kalite/knowledge adaylarına geçiş.
9. Commands:
   - `customer-care:feedback-digest --store=ID --dry-run`
   - `customer-care:feedback-export --store=ID --month=YYYY-MM`
10. Export:
    - UTF-8 BOM,
    - XML-safe,
    - PII masked,
    - raw müşteri iletişim bilgisi yok.
11. Tests:
    - Feature flag kapalı route 404.
    - Cross-store feedback görünmez.
    - Consent yoksa feedback request dış kanala gönderilmez.
    - Public review/comment kanallarında auto feedback default kapalı.
    - Feedback response PII masked saklanır.
    - Empty state sahte CSAT/NPS üretmez.
    - Düşük feedback quality candidate üretir ama auto publish etmez.
    - Export PII/secret içermez ve UTF-8/XML-safe olur.

## Kapsam Dışı

- Pazarlama kampanyası veya müşteri kazanım otomasyonu.
- Google/Meta public yorumlara otomatik feedback linki bırakmak.
- Sentiment için yeni LLM çağrı maliyet hattı açmak; gerekiyorsa mevcut AI ledger/gate kullanılmalı.

---

# Dalga AX — Developer Portal, Webhook Subscriptions ve Partner Enablement

## Amaç

Enterprise API ve Integration Hub kurulduktan sonra kurumsal müşterilerin ve entegrasyon partnerlerinin güvenli şekilde geliştirme yapabileceği bir developer portal zemini kurmak. Bu dalga public app store yayınlamaz; yalnız dokümante edilmiş, scope’lu, test edilebilir ve denetlenebilir partner enablement katmanı kurar.

## Kapsam

1. Feature flag:
   - `CUSTOMER_CARE_DEVELOPER_PORTAL_ENABLED=false`
   - Varsayılan kapalı.
2. Rota:
   - `/customer-care/developer`
   - route adı: `customer-care.developer`
3. Tablolar:
   - `support_developer_apps`
   - `support_webhook_subscriptions`
   - `support_webhook_delivery_logs`
   - `support_partner_certification_requests`
4. Developer app:
   - organization/store scoped.
   - app name, contact email, allowed scopes, allowed stores, status.
   - secret yalnız oluşturma anında gösterilir; DB’de hash/encrypted secret.
   - draft/review/approved/suspended lifecycle.
5. Webhook subscriptions:
   - event types allowlist:
     - `conversation.created`
     - `message.created`
     - `reply.sent`
     - `feedback.received`
     - `incident.created`
   - unknown event fail-closed.
   - HMAC-SHA256 signature zorunlu.
   - endpoint URL policy: localhost/private IP/invalid URL production’da engellenmeli.
   - delivery retry Integration Hub standardıyla uyumlu.
6. Partner certification:
   - Partner app canlıya alınmadan önce connector certification ve security audit referansı istenir.
   - Approved olmadan webhook delivery canlı dış endpoint’e gitmez; sandbox/dry-run olabilir.
7. UI:
   - ZOLM açık panel.
   - Developer apps listesi,
   - subscription listesi,
   - event type seçimi,
   - son delivery logları,
   - sandbox payload preview.
8. Docs:
   - `docs/customer-care/developer-portal-contract.md`
   - webhook signature,
   - event schemas,
   - retry semantics,
   - error codes,
   - sample payloads.
9. Commands:
   - `customer-care:webhook-subscription-audit --store=ID --dry-run`
   - `customer-care:webhook-delivery-retry --delivery=ID --dry-run`
10. Tests:
    - Feature flag kapalı route 404.
    - Cross-store developer app/subscription erişimi engellenir.
    - Secret plain DB’de saklanmaz.
    - Unknown event type fail-closed.
    - Private/internal URL production-like context’te engellenir.
    - Approved olmayan app canlı delivery yapmaz.
    - HMAC signature deterministic test edilir.
    - Delivery logs PII/secret içermez.
    - Retry dry-run default mutasyon yapmaz.

## Kapsam Dışı

- Public marketplace / app store yayını.
- OAuth authorization server implementasyonu.
- SDK npm/composer paketleri yayınlamak.
- Gerçek dış webhook endpointlerine testte HTTP çağrısı yapmak.

---

# Dalga AY — Incident Command Center, SLO/SLA Postmortem ve Continuous Improvement

## Amaç

Canlı sistemde kaçınılmaz olarak yaşanacak hataları, gecikmeleri ve kalite düşüşlerini yönetmek: incident kayıtları, SLO/SLA ihlalleri, postmortem, aksiyon takibi ve sürekli iyileştirme önerileri. Bu dalga otomatik aksiyon almaz; güvenli operasyon karar yüzeyi ve kanıt üretir.

## Kapsam

1. Feature flag:
   - `CUSTOMER_CARE_INCIDENT_CENTER_ENABLED=false`
   - Varsayılan kapalı.
2. Rota:
   - `/customer-care/incidents`
   - route adı: `customer-care.incidents`
3. Tablolar:
   - `support_incidents`
   - `support_incident_events`
   - `support_incident_actions`
   - `support_slo_snapshots`
   - Append-only event/action mantığı.
4. Incident source:
   - circuit breaker trip,
   - dead-letter spike,
   - provider health failure,
   - high policy block rate,
   - low CSAT spike,
   - stale golden eval,
   - connector certification fail,
   - manual operator report.
5. Severity:
   - `sev1`, `sev2`, `sev3`, `sev4`
   - severity change append-only event yazmalı.
6. SLO/SLA:
   - first response SLO,
   - resolution SLO,
   - dispatch success SLO,
   - AI draft quality SLO,
   - public review response SLO.
   - Veri yoksa unknown; sahte pass yok.
7. Postmortem:
   - Root cause,
   - impact summary,
   - timeline,
   - action items,
   - owner,
   - due date,
   - evidence links.
   - PII redaction zorunlu.
8. Continuous improvement:
   - Incident action item knowledge suggestion, macro audit veya connector certification task’ına bağlanabilir.
   - Hiçbir öneri otomatik uygulanmaz.
9. UI:
   - ZOLM açık panel.
   - Incident board,
   - SLO cards,
   - open action items,
   - postmortem editor,
   - timeline ledger.
10. Commands:
   - `customer-care:incident-scan --store=ID --dry-run`
   - `customer-care:slo-snapshot --store=ID --dry-run`
   - `customer-care:postmortem-export --incident=ID`
11. Export:
   - Markdown ve CSV opsiyonları,
   - PII masked,
   - UTF-8/XML-safe.
12. Tests:
   - Feature flag kapalı route 404.
   - Cross-store incident erişimi engellenir.
   - Incident scan dry-run mutasyon yapmaz.
   - Circuit breaker trip incident candidate üretir.
   - Veri yokken SLO fake pass üretmez; unknown döner.
   - Postmortem export PII/secret içermez.
   - Action item append-only event üretir.
   - Severity değişimi geçmişi silmez.
   - Continuous improvement önerisi auto-apply yapmaz.

## Kapsam Dışı

- PagerDuty/Slack/Teams canlı entegrasyonu.
- Otomatik production rollback.
- Gerçek müşteri veya temsilciye incident mesajı göndermek.

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

Dalga AW/AX/AY için ayrı kanıt paketleri oluştur:

- `docs/customer-care/dalga-aw-kanit-paketi.md`
- `docs/customer-care/dalga-ax-kanit-paketi.md`
- `docs/customer-care/dalga-ay-kanit-paketi.md`

`walkthrough.md` dosyasını da Dalga AW/AX/AY özetiyle güncelle.

İş bitince dur; kalite kapısı için Codex baş mühendis kontrolünü bekle.

---

## Antigravity’ye Gönderilecek Kısa Komut

```text
/Volumes/TWINMOS/zolm reposunda Dalga AW/AX/AY görevlerini uygula.

Önce şu dosyayı tamamen oku ve içindeki talimatları eksiksiz uygula:

/Volumes/TWINMOS/zolm/docs/customer-care/dalga-awaxay-antigravity-promptu.md

Yalnız Dalga AW/AX/AY kapsamını uygula.
Kapsam dışındaki mevcut kullanıcı/Codex değişikliklerine dokunma.
Commit, push veya branch değişikliği yapma.
Dalga AZ veya başka kapsama geçme.
Test, build, route, command, scheduler ve git kanıt paketini verdikten sonra dur.
```
