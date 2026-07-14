# ZOLM AI Müşteri İletişim Merkezi — Dalga AB/AC/AD Antigravity Uygulama Promptu

**Tarih:** 2026-07-13  
**Hazırlayan:** Codex — Baş mühendis planı  
**Ön koşul:** Dalga Y/Z/AA kabul edildi. Dalga S/T/U + V/W/X Kalite Kapısı 02 kapanmadan bu dalga uygulanmamalıdır.  
**Kapsam:** Dalga AB, AC, AD uygulanacak; sonraki dalgalara geçilmeyecek.

---

## 0. Genel Talimatlar

/Volumes/TWINMOS/zolm reposunda çalış.

Önce şu dosyaları oku:

- `docs/customer-care/dalga-yzaa-antigravity-promptu.md`
- `docs/customer-care/dalga-yzaa-kabul-karari.md`
- `docs/customer-care/dalga-stu-vwx-kalite-kapisi-02.md`
- `docs/customer-care/web-chat-widget-contract.md` varsa oku
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
- Var olmayan ürün, stok, fiyat, kampanya, sipariş, kargo veya müşteri verisi uydurma.
- Katalog/sipariş/politika kaynağı yoksa kesin yanıt üretme; copilot/handoff davran.
- Yeni UI varsa ZOLM Kurumsal Açık Panel Sistemi ve mobil responsive kurallarına uy.
- Excel/CSV export varsa ZOLM export kurallarını uygula.
- Dalga AE/AF/AG veya başka kapsama geçme.

---

# Dalga AB — Kaynaklı Bilgi Merkezi v2: Katalog, Politika ve Kampanya Grounding

## Amaç

AI cevaplarının “genel chatbot” gibi değil, firmanın gerçek katalog, stok, fiyat, kampanya, iade, kargo ve bakım talimatı kaynaklarına dayanan bir müşteri temsilcisi gibi çalışmasını sağlamak. Bu dalga, mevcut bilgi öneri döngüsünü üretim kalitesinde source-of-truth katmanına dönüştürür.

## Kapsam

1. Kaynak tipleri:
   - `product_catalog`
   - `product_variant`
   - `stock_price`
   - `campaign`
   - `return_policy`
   - `shipping_policy`
   - `size_chart`
   - `care_instruction`
   - `ingredient_allergen`
   - `faq`
2. Mevcut bilgi tablolarını incele:
   - Var olan `support_knowledge_*` yapıları uygunsa genişlet.
   - Yeni migration gerekiyorsa backward compatible ve store scoped olmalı.
   - Kaynak kayıtlarında PII tutulmayacak.
3. Yeni servis önerisi:
   - `CustomerCareKnowledgeGroundingService`
   - Görevi: conversation + mesaj + kanal bağlamına göre güvenli kaynakları bulmak, citation listesi üretmek, stale/expired kaynakları elemek.
4. Source freshness:
   - Fiyat/stok/kampanya kaynaklarında `synced_at` veya eşdeğer freshness kontrolü olmalı.
   - Stale kaynak kesin fiyat/stok/kampanya cevabı üretmek için kullanılmamalı.
   - Stale ise cevap `copilot` veya handoff’a düşmeli.
5. AI context entegrasyonu:
   - `CustomerCareAiOrchestrator` / context builder içinde kaynaklı bilgi blokları net ayrılmalı.
   - Her AI draft/answer ledger kaydında kullanılan kaynaklar `sources_used_json` veya mevcut kanonik alan üzerinden tutulmalı.
   - Cevapta kaynak/citation izlenebilir olmalı; kullanıcıya ham iç sistem path’i gösterilmemeli.
6. Ürün/katalog guard:
   - Katalogda olmayan ürün önerilmez.
   - Stokta olmayan ürün “var” denmez; alternatif önerilecekse gerçek alternatif katalogdan gelir.
   - Fiyat ve kampanya yalnız güncel kaynak varsa söylenir.
   - Beden/ölçü/uyumluluk cevapları yalnız ürün varyantı/size chart kaynağı varsa net söylenir; yoksa ölçü isteme veya ekibe devretme.
7. Prompt injection guard:
   - Bilgi kaynağı içinde “önceki talimatları unut”, “sen artık” benzeri injection ifadeleri varsa kaynak güvenli şekilde reddedilmeli veya sanitize edilmeli.
8. Artisan komutu:
   - `customer-care:sync-knowledge --store=ID --source=SOURCE --dry-run`
   - Dry-run default olabilir.
   - Dış API uydurma yok; mevcut repo kaynaklarından veya fixture/mock test verisinden çalış.
9. UI:
   - `/customer-care/knowledge` varsa genişlet; yoksa minimal ZOLM açık panel sayfası ekle.
   - Kaynak listesi, durum badge’i, freshness, son senkron, kullanılabilirlik.
   - Veri yoksa sahte bilgi kartı yok; açık empty state.
10. Tests:
   - cross-store source leakage engellenir.
   - stale price/stock/campaign source kesin cevapta kullanılmaz.
   - in-stock product source ile citation ledger’a yazılır.
   - out-of-stock üründe “stokta var” denmez.
   - prompt injection içeren source context’e girmez.
   - PII içeren source kaydı redacted/sanitized olur.
   - no-source durumda AI cevap uydurmaz, copilot/handoff davranır.
   - sync command dry-run veri değiştirmez.

## Kapsam Dışı

- Yeni pazaryeri canlı ürün API entegrasyonu yazma.
- Vector database zorunlu kılma.
- Fine-tuning veya kendi LLM eğitimi.
- Tıbbi/hukuki/finansal kesin uzman tavsiyesi üretme.

---

# Dalga AC — Ekip Yönlendirme, Kuyruklar, Mesai ve SLA Escalation

## Amaç

Müşteri İletişim Merkezi’ni tek kişinin baktığı inbox olmaktan çıkarıp ekip operasyonuna hazır hale getirmek. Bu dalga, “hangi konuşma kime düşecek, ne zaman eskale olacak, AI ne zaman geri çekilecek?” sorularını deterministik ve denetlenebilir şekilde çözer.

## Kapsam

1. Team/queue modeli:
   - Var olan kullanıcı/rol/store yapısını incele.
   - Yeni tablo gerekiyorsa minimal ve backward compatible:
     - `support_teams`
     - `support_team_members`
     - `support_routing_rules`
   - Store scoped olmalı.
2. Routing rule engine:
   - Yeni servis önerisi:
     - `CustomerCareRoutingService`
   - Rule örnekleri:
     - kanal bazlı yönlendirme,
     - negatif Google yorumları uzman ekibe,
     - iade/iptal konuları operasyon ekibine,
     - yüksek sepet/son sipariş sinyali varsa satış ekibine,
     - mesai dışı default copilot/manual.
3. Ownership ve concurrency:
   - Mevcut ownership state machine bozulmayacak.
   - Aynı konuşmayı iki temsilci aynı anda claim edememeli.
   - Human lock varken AI automatic cevap üretmemeli.
4. Mesai saatleri:
   - Store bazlı business hours config.
   - Mesai dışı otomatik cevap default kapalı veya ayrı allowlist’e bağlı.
   - Mesai dışı “ekibimiz dönecek” gibi güvenli taslak copilot olarak önerilebilir.
5. SLA escalation:
   - İlk yanıt ve çözüm SLA’ları mevcut analitikle uyumlu olmalı.
   - Yeni command:
     - `customer-care:run-sla-escalations --store=ID`
   - Escalation audit log:
     - `support_agent_actions.action = sla_escalated` veya mevcut pattern.
   - Gerçek dış bildirim provider yoksa Slack/email gönderimi uydurma; sadece internal event/audit yaz.
6. Inbox UI:
   - Queue filter:
     - Benim üzerimde,
     - Atanmamış,
     - SLA riski,
     - Eskale,
     - Kanal/öncelik.
   - Team/assignee badge’leri.
   - Mobilde filtreler kompakt command bar içinde.
7. Tests:
   - routing cross-store sızmaz.
   - rule match doğru team/queue atar.
   - iki temsilci aynı conversation’ı eşzamanlı claim edemez.
   - human ownership AI automatic’i engeller.
   - business hours dışında automatic default engelli.
   - SLA command overdue conversation için escalation audit yazar.
   - agent/manual reply routing quota/circuit limitlerinden gereksiz etkilenmez.

## Kapsam Dışı

- Slack/Teams/email gerçek entegrasyonu.
- Bordro/shift planlama ürünü.
- Canlı bildirim push sistemi.

---

# Dalga AD — Satış Copilot’u, Ürün Öneri ve Sepet Kurtarma Güvenli Çekirdeği

## Amaç

ZOLM’ün müşteri temsilcisi hissini satış değerine bağlamak: ürün karşılaştırma, beden/uyumluluk, hediye önerisi, alternatif ürün ve sepet kurtarma taslakları. Bu dalga satış yapar gibi davranır ama uydurmadan, baskıcı olmadan ve kanal kurallarını ihlal etmeden çalışır.

## Kapsam

1. Yeni servis önerisi:
   - `CustomerCareSalesAssistService`
   - Sadece gerçek katalog, stok, fiyat, kampanya ve policy kaynaklarıyla çalışır.
2. Senaryolar:
   - ürün karşılaştırma,
   - beden/ölçü önerisi,
   - hediye önerisi,
   - out-of-stock ürün alternatifi,
   - discontinued ürün alternatifi,
   - bakım/uyumluluk sorusu sonrası uygun ürün önerisi,
   - web chat sepet terk sinyali varsa güvenli copilot taslağı.
3. Feature flags:
   - `CUSTOMER_CARE_SALES_COPILOT_ENABLED=false`
   - `CUSTOMER_CARE_CART_RECOVERY_ENABLED=false`
   - `CUSTOMER_CARE_PROACTIVE_SALES_AUTO_ENABLED=false`
4. Guard rails:
   - Public comment/review tarafında proaktif satış default kapalı.
   - Pazaryeri kanalında dış link, telefon, IBAN, platform dışı kampanya yasakları korunur.
   - “Kesin sana olur”, “garanti çözüm”, “yarın teslim” gibi kesin vaatler engellenir.
   - Stok/fiyat/kampanya güncel değilse net satış vaadi yok.
   - Ürün önerisi sayısı sınırlı ve açıklamalı olmalı; spam gibi çok ürün basılmamalı.
5. Cart/session signal:
   - Web chat signed payload içinde cart/session sinyali varsa kullanılabilir.
   - İmzasız veya stale cart sinyali kullanılmaz.
   - Sepet içeriği PII içermeyecek şekilde context’e alınır.
6. Inbox UI:
   - Sağ panelde “Satış Copilot” kartı:
     - önerilen ürünler,
     - öneri nedeni,
     - kaynak/citation,
     - “Taslağa ekle” aksiyonu.
   - Veri yoksa empty state; sahte öneri yok.
7. Metrics:
   - suggestion generated,
   - suggestion accepted,
   - suggestion sent,
   - agent edited before send.
   - ROI/satış geliri uydurulmaz; sadece eldeki event’ler raporlanır.
8. Tests:
   - katalogda olmayan ürün önerilmez.
   - stokta olmayan ürün “stokta” denmez; varsa alternatif gösterilir.
   - fiyat/kampanya stale ise net fiyat/kampanya cevabı yok.
   - marketplace policy satış taslağındaki dış link/telefonu bloklar.
   - public comment proactive sales automatic default kapalı.
   - signed web chat cart signal kullanılır; invalid signature kullanılmaz.
   - cross-store ürün/kampanya sızmaz.
   - suggestion ledger/event PII içermez.

## Kapsam Dışı

- Sepet değiştirme, ödeme alma, kupon üretme.
- Gerçek reklam/remarketing kampanyası.
- Tam ürün arama motoru veya vector recommender.
- Auto-purchase veya otomatik indirim verme.

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

Dalga AB/AC/AD için ayrı kanıt paketleri oluştur:

- `docs/customer-care/dalga-ab-kanit-paketi.md`
- `docs/customer-care/dalga-ac-kanit-paketi.md`
- `docs/customer-care/dalga-ad-kanit-paketi.md`

`walkthrough.md` dosyasını güncelle.

İş bitince dur; kalite kapısı için Codex baş mühendis kontrolünü bekle.

---

## Antigravity’ye Gönderilecek Kısa Komut

```text
/Volumes/TWINMOS/zolm reposunda Dalga AB/AC/AD görevlerini uygula.

Önce şu dosyayı tamamen oku ve içindeki talimatları eksiksiz uygula:

/Volumes/TWINMOS/zolm/docs/customer-care/dalga-abacad-antigravity-promptu.md

Yalnız Dalga AB/AC/AD kapsamını uygula.
Kapsam dışındaki mevcut kullanıcı değişikliklerine dokunma.
Commit, push veya branch değişikliği yapma.
Dalga AE/AF/AG veya başka kapsama geçme.
Test, build, route, command, scheduler ve git kanıt paketini verdikten sonra dur.
```
