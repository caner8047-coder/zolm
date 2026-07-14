# ZOLM AI Müşteri İletişim Merkezi — Dalga V/W/X Antigravity Uygulama Promptu

**Tarih:** 2026-07-12  
**Hazırlayan:** Codex — Baş mühendis planı  
**Ön koşul:** Dalga S/T/U uygulaması tamamlandıktan sonra çalıştırılmalıdır.  
**Kapsam:** Dalga V, W, X uygulanacak; sonraki dalgalara geçilmeyecek.

---

## 0. Genel Talimatlar

/Volumes/TWINMOS/zolm reposunda çalış.

Önce şu dosyaları oku:

- `docs/customer-care/dalga-stu-antigravity-promptu.md`
- `docs/customer-care/dalga-pqr-antigravity-promptu.md`
- `docs/customer-care/dalga-mno-kabul-karari.md`
- `docs/customer-care/dalga-jkl-kabul-karari.md`
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
- Var olmayan API endpoint’i, token davranışı veya success cevabı uydurma.
- Unsupported/eksik credential durumunda fail-closed çalış.
- Müşteri kanalı public ise daha sıkı policy guard uygula.
- Yeni UI varsa ZOLM Kurumsal Açık Panel Sistemi ve mobil responsive kurallarına uy.
- Dalga Y/Z veya başka kapsama geçme.

---

# Dalga V — Meta Social Inbox Bridge: Instagram + Facebook

## Amaç

Instagram DM, Instagram yorumları ve Facebook sayfa mesaj/yorumlarını Müşteri İletişim Merkezi çekirdeğine güvenli biçimde bağlamak. Bu dalga sosyal medya kanalını “ayrı bir chat widget” olarak değil, support conversation/outbox/policy/AI/copywriting kurallarıyla aynı zemine alır.

## Kapsam

1. Yeni kanal anahtarları:
   - `instagram`
   - `facebook`
   - Gerekirse ortak adapter: `MetaSocialSupportChannelAdapter`
2. Feature flag:
   - `CUSTOMER_CARE_META_SOCIAL_ENABLED=false`
   - Config default kapalı.
3. Connection model:
   - Mevcut `IntegrationConnection` kullanılabiliyorsa kullan.
   - Token/secret alanları encrypted cast içinde kalmalı.
   - Token raw halde loglanmayacak.
4. Capabilities:
   - DM read/write
   - comment read/reply
   - attachment handling başlangıçta unavailable olabilir
   - capability durumları credential + channel enabled + provider support durumuna göre context-aware olacak.
5. Inbound projection:
   - Meta webhook payloadları doğrudan support message’a raw yazılmayacak.
   - DM ve comment ayrı `source_type` veya `thread_type` ile ayrılacak.
   - Duplicate webhook event idempotent işlenecek.
   - Store/channel scope dışında projection yapılmayacak.
6. Outbound reply:
   - DM reply ve comment reply ayrımı korunacak.
   - Eğer mevcut connector yoksa sahte success dönme; fail-closed.
   - Policy engine:
     - link/contact paylaşımı kanal politikasına göre engellensin.
     - public comment cevapları için daha kısa, PII içermeyen, genel cevap guard’ı uygulansın.
7. AI behavior:
   - Public comment cevapları default `copilot/manual`; automatic public comment kapalı olmalı.
   - DM tarafı normal automation gate’e bağlı kalabilir.
8. UI:
   - Inbox kanal badge’leri: Instagram DM, Instagram Yorum, Facebook Mesaj, Facebook Yorum.
   - Public comment ise “Herkese açık cevap” uyarısı göster.
9. Tests:
   - feature flag kapalıyken social route/handler fail-closed.
   - token/credential loglanmaz.
   - inbound DM projection idempotent.
   - inbound comment projection idempotent.
   - raw webhook payload support message’a sızmaz.
   - public comment auto reply default engelli.
   - malformed event/channel mismatch fail-closed.
   - unsupported connector fake success dönmez.

## Kapsam Dışı

- Meta canlı API endpointlerini repo içinde yoksa uydurma.
- Instagram/Facebook reklam mesajlarını yönetme.
- Social publishing/scheduler yazma.

---

# Dalga W — Google Business Profile Reviews / Reputation Inbox

## Amaç

Google Maps / Google Business Profile yorumlarını ZOLM müşteri iletişim paneline almak; yorum yönetimi, taslak cevap ve itibar operasyonu akışını kurmak. Bu dalga “müşteri sorusu” kadar “public review response” kalitesine odaklanır.

## Kapsam

1. Yeni kanal anahtarı:
   - `google_business`
   - UI adı: Google Maps / Google Business Profile
2. Feature flag:
   - `CUSTOMER_CARE_GOOGLE_REVIEWS_ENABLED=false`
3. Adapter:
   - `GoogleBusinessSupportChannelAdapter`
   - capabilities:
     - `read_reviews`
     - `reply_reviews`
     - `ai_suggestions`
4. Review projection:
   - Review → `support_conversations`
   - Review text → inbound `support_messages`
   - Rating, location id, review id gibi alanlar kaynak metadata içinde güvenli tutulacak.
   - Raw payload support message body’ye yazılmayacak.
5. Reply behavior:
   - Public review reply olduğu için policy engine daha sert çalışacak:
     - kişisel bilgi yok,
     - sipariş detayı yok,
     - “bize DM atın” gibi platform dışı yönlendirme kanal politikasına göre engellensin,
     - kesin vaat ve agresif savunma dili engellensin.
6. AI draft:
   - 1-2 yıldız yorumlar default automatic değil, copilot/manual.
   - 4-5 yıldız yorumlarda bile automatic ancak ayrı allowlist + eval + policy pass ile mümkün.
   - Yanıtlar marka sesiyle ama public review short tone kurallarıyla üretilecek.
7. Reputation analytics:
   - Basit KPI:
     - toplam yorum,
     - cevaplanmamış yorum,
     - ortalama rating,
     - negatif yorum sayısı,
     - ortalama cevap süresi.
   - Veri yoksa sahte metrik yok.
8. Tests:
   - review projection idempotent.
   - cross-store review idor engellenir.
   - public reply PII içerirse bloklanır.
   - low rating automatic default engelli.
   - rating KPI gerçek veriden hesaplanır, veri yoksa empty state.
   - unsupported connector fail-closed.

## Kapsam Dışı

- Google OAuth canlı akışı yazma.
- Google Business Profile API endpointleri yoksa uydurma.
- Review silme/şikayet etme akışı.

---

# Dalga X — Web/E-Ticaret Site Chat Bridge + Public Widget Contract

## Amaç

ZOLM kullanan firmanın kendi e-ticaret sitesinden gelen canlı destek / web chat mesajlarını customer care çekirdeğine almak. Bu dalga Shopify/Ikas/custom site entegrasyonlarına temel olacak güvenli public widget contract’ını kurar.

## Kapsam

1. Yeni kanal anahtarları:
   - `web_chat`
   - ileride `shopify`, `ikas` bu contract üstünden bağlanabilir.
2. Feature flag:
   - `CUSTOMER_CARE_WEB_CHAT_ENABLED=false`
3. Public inbound endpoint:
   - İmzasız açık endpoint olmayacak.
   - HMAC signature veya signed widget token zorunlu.
   - Rate limit uygulanacak.
   - CORS güvenli ve config kontrollü olacak.
4. Widget session model:
   - Guest session id hashed saklanacak.
   - IP/user-agent raw loglanmayacak veya redakte edilecek.
   - PII redaction pipeline inbound preview ve AI context için çalışacak.
5. Projection:
   - Web chat message → support conversation/message
   - Idempotency key zorunlu.
   - Duplicate message event tekrar kayıt oluşturmayacak.
6. Outbound:
   - Support outbox → web chat delivery table/queue.
   - Müşteri bağlantısı offline ise delivery status `queued`/`pending` gibi gerçekçi kalmalı; sahte sent yok.
7. AI behavior:
   - Web chat için copilot/automatic gate aynı merkezi gate’i kullanacak.
   - Ürün önerisi gerekiyorsa yalnız katalogda var olan ürünlerden öneri yapılacak.
   - Sepet/sipariş sorgusu için gerçek order context yoksa uydurma yapılmayacak.
8. Minimal widget contract doc:
   - `docs/customer-care/web-chat-widget-contract.md`
   - Request/response JSON örnekleri
   - Signature hesaplama
   - Rate limit ve idempotency açıklaması
9. Tests:
   - missing/invalid signature 403.
   - valid signed message projection.
   - duplicate idempotency key duplicate message üretmez.
   - cross-store widget token engellenir.
   - PII preview/context redacted.
   - offline delivery fake sent dönmez.
   - AI product/order context uydurmaz.

## Kapsam Dışı

- Gerçek JavaScript widget tasarımı/publish.
- Shopify/Ikas canlı API entegrasyonu.
- Ödeme/sepet değiştirme aksiyonu.

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

Dalga V/W/X için ayrı kanıt paketleri oluştur:

- `docs/customer-care/dalga-v-kanit-paketi.md`
- `docs/customer-care/dalga-w-kanit-paketi.md`
- `docs/customer-care/dalga-x-kanit-paketi.md`

`walkthrough.md` dosyasını güncelle.

İş bitince dur; kalite kapısı için Codex baş mühendis kontrolünü bekle.

---

## Antigravity’ye Gönderilecek Kısa Komut

```text
/Volumes/TWINMOS/zolm reposunda Dalga V/W/X görevlerini uygula.

Önce şu dosyayı tamamen oku ve içindeki talimatları eksiksiz uygula:

/Volumes/TWINMOS/zolm/docs/customer-care/dalga-vwx-antigravity-promptu.md

Yalnız Dalga V/W/X kapsamını uygula.
Kapsam dışındaki mevcut kullanıcı değişikliklerine dokunma.
Commit, push veya branch değişikliği yapma.
Dalga Y/Z veya başka kapsama geçme.
Test, build, route, command, scheduler ve git kanıt paketini verdikten sonra dur.
```
