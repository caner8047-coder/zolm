# ZOLM AI Müşteri İletişim Merkezi — Dalga AE/AF/AG Antigravity Uygulama Promptu

**Tarih:** 2026-07-13  
**Hazırlayan:** Codex — Baş mühendis planı  
**Ön koşul:** Dalga AB/AC/AD kalite kapısı kabul edildikten sonra çalıştırılmalıdır. Dalga S/T/U + V/W/X Kalite Kapısı 02 kapanmadan bu dalga uygulanmamalıdır.  
**Kapsam:** Dalga AE, AF, AG uygulanacak; sonraki dalgalara geçilmeyecek.

---

## 0. Genel Talimatlar

/Volumes/TWINMOS/zolm reposunda çalış.

Önce şu dosyaları oku:

- `docs/customer-care/dalga-abacad-antigravity-promptu.md`
- `docs/customer-care/dalga-yzaa-kabul-karari.md`
- `docs/customer-care/dalga-stu-vwx-kalite-kapisi-02.md`
- `docs/customer-care/kvkk-retention-policy.md` varsa oku
- `docs/customer-care/adr/003-generic-outbound-dispatch.md`
- `docs/customer-care/adr/007-ai-shadow-golden-eval-ledger.md`
- `AGENTS.md`

Kurallar:

- Commit, push veya branch değişikliği yapma.
- Kapsam dışındaki kullanıcı değişikliklerine dokunma.
- Feature flag varsayılanlarını kapalı tut.
- Otomatik yanıtı global olarak açma.
- Testlerde canlı dış API çağrısı yapma.
- Var olmayan CRM/ERP/helpdesk/webhook provider başarısı uydurma.
- Harici sistem entegrasyonlarında HMAC, idempotency, retry ve fail-closed davranış zorunludur.
- Yeni UI varsa ZOLM Kurumsal Açık Panel Sistemi ve mobil responsive kurallarına uy.
- Excel/CSV export varsa ZOLM export kurallarını uygula.
- Dalga AH/AI/AJ veya başka kapsama geçme.

---

# Dalga AE — Kalite Denetim Merkezi, Skor Kartları ve Agent Coaching

## Amaç

ZOLM’ü yalnız cevap üreten sistem olmaktan çıkarıp, müşteri hizmetleri kalitesini ölçen ve iyileştiren yönetim katmanına taşımak. Bu dalga; AI cevapları, temsilci cevapları ve handoff kararları için denetlenebilir kalite puanı, geri bildirim ve eğitim döngüsü kurar.

## Kapsam

1. Kalite inceleme modeli:
   - Var olan `support_ai_runs`, `support_agent_actions`, `support_messages` yapıları incelenecek.
   - Yeni tablo gerekiyorsa minimal ve store scoped:
     - `support_quality_reviews`
     - `support_quality_review_items`
   - Append-only audit yaklaşımı korunacak.
2. Scorecard başlıkları:
   - doğruluk / kaynak uyumu,
   - marka sesi,
   - kanal politikası uyumu,
   - PII / KVKK güvenliği,
   - çözüm netliği,
   - satış fırsatı uygunluğu,
   - gereksiz vaat / uydurma riski.
3. Review workflow:
   - Admin veya yetkili kişi konuşmayı kalite incelemesine alabilir.
   - AI cevabı, temsilci cevabı veya taslak ayrı ayrı puanlanabilir.
   - İnceleme sonucu:
     - kabul,
     - düzeltme gerekli,
     - golden dataset’e aday,
     - bilgi merkezi önerisine aday.
4. AI feedback loop:
   - İnceleme sonucundan otomatik fine-tuning yapılmayacak.
   - Güvenli çıktı:
     - golden eval candidate,
     - knowledge suggestion candidate,
     - policy rule suggestion.
   - İnsan onayı olmadan yeni kural/knowledge canlıya alınmayacak.
5. Agent coaching:
   - Temsilci bazında kişisel performans metriği uydurulmayacak.
   - Yalnız gerçek review item’larından:
     - güçlü yön,
     - dikkat edilmesi gereken konu,
     - örnek iyi cevap.
   - PII maskeli olacak.
6. UI:
   - `/customer-care/quality`
   - İnceleme kuyruğu, skor kartı, filtreler:
     - AI auto reply,
     - copilot draft,
     - agent reply,
     - policy block,
     - handoff.
   - Mobilde kart görünümü; desktop’ta tablo + sağ review paneli.
7. Artisan command:
   - `customer-care:sample-quality-reviews --store=ID --limit=50 --dry-run`
   - Rastgele/sistematik örnekleme yapar; dry-run default.
8. Tests:
   - quality review store scoped.
   - PII export/review text redacted.
   - non-admin review route erişemez.
   - scorecard item audit trail korunur.
   - review sonucu golden candidate üretir ama live eval dataset’i otomatik değiştirmez.
   - no fake coaching metrics when no reviews.
   - sample command dry-run veri değiştirmez.

## Kapsam Dışı

- Otomatik temsilci cezalandırma/puanlama sistemi.
- Fine-tuning pipeline.
- Performans primi veya HR modülü.

---

# Dalga AF — Enterprise Integration Hub: Webhook, CRM/ERP ve Dış Sistem Köprüsü

## Amaç

ZOLM kullanan firmanın kendi CRM, ERP, kargo, helpdesk veya BI sistemleriyle güvenli ve denetlenebilir entegrasyon kurabilmesi. Bu dalga canlı provider uydurmaz; signed webhook ve generic integration contract zemini kurar.

## Kapsam

1. Integration hub config:
   - Feature flag:
     - `CUSTOMER_CARE_INTEGRATION_HUB_ENABLED=false`
   - Store scoped entegrasyon ayarları.
   - Secret/token encrypted olmalı; raw loglanmamalı.
2. Outbound webhook event contract:
   - Olaylar:
     - `conversation.created`
     - `message.received`
     - `reply.sent`
     - `handoff.created`
     - `sla.escalated`
     - `policy.blocked`
     - `quality.reviewed`
   - Her event:
     - event_id,
     - store_id,
     - occurred_at,
     - schema_version,
     - redacted payload,
     - idempotency key.
3. Delivery table:
   - Yeni tablo gerekiyorsa:
     - `support_integration_events`
     - `support_integration_deliveries`
   - Retry/backoff ve dead-letter statüleri.
   - PII yok veya redacted.
4. HMAC signing:
   - Outbound webhook body HMAC-SHA256 ile imzalanmalı.
   - Timestamp + replay window kontrolü dokümante edilmeli.
   - Testte gerçek HTTP çağrısı yerine fake HTTP client kullanılmalı.
5. Inbound integration command/API:
   - Canlı public API yazılacaksa auth/HMAC zorunlu.
   - Eğer route kapsam dışıysa sadece service contract ve command.
   - Inbound veri store scope ve idempotency ile fail-closed çalışmalı.
6. CRM/ERP adapter skeleton:
   - `GenericCrmConnectorInterface` veya benzeri contract.
   - Gerçek provider yoksa fake success yok.
   - Capabilities unavailable kalmalı.
7. UI:
   - `/customer-care/integrations`
   - Entegrasyon listesi, webhook endpoint config, son delivery durumu, dead-letter listesi.
   - Secret gösterme yok; sadece “tanımlı/tanımlı değil” badge.
8. Dokümantasyon:
   - `docs/customer-care/integration-hub-contract.md`
   - Event JSON örnekleri.
   - HMAC hesaplama.
   - Retry ve idempotency.
9. Tests:
   - outbound payload PII redacted.
   - webhook signature doğru üretilir.
   - retry/backoff duplicate delivery üretmez.
   - dead-letter state terminal ve auditlenebilir.
   - connector yoksa CRM sync fake success dönmez.
   - cross-store integration event sızmaz.
   - secret raw log/export edilmez.

## Kapsam Dışı

- Salesforce, HubSpot, Logo, Mikro, Netsis gibi canlı provider entegrasyonu.
- OAuth ekranı.
- Gerçek harici HTTP başarılarını canlı API ile test etme.

---

# Dalga AG — Production Observability, Maliyet Kontrolü ve Güvenli Model Operasyonları

## Amaç

AI Müşteri İletişim Merkezi’nin canlı operasyonunu yönetilebilir kılmak: maliyet, latency, hata oranı, provider sağlığı, model seçimi, güvenlik ve incident görünürlüğü. Bu dalga “çalışıyor”dan “canlıda izlenebilir ve frenlenebilir” seviyesine çıkarır.

## Kapsam

1. Observability event standardı:
   - AI run, dispatch, policy block, integration delivery, webhook projection, circuit breaker eventleri ortak metrik formatına bağlanır.
   - PII ve raw prompt loglanmaz.
2. Cost ledger:
   - Yeni tablo gerekiyorsa:
     - `support_ai_cost_events`
   - Model adı, provider, input/output token tahmini, cost estimate, store_id.
   - Maliyet tahmini yoksa sıfır uydurma yapılmaz; `unknown`/null state kullanılır.
3. Provider health:
   - `CustomerCareAiProviderHealthService`
   - Gemini/Groq/fake provider ayrımı mevcut yapıya göre incelenir.
   - API key yoksa fail-closed.
   - Provider down ise automatic answer durur; copilot açık kalabilir ama açık uyarı gösterir.
4. Model routing policy:
   - Config default güvenli.
   - High-risk/public channel cevaplarında daha sıkı model/policy/eval gate.
   - Low-risk draft senaryolarında düşük maliyetli provider kullanılabilir; ama gerçek provider yoksa fake success yok.
5. Budget guard:
   - Store bazlı günlük/aylık AI maliyet limiti.
   - Limit aşılırsa:
     - auto reply durur,
     - draft üretimi açık uyarıyla engellenebilir,
     - manual reply etkilenmez.
6. Incident dashboard:
   - `/customer-care/ops`
   - Kartlar:
     - AI provider health,
     - dispatch failure rate,
     - policy block trend,
     - circuit breaker status,
     - cost estimate,
     - latency p50/p95,
     - dead-letter count.
   - Veri yoksa sahte metrik yok.
7. Artisan commands:
   - `customer-care:ops-health --store=ID`
   - `customer-care:recompute-ai-costs --store=ID --dry-run`
8. Tests:
   - raw prompt/body PII observability event’e sızmaz.
   - provider API key yoksa health fail-closed.
   - budget exceeded auto reply blocks, manual reply continues.
   - no cost data varsa fake zero/ROI gösterilmez.
   - ops dashboard cross-store data sızdırmaz.
   - p95 latency gerçek eventlerden hesaplanır.
   - recompute dry-run veri değiştirmez.

## Kapsam Dışı

- Prometheus/Grafana canlı kurulum.
- Ücretli APM provider entegrasyonu.
- Gerçek provider failover otomatik trafik dağıtımı.
- Finansal fatura/chargeback sistemi.

---

## Kabul Kanıtları

Uygulama bittikten sonra şu komutlar çalıştırılacak ve sonuçlar rapora yazılacak:

```bash
git diff --check
npm run build
./vendor/bin/sail artisan route:list --name=customer-care
./vendor/bin/sail artisan list customer-care --raw
./vendor/bin/sail artisan schedule:list
./vendor/bin/sail artisan test tests/Feature/CustomerCare tests/Feature/WhatsApp/SupportChannelTest.php tests/Feature/MarketplaceQuestionsTest.php --no-coverage --compact
./vendor/bin/sail artisan test --no-coverage --compact
```

Dalga AE/AF/AG için ayrı kanıt paketleri oluştur:

- `docs/customer-care/dalga-ae-kanit-paketi.md`
- `docs/customer-care/dalga-af-kanit-paketi.md`
- `docs/customer-care/dalga-ag-kanit-paketi.md`

`walkthrough.md` dosyasını güncelle.

İş bitince dur; kalite kapısı için Codex baş mühendis kontrolünü bekle.

---

## Antigravity’ye Gönderilecek Kısa Komut

```text
/Volumes/TWINMOS/zolm reposunda Dalga AE/AF/AG görevlerini uygula.

Önce şu dosyayı tamamen oku ve içindeki talimatları eksiksiz uygula:

/Volumes/TWINMOS/zolm/docs/customer-care/dalga-aeafag-antigravity-promptu.md

Yalnız Dalga AE/AF/AG kapsamını uygula.
Kapsam dışındaki mevcut kullanıcı değişikliklerine dokunma.
Commit, push veya branch değişikliği yapma.
Dalga AH/AI/AJ veya başka kapsama geçme.
Test, build, route, command, scheduler ve git kanıt paketini verdikten sonra dur.
```
