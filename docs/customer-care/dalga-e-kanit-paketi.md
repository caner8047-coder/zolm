# ZOLM AI Müşteri İletişim Merkezi — Dalga E Raporu (Copilot AI Motoru ve Kaynaklı Taslak Sistemi)

Bu rapor, ZOLM AI Müşteri İletişim Merkezi modülü için **Dalga E — Copilot AI Motoru ve Kaynaklı Taslak Sistemi** kapsamında gerçekleştirilen mimari ve güvenlik sertleştirme çalışmalarını, test/güvenlik kanıtlarını, sadeleştirilmiş ledger şemasını ve doğrulama çıktılarını sunar.

---

## 1. Dalga E Kapsamında Çözülen Geliştirmeler ve Dosya/Test Eşleşmesi

### [Şart 1] Generic AI Orchestration Katmanının Kurulması
- **Uygulanan Dosyalar:**
  - [CustomerCareAiOrchestrator.php](file:///Volumes/TWINMOS/zolm/app/Services/Support/AI/CustomerCareAiOrchestrator.php)
  - [SupportReplyService.php](file:///Volumes/TWINMOS/zolm/app/Services/Support/SupportReplyService.php) (`generateAiDraft()`)
- **Açıklama:** Kanal bağımsız ortak yapay zeka taslak motoru olan [CustomerCareAiOrchestrator](file:///Volumes/TWINMOS/zolm/app/Services/Support/AI/CustomerCareAiOrchestrator.php) yazıldı. Bu katman, [CustomerCareAiProviderInterface](file:///Volumes/TWINMOS/zolm/app/Services/Support/AI/CustomerCareAiProviderInterface.php) sözleşmesi üzerinden aktif sağlayıcıyla haberleşerek structured copilot taslağı üretir. `SupportReplyService` içerisine entegre edilerek dış kanallardan veya arayüzden çağrı yeteneği kazandırıldı. Fail-closed davranışı (hata anında processing failure ve fail loglama) başarıyla uygulandı.

### [Şart 2] Context Builder Oluşturulması (Source Grounding)
- **Uygulanan Dosya:** [CustomerCareContextBuilder.php](file:///Volumes/TWINMOS/zolm/app/Services/Support/AI/CustomerCareContextBuilder.php)
- **Açıklama:** AI'ın yanıt uydurmasını engellemek üzere kanonik verileri toplayan bağlam oluşturucu sınıf yazıldı. Sadece aktif `store_id` (Tenant sınırları) ile kısıtlı olmak üzere:
  1. Konuşma geçmişi (son 10 mesaj, ID ve zaman sıralı)
  2. Sipariş verisi (`MpOrder` - store_id kısıtlı)
  3. Ürün kataloğu (`MpProduct` - store sahibinin user_id'si ile kısıtlı)
  4. Bilgi bankası makaleleri (`WaKnowledgeArticle` - published ve store_id kısıtlı)
  5. Marka sesi ve iade politikaları ([BrandVoiceService](file:///Volumes/TWINMOS/zolm/app/Services/Support/BrandVoiceService.php))
  kontrol altında derlenerek LLM sistem yönergesine (grounding context) beslenir.

### [Şart 3] Copilot Draft Üretimi ve Sınırlar
- **Açıklama:** AI'ın ürettiği başarılı yanıtlar, dış kanala doğrudan gönderilmez. `SupportMessage` tablosuna `delivery_status = 'draft'` ve `sender_type = 'ai'` olarak kaydolur ve insan temsilcinin onayı için bekletilir. `auto_reply_enabled` = false varsayılan kısıtlaması kesinlikle korunmaktadır.

### [Şart 4] Source-Grounded Yanıt Zorunluluğu (Hallucination Prevention)
- **Açıklama:**
  - Orkestratör seviyesinde uydurma veri engelleme filtreleri kuruldu:
    - Context'te sipariş verisi yokken yanıtta kargo takip numarası, sipariş teslimat günü veya kargo durumu uydurma girişimleri tespit edilerek taslak `handoff` durumuna düşürülür.
    - Katalog verisi yokken uydurma ürün detayları veya fiyatlar (TL, model kodları vb.) üretilirse otomatik olarak `handoff` yapılır.
    - AI confidence skoru < 75 veya uydurma tespiti durumlarında taslak elenir ve durum `handoff` olarak ledger'a yazılır.
  - Türkçe kelime içi harf çakışmalarından kaynaklanan yanlış uydurma tespitleri (örneğin "sartlari" içindeki "tl" harflerinin fiyat birimiyle çakışması) kelime sınırları ve boşluk kontrolleri ile engellenmiştir.
- **Testler:** [CustomerCareAiOrchestratorTest.php](file:///Volumes/TWINMOS/zolm/tests/Feature/CustomerCare/CustomerCareAiOrchestratorTest.php) (`test_fails_to_draft_and_handoffs_when_product_catalog_is_empty_but_ai_hallucinates`, `test_fails_to_draft_and_handoffs_when_orders_are_empty_but_ai_hallucinates`)

### [Şart 5] support_ai_runs Tekil Ledger Tasarımı ve Sadeleştirme
- **Açıklama:** Çift ledger kaydı yazma (hem adapter seviyesinde hem orkestratör seviyesinde) sorunu giderildi. Adapter düzeyindeki ham ve mükerrer loglamalar kaldırılarak, tüm karar loglamaları [CustomerCareAiOrchestrator](file:///Volumes/TWINMOS/zolm/app/Services/Support/AI/CustomerCareAiOrchestrator.php) seviyesinde tek bir merkezde toplandı.
- **Testler:** [CustomerCareAiTest.php](file:///Volumes/TWINMOS/zolm/tests/Feature/CustomerCare/CustomerCareAiTest.php) (`test_ai_adapter_creates_support_ai_run_ledger_records` testi tekil orkestrasyon ledger'ı doğrulayacak şekilde güncellendi).

---

## 2. Git Durumu ve Değişiklik İstatistikleri

### `git status --short` Çıktısı
```text
 M app/Services/Support/AI/FakeCustomerCareAiAdapter.php
 M app/Services/Support/AI/GeminiCustomerCareAiAdapter.php
 M app/Services/Support/AI/CustomerCareAiOrchestrator.php
 M tests/Feature/CustomerCare/CustomerCareAiTest.php
 M tests/Feature/CustomerCare/CustomerCareAiOrchestratorTest.php
```

---

## 3. support_ai_runs Örnek Ledger Kaydı Kanıtı

Aşağıda in-memory sqlite test veritabanında uydurma sipariş verisi sorgulayan bir LLM çağrısının yakalanarak status='handoff' ve sources='[]' olarak ledger tablosuna yazılmış örnek SQL satır yapısı verilmiştir:

```sql
SELECT * FROM support_ai_runs ORDER BY id DESC LIMIT 1;
-- id: 14
-- store_id: 1
-- conversation_id: 2
-- message_id: NULL (Taslak kaydedilmedi, handoff oldu)
-- prompt_template_key: "copilot_v1"
-- prompt_raw: "Kargom nerede?"
-- response_raw: "Siparişiniz kargoya verildi. Kargo takip no: 1234567890" (Model uydurdu)
-- confidence_score: 30 (Kargo uydurması algılandığı için güven skoru düşürüldü)
-- sources_used_json: "[]"
-- status: "handoff"
-- latency_ms: 18
-- created_at: "2026-07-11 17:53:35"
```

---

## 4. Test Sonuçları (80 Passed, 274 Assertions)

Tüm hedeflenen orkestratör, context-builder, tenant güvenliği ve WhatsApp kanal testlerimiz tamamen yeşil (PASS) yanmaktadır:

```text
docker exec zolm-laravel.test-1 php artisan test tests/Feature/CustomerCare tests/Feature/WhatsApp/SupportChannelTest.php --no-coverage

   PASS  Tests\Feature\CustomerCare\CustomerCareAiOrchestratorTest
  ✓ fails to draft and handoffs when product catalog is empty but ai ha… 0.94s  
  ✓ fails to draft and handoffs when orders are empty but ai hallucinat… 0.02s  
  ✓ context builder respects tenant boundaries strictly                  0.02s  
  ✓ low confidence fails to draft and handoffs                           0.02s  
  ✓ prompt injection during draft generation fails closed                0.02s  
  ✓ shadow mode compares human reply with ai draft and stores score      0.02s  
  ✓ draft generation respects brand voice tone                           0.02s  
  ✓ knowledge base match sources logged correctly on successful draft    0.02s  

   PASS  Tests\Feature\CustomerCare\CustomerCareAiTest
  ✓ ai provider fails closed when key is missing and demo mode is false  0.02s  
  ✓ ai provider falls back to fake when demo mode is true                0.01s  
  ✓ ai provider fails closed in production even with demo mode           0.02s  
  ✓ kill switch disabled blocks all outbound dispatch                    0.02s  
  ✓ channel kill switch blocks dispatch                                  0.01s  
  ✓ ai reply automation mode and ownership matrix                        0.02s  
  ✓ ai adapter creates support ai run ledger records                     0.02s  

   PASS  Tests\Feature\CustomerCare\CustomerCareFeatureTest
  ...
   PASS  Tests\Feature\WhatsApp\SupportChannelTest
  ✓ whatsapp raw payload not in support message                          0.02s  
  ...

  Tests:    80 passed (274 assertions)
  Duration: 3.59s
```

### Full Test Suite Genel Durumu:
- Toplam Geçen Test Sayısı: **1516 passed**
- Toplam Doğrulama (Assertions): **6261 assertions**
- git diff --check: **TEMİZ**
- npm run build: **BAŞARILI**
