# ZOLM AI Müşteri İletişim Merkezi — Dalga E Kabul Kararı

Tarih: 2026-07-11  
Karar sahibi: Codex baş mühendis kontrolü  
Kapsam: Dalga E — Copilot AI Motoru ve Kaynaklı Taslak Sistemi

## Karar

**Dalga E copilot taslak motoru kalite kapısı kabul edildi.**

Bu kabul; AI'ın müşteriyle doğrudan konuşması için değil, yalnızca temsilci onayına düşen kaynaklı taslak üretimi için verilmiştir.

Bu karar **automatic reply production açılışı**, **genel pilot açılışı** veya **Dalga F kabulü** anlamına gelmez.

## Bağımsız doğrulama kanıtları

- `git diff --check`: temiz.
- `npm run build`: başarılı.
- Customer Care migration'ları `Ran` durumunda.
- Route listesinde:
  - `customer-care`
  - `customer-care/pilot`
- Hedef test paketi:
  - `tests/Feature/CustomerCare`
  - `tests/Feature/WhatsApp/SupportChannelTest.php`
  - `tests/Feature/MarketplaceQuestionsTest.php`
- Hedef test sonucu: **80 passed, 274 assertions**.
- Full test suite sonucu: **1516 passed, 6260 assertions**.

Not: Antigravity raporunda full suite assertion sayısı 6261 olarak yazılmış; canlı doğrulamada **6260 assertions** görüldü. Test geçişi temizdir.

## Kabul edilen ana maddeler

1. `GeminiCustomerCareAiAdapter` ve `FakeCustomerCareAiAdapter` içindeki mükerrer `SupportAiRun::create()` çağrıları kaldırıldı.
2. AI run ledger yazımı `CustomerCareAiOrchestrator` seviyesinde tek merkezde toplandı.
3. Başarılı copilot yanıtları dış kanala gönderilmiyor; `SupportMessage` içine `delivery_status = draft` olarak kaydediliyor.
4. Katalog verisi yokken ürün/fiyat/stok uydurma denemeleri `handoff` durumuna düşüyor.
5. Sipariş verisi yokken kargo/takip no/sipariş durumu uydurma denemeleri `handoff` durumuna düşüyor.
6. Düşük confidence durumunda draft oluşturulmadan `handoff` davranışı uygulanıyor.
7. Prompt injection durumunda fail-closed akışı ve `support_ai_runs.status = failed` davranışı testleniyor.
8. Marka sesi (`brand_voice`) sistem yönergesine giriyor ve testle doğrulanıyor.
9. Knowledge base eşleşmesi olduğunda `sources_used_json` içine `Knowledge Base` yazıldığı testleniyor.
10. WhatsApp SupportChannel ve MarketplaceQuestions regresyonları temiz geçiyor.

## Mimari gözlem

Dalga E, “AI güzel cevap verdi mi?” seviyesinde değil, “AI kaynaksız veya tenant dışı bilgi uyduramadı mı?” seviyesinde başarılıdır. Bu doğru kalite çıtasıdır.

Orkestratör artık karar defterini tek noktadan yazdığı için ileride shadow/eval/pilot istatistiklerini toplamak daha kolay olacaktır.

## Dalga F öncesi taşınacak notlar

### 1. Ürün kataloğu hâlâ user-level havuzdan geliyor

`MpProduct` modeli mevcut projede `store_id` taşımıyor; bu nedenle `CustomerCareContextBuilder` ürünleri `MpProduct::where('user_id', $store->user_id)` ile sınırlıyor.

Bu mevcut veri modeliyle tenant açısından kabul edilebilir; ancak mağaza/listing bazlı fiyat, stok, kampanya ve kanal görünürlüğü için Dalga F veya pilot öncesinde `ChannelListing` / pazaryeri listing projection kullanımı netleşmelidir.

### 2. Provider confidence hâlâ basit

Gemini adapter başarılı yanıtta sabit `85` confidence döndürüyor. Dalga E için guard ve copilot taslak seviyesinde kabul edilebilir; fakat automatic reply kapısı öncesinde dinamik confidence, structured output ve safety/grounding kontrolü gerekir.

### 3. Empty query/skipped ledger politikası netleşmeli

Müşteri mesajı bulunmayan konuşmada orkestratör `skipped` döndürüyor; bu akışta ledger yazımı yapılmıyor. Bu bir AI denemesi sayılmayabilir; fakat operasyonel izlenebilirlik istenirse Dalga F'te `skipped` ledger kaydı standardize edilmelidir.

### 4. PII redaksiyonu Dalga F kapısında tekrar ele alınmalı

`prompt_raw` ve `response_raw` pilot dashboard/ledger için faydalı; fakat geniş kullanıcı erişimi veya production izleme ekranı öncesinde PII maskeleme/redaksiyon politikası gereklidir.

## Sonuç

Dalga E kalite kapısı **geçti**.

Sıradaki doğru adım: **Dalga F — Kontrollü Pilot, Shadow Mode ve Otomasyon Kapısı** kalite incelemesi ve sertleştirmesi.

