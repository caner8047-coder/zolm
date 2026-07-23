# Faz 0 — Güncel Repo Doğrulaması ve Boşluk Analizi

**Oluşturulma:** 2026-07-11  
**Revizyon:** 2026-07-23 (Laravel 13 yükseltmesi sonrası)
**Branch:** `codex/recovery-20260706-172630`  
**Son commit:** `497b531 feat(accounting): add pilot feedback backlog workflow`  
**Remote:** `https://github.com/caner8047-coder/zolm.git`  
**Kirli çalışma ağacı:** `?? docs/customer-care/` (yalnız bu dizin untracked)

---

## 1. İncelenen Commit, Branch ve Kirli Çalışma Ağacı

```
git branch --show-current  → codex/recovery-20260706-172630
git status --short          → ?? docs/customer-care/
git log -1 --oneline        → 497b531 feat(accounting): add pilot feedback backlog workflow
```

Kirli ağaçtaki `docs/customer-care/` dizini (bu faz promptları dahil) mevcut kullanıcı değişikliğidir; hiçbir dosyasına dokunulmadı.

---

## 2. Gerçek Teknoloji ve Boyut Envanteri

| Alan | Değer |
|---|---|
| Framework | Laravel 13 (`^13.0`) + Livewire 4 (`^4.0`) |
| PHP versiyonu | `^8.3` |
| DB | MySQL 8 (Docker Sail: `zolm-mysql-1`, port 3306) |
| Queue driver | `database` (env: `QUEUE_CONNECTION=database`) |
| Excel | PhpSpreadsheet `^5.4` |
| PDF | `barryvdh/laravel-dompdf ^3.0`, `smalot/pdfparser` |
| JS | npm/vite (Tailwind CDN, Alpine.js) |
| Toplam `app/` PHP dosyası | 717 |
| `app/Models/` | 243 |
| `app/Livewire/` | 100 |
| `app/Services/` | 273 |
| `database/migrations/` | 220 |
| Test dosyası | 182 |

> **Not:** Kaynak doğrulama için `composer.lock` ve çalışan Sail ortamı esas alınır. Güncel sürümler Laravel 13.21.1 ve Livewire 4.3.3'tür.

---

## 3. Mevcut Uçtan Uca Trendyol Soru-Cevap Akışı

### 3.1 Soru Çekme

- **Connector:** `TrendyolConnector implements PullsCustomerQuestions, AnswersCustomerQuestions`
  → `app/Services/Marketplace/Connectors/TrendyolConnector.php`
- **Sync servisi:** `MarketplaceQuestionSyncService::sync()` → `app/Services/Marketplace/MarketplaceQuestionSyncService.php`
  - `firstOrNew` + `fill` + `save` ile idempotent upsert ✅
  - Satır bazlı `try/catch` ile hata toleransı ✅
  - Webhook tetiklemeli bildirim (`NotificationCenterService::notifyQuestionReceived()`) ✅
- **Model:** `MarketplaceQuestion` → `app/Models/MarketplaceQuestion.php`
- **İlişkili modeller:** `MarketplaceQuestionMessage`, `MarketplaceQuestionAnswerLog`, `MarketplaceQuestionRule`, `MarketplaceQuestionTemplate`

### 3.2 AI Öneri

- **Servis:** `MarketplaceQuestionAiService::suggestAnswer()` → `app/Services/Marketplace/MarketplaceQuestionAiService.php`
- **Sabit güven skoru:** `'ai_confidence' => 60` — hesaplanmış değil, **sabit hardcode** ⚠️
- **Kaynak:** `MarketplaceQuestionTemplate` örnekleri + `AIService::ask()` (Groq/OpenAI/Gemini)
- Başarısız AI yanıtında fallback sabit metin döner ✅

### 3.3 Cevap Gönderme

- **Servis:** `MarketplaceQuestionAnswerService::sendAnswer()` → senkron HTTP çağrısı ⚠️
- **Connector:** `TrendyolConnector::answerCustomerQuestion()` → `POST /integration/qna/sellers/{id}/questions/{qid}/answers` — **GERÇEK API ÇAĞRISI** ✅
- Validasyon: cevap 10–2000 karakter arası ✅
- Başarısız olursa `MarketplaceQuestionAnswerLog::status = 'failed'` ✅
- **Outbox/queue yok** — senkron, retry mekanizması bulunmuyor ⚠️
- **UI:** `app/Livewire/MarketplaceQuestions.php` + `resources/views/livewire/marketplace-questions.blade.php`

---

## 4. Mevcut Uçtan Uca WhatsApp Inbound/Outbound Akışı

### 4.1 Inbound (Webhook → İşleme)

1. **Meta webhook:** `WebhookController::handleMeta()` → `app/Http/Controllers/WhatsApp/WebhookController.php`
   - HMAC SHA-256 imza doğrulama ✅
   - Payload boyut sınırı (1 MB) ✅
   - IP rate limit ✅
   - Idempotency: `provider_event_key` hash ile `WaWebhookEvent::firstOrCreate()` ✅
2. **Job:** `ProcessMetaWebhookJob` → `app/Jobs/WhatsApp/ProcessMetaWebhookJob.php` (async queue)
3. **AI chat:** `AiChatService::processInboundMessage()` → `app/Services/WhatsApp/AiChatService.php`
   - `ai_status === 'handed_off'` kontrolü (human ownership lock) ✅
   - Suppression kontrolü ✅
   - Tool çağrısı: `ToolRouter` → `ProductLookupTool`, `OrderStatusTool`, `ReturnStatusTool`, `StockAvailabilityTool`, `PolicyKnowledgeTool`, `HumanHandoffTool`
   - `ResponseGuardService` ile içerik güvenlik filtresi ✅
   - `WaAiRun` ile her AI çalışması kaydediliyor ✅

### 4.2 Outbound (Kuyruk → Meta API)

1. **Outbox servisi:** `OutboxService::enqueue()` → `app/Services/WhatsApp/OutboxService.php`
   - `wa_outbox` tablosuna yazar ✅
   - `idempotency_key` unique constraint ✅
   - **Önemli kısıt:** Mevcut `OutboxService` **yalnız WooCommerce mağazaları** için kuyruğa alıyor ⚠️
2. **Meta API:** `MetaCloudApiService` → `app/Services/WhatsApp/MetaCloudApiService.php`
3. **İşleme komutu:** `WhatsAppProcessOutboxCommand`

### 4.3 Human Handoff

- `HumanHandoffService::initiateHandoff()` → `ai_status = 'handed_off'` + `WaHandoff` kaydı ✅
- Audit log: `AuditLogService::log()` ✅
- **Serbest bırakma:** Fonksiyonel geri bırakma `resolve(WaHandoff $handoff, string $resolution, ?int $userId = null)` metodu ile mevcuttur (`app/Services/WhatsApp/HumanHandoffService.php:64`). resolved durumunda conversation `ai_status = 'active'` olur. Eksik olan; açık ownership state machine, yetki/policy kontrolü, concurrency koruması ve "insan çözümü" ile "AI'a geri bırakma" kararlarının ayrı audit edilebilir eylemler olmasıdır. ⚠️

---

## 5. Mevcut `support_*` Birleşik Kanal Mimarisi

### 5.1 Migration

**`2026_07_04_140000_create_support_channel_tables.php`** — 6 tablo:

| Tablo | Amaç |
|---|---|
| `support_channels` | Kanal kaydı (store_id + key unique) |
| `support_channel_capabilities` | Kanal yetenekleri (read/send/webhook...) |
| `support_conversations` | Birleşik konuşmalar (external_id + source_type) |
| `support_messages` | Birleşik mesajlar (`body_encrypted` 'encrypted' cast ile) |
| `support_sync_cursors` | Sync cursor takibi |
| `support_agent_actions` | Temsilci işlem geçmişi |

### 5.2 Servis Katmanı

| Sınıf | Dosya | Durum |
|---|---|---|
| `SupportChannelAdapterInterface` | `app/Services/Support/SupportChannelAdapterInterface.php` | ✅ Mevcut minimal contract / güçlendirilecek |
| `SupportChannelManager` | `app/Services/Support/SupportChannelManager.php` | ✅ Adapter registry |
| `SupportConversationSyncService` | `app/Services/Support/SupportConversationSyncService.php` | ✅ Cursor + error handling |
| `SupportReplyService` | `app/Services/Support/SupportReplyService.php` | ✅ Senkron gönderim |
| `SupportCapabilityService` | `app/Services/Support/SupportCapabilityService.php` | ✅ Capability refresh |

### 5.3 Adapter Durumu

| Adapter | Sync Conversations | sendReply Durumu |
|---|---|---|
| `WhatsAppSupportChannelAdapter` | ✅ `wa_conversations`'dan projection | ⚠️ `placeholder / sahte başarı`: `WhatsAppSupportChannelAdapter::sendReply` metodu gerçek bir outbox kaydı (`WaOutbox`) veya Meta API gönderimi yapmadan doğrudan `success=true` döner. |
| `TrendyolSupportChannelAdapter` | ⚠️ `['synced' => 0]` — boş dönüyor | ⚠️ `['success' => false]` — gerçek çağrı yok |
| `HepsiburadaSupportChannelAdapter` | ⚠️ `['synced' => 0]` — boş dönüyor | ⚠️ `['success' => false]` — gerçek çağrı yok |
| `NullSupportChannelAdapter` | ✅ Güvenli fallback | ✅ |

**Kritik bulgu:** `TrendyolSupportChannelAdapter::sendReply()` daima `['success' => false]` döndürüyor. Gerçek `MarketplaceQuestionAnswerService` çağrısı **bağlanmamış**. `SupportReplyService` bu durumda `delivery_status = 'failed'` kaydediyor.

---

## 6. Kanal Capability Matrisi

| Kanal | read_messages | send_messages | webhooks | attachments | ai_suggestions |
|---|---|---|---|---|---|
| **Trendyol** (support adapter) | `available`* | `available`* | `available`* | `unavailable` | `available`* |
| **WhatsApp** (support adapter) | `available`* | `available`* | `available` | `available`* | `available`* |
| **Hepsiburada** (support adapter) | `available`* | `available`* | `unknown` | `unavailable` | `available`* |
| **Instagram** | yok | yok | yok | yok | yok |
| **Facebook** | yok | yok | yok | yok | yok |
| **Google Business** | yok | yok | yok | yok | yok |
| **Site widget** | yok | yok | yok | yok | yok |

> `*` = Adapter statik bildirim yapıyor; gerçek API testi bağlanmamış

---

## 7. Yeniden Kullanılacak Sınıf ve Tablolar

| Varlık | Yol | Gerekçe |
|---|---|---|
| `support_channels` tablosu | migration | Birleşik kanal kaydı için olgun şema |
| `support_conversations` tablosu | migration | Projection katmanı için doğru temel |
| `support_messages` tablosu | migration | `body_encrypted` 'encrypted' cast ile güvenli |
| `support_agent_actions` tablosu | migration | Audit trail temeli |
| `support_sync_cursors` tablosu | migration | Sync state yönetimi |
| `SupportChannelAdapterInterface` | app/Services/Support | Genişletilebilir contract |
| `SupportChannelManager` | app/Services/Support | Adapter registry |
| `SupportConversationSyncService` | app/Services/Support | Cursor + error handling sağlam |
| `WaOutbox` + `OutboxService` | app/Models + app/Services/WhatsApp | Idempotency, queue, retry temeli (WA için) |
| `WaHandoff` + `HumanHandoffService` | app/Models + app/Services/WhatsApp | Human ownership lock temeli |
| `WaKnowledgeArticle` + `KnowledgeBaseService` | app/Models + app/Services/WhatsApp | Bilgi merkezi şeması |
| `MarketplaceQuestion*` ailesi | app/Models + app/Services/Marketplace | Trendyol soru-cevap kanonik kaynağı |
| `ResponseGuardService` | app/Services/WhatsApp | Güvenlik filtresi |
| `AiChatService` | app/Services/WhatsApp | Tool çağrısı, guard, run kaydı |
| `GeminiAiProvider` + `AiProviderInterface` | app/Services/WhatsApp | WhatsApp'a özel mevcut provider implementasyonu/adapter adayı; canonical CustomerCare contractı değildir. HTTP implementasyonu tekrar yazılmadan Faz 1'de generic contract arkasına alınabilir. Mevcut DI binding production için fail-open risklidir. |
| `WebhookController` | app/Http/Controllers/WhatsApp | HMAC, rate limit, idempotency |
| `TrendyolConnector::answerCustomerQuestion` | app/Services/Marketplace/Connectors | Gerçek API çağrısı çalışıyor |

---

## 8. Güçlendirilecek Sınıf ve Tablolar

| Varlık | Mevcut Sorun | Önerilen İyileştirme |
|---|---|---|
| `TrendyolSupportChannelAdapter::sendReply` | Daima `['success' => false]` | `MarketplaceQuestionAnswerService` ile bağla (Faz 4) |
| `SupportReplyService` | Senkron gönderim, retry yok | Outbox/queue mimarisine taşı (Faz 3) |
| `MarketplaceQuestionAiService` | `ai_confidence = 60` sabit | Kaynak + entity + güncellik sinyallerine göre hesapla |
| `KnowledgeBaseService::calculateRelevance` | `$query` boş geçilmekte | Gerçek query bazlı relevance (Faz 7) |
| `WaAiRun` | `response_time_ms` var; token/maliyet kaydı yok | Token ve maliyet alanları ekle (Faz 6) |
| `OutboxService::enqueue` | Yalnız WooCommerce izin veriyor | Kanal bazlı kısıtı adapter'a taşı |
| `WaKnowledgeArticle` | `store_id nullable` — tenant sızıntısı riski | Global/tenant ayrımını net belirt |
| Support migration | `human_owned_at`, `handoff_released_at` yok | ZCC-003 için kilitleme alanları ekle (Faz 2) |

---

## 9. Oluşturulması Gerçekten Gereken Yeni Parçalar

| Parça | Gerekçe | ZCC |
|---|---|---|
| Birleşik inbox Livewire component | `WhatsAppInbox` yalnız WA; çok kanallı inbox yok | ZCC-009 |
| Copilot UI (taslak + kaynak gösterimi) | `ai_mode` field var ama sunan UI yok | ZCC-004 |
| Güven puanı hesaplama motoru | Sabit 60 → sinyal bazlı hesaplama | ZCC-003 |
| Kanal politika motoru (deterministik validator) | `ResponseGuardService` kısmi; kanal/intent kural seti yok | ZCC-005 |
| Marka sesi profili | Kod tabanında **hiç yok** | ZCC-007 |
| Öğrenme önerisi yaşam döngüsü (draft → onay → yayın) | `upsertArticle` doğrudan yazıyor | ZCC-006 |
| `SupportChannel` route ve middleware | Route/middleware/UI hiç yok | Faz 5 |
| Analitik: copilot kabul/ret/düzenleme oranı | `SupportAgentAction` var ama toplama yok | ZCC-011 |
| Human ownership state machine | resolve() fonksiyonel geri bırakma yapıyor; ayrı yetki/policy, concurrency ve auditli release kararı eksik | ZCC-003 |
| Onboarding sihirbazı (iletişim merkezine özel) | Yalnız marketplace onboarding var | ZCC-014 |
| Yanlış cevap geri alma yaşam döngüsü | Retract/edit capability akışı yok | ZCC-017 |
| Site widget altyapısı | Hiç yok | ZCC-008, ZCC-012 |
| Instagram / Facebook / Google Business adapter | Hiç yok | ZCC-009 |
| ADR'ler (mimari karar kayıtları) | Faz 1 öncesi şart | Faz 1 |

---

## 10. Oluşturulmaması Gereken Tekrar Yapılar

- `care_conversations` / `care_messages` tabloları — `support_*` tabloları doğru adaydır.
- Trendyol için ikinci soru-cevap modeli — `MarketplaceQuestion` kanonik kaynak olarak korunmalı.
- `wa_outbox` WhatsApp kanal outbox'ı olarak korunmalıdır. Kanallar arası dispatch için `support_messages` lifecycle + generic dispatch tablosu veya yeni `support_outbox` seçenekleri Faz 1 ADR'sinde değerlendirilmelidir. WhatsApp şeması Trendyol'a zorla genişletilmemelidir. ⚠️
- Mevcut sağlayıcı implementasyonu/adaptörü yeniden kullanılabilir; fakat Faz 1'de kanal bağımsız CustomerCare AI contractı tanımlanabilir. Bu contract mevcut `AIService` ve/veya WhatsApp providerlarını adapter arkasından kullanır. İkinci bir aynı Gemini HTTP implementasyonu yazılmaz. ⚠️
- `support_*` tablolarının yeniden adlandırılması — geriye uyumluluk bozulur.

---

## 11. Tenant ve Yetkilendirme Modeli

- Mevcut sahiplik: `User → LegalEntity → MarketplaceStore` ⚠️
- Mevcut kayıt partition anahtarı çoğunlukla `store_id`
- Support sorgularında otomatik query scope/policy yok
- `support_channels.store_id` nullable
- Çok kullanıcılı firma üyeliği/RBAC eksik
- Organization gerekip gerekmediği Faz 1 ADR çıktısıdır. Karar verilene kadar mevcut store/user sahipliği yeni SaaS tenant modeli gibi varsayılmayacaktır. ⚠️

---

## 12. Güvenlik ve KVKK Riskleri

| Alan | Durum | Risk |
|---|---|---|
| `SupportMessage.body_encrypted` | Laravel 'encrypted' cast ✅ | Düşük |
| `WaInboundMessage.body` | Şifreleme cast yok ⚠️ | Orta — plaintext DB'de |
| Credential saklama | `IntegrationConnection.credentials_encrypted` (`encrypted:array` cast ile şifreli) ✅ | Düşük/Orta (webhook_secret için encrypted cast yok, model serialization/UI masking ve key rotation doğrulanmalı) ⚠️ |
| AI sağlayıcıya gönderilen veri | Mesaj gövdesi Gemini/Groq'a gönderiliyor; minimize edilmiş mi? | Alt işleyici kaydı yok ⚠️ |
| `MarketplaceQuestionMessage.raw_payload` | Plaintext ⚠️ | Orta — PII içerebilir |
| Audit log | `WaAuditLog` + `SupportAgentAction` ✅ | Support modülü otomasyon audit'i eksik |
| Retention / silme | `WhatsAppRetentionCleanupCommand` ✅; support mesajları için yok ⚠️ | Orta |
| RBAC | `EnsureWhatsAppFeatureEnabled` var; support rol hiyerarşisi tanımsız ⚠️ | — |
| AI fail-open riskleri | `AiProviderInterface` binded FakeAiProvider ⚠️ | Yüksek — fail-open / sahte yanıt riski (Düzeltme 5) |

---

## 13. Queue, Outbox, Retry ve Idempotency Riskleri

| Alan | Durum |
|---|---|
| WA webhook idempotency | `provider_event_key` hash → `firstOrCreate()` ✅ |
| WA outbox idempotency | `idempotency_key` unique constraint ✅ |
| WA job uniqueness | `ShouldBeUnique` yok `ProcessMetaWebhookJob`'da ⚠️ |
| Support cevap gönderimi | Senkron HTTP, retry yok ⚠️ |
| Trendyol cevap gönderimi | Senkron HTTP, retry yok ⚠️ |
| Queue driver | Database queue MVP için korunabilir; queue driver seçimi performans/yük testi ve deployment koşullarıyla Faz 3 öncesi veya production sertleştirmesinde karara bağlanır. Redis zorunluluğu ölçümsüz mimari karar değildir. ⚠️ |
| Failed job yönetimi | `WhatsAppRetryFailedCommand` var ✅ |
| Race condition koruması | `OutboxService::claimForProcessing()` var ✅ |

---

## 14. AI Doğruluk, Kaynak, Güven ve Maliyet Boşlukları

- **Güven puanı:** `MarketplaceQuestionAiService`: **sabit 60** ⚠️
- **Kaynak defteri (ZCC-002):** AI taslağında kaynak ID/tür kaydedilmiyor ⚠️
- **Token/maliyet takibi:** `WaAiRun`: yalnız `response_time_ms`; token/cost yok ⚠️
- **Structured output:** Genel `ask()` metodu; JSON schema yok (sadece `ReturnVisionService`'de) ⚠️
- **Prompt versiyonu:** Versiyonlama yok ⚠️
- **Provider fallback & Binding:** `AIService`'de 3 kademeli fallback ✅; `AiProviderInterface` `AppServiceProvider.php:18`'de koşulsuz olarak `FakeAiProvider`'a bind edilmiştir. `AiChatService` interface inject ettiği için gerçek çalışma durumunda Gemini kullanılmamaktadır. `GeminiAiProvider` de API anahtarı yoksa veya hata verirse FakeAiProvider cevabı döndürüyor. Bu davranış yüksek riskli **fail-open / sahte yanıt** problemidir. Provider selection ortam/config bazlı ve fail-closed olmalıdır. Production'da gerçek provider başarısızsa taslak başarısız/eskalasyon olmalı; FakeAiProvider yalnız test veya açık demo modunda kullanılmalıdır. ⚠️
- **Relevance/embedding:** Keyword string match; `calculateRelevance($article, '')` — query boş geçiliyor ⚠️
- **Marka sesi:** Kod tabanında **hiç yok** ⚠️
- **Golden dataset:** Kod tabanında **hiç yok** ⚠️
- **Shadow/canary pilot:** Kod tabanında **hiç yok** ⚠️
- **Çalışma modu altyapısı:** `SupportConversation.ai_mode` ve WhatsApp `ai_status` alanları başlangıç/placeholder altyapıdır. Eksik olan; enforce edilen mode state machine, policy, UI, audit ve otomasyon kararıdır. ⚠️

---

## 15. Test Kapsamı ve Güvenilirlik Değerlendirmesi

### 15.1 SupportChannelTest Sonucu

```text
docker exec zolm-laravel.test-1 php artisan test tests/Feature/WhatsApp/SupportChannelTest.php

Tests:    1 risky, 10 passed (15 assertions)
Duration: 1.29s
```

| Test | Sonuç |
|---|---|
| `whatsapp_adapter_shows_wa_conversations` | ✅ PASS |
| `same_whatsapp_message_not_duplicated` | ✅ PASS |
| `unsupported_channel_no_api_call` | ✅ PASS |
| `unsupported_channel_no_sync` | ✅ PASS |
| `unavailable_capability_blocks_reply` | ✅ PASS |
| `whatsapp_adapter_can_reply_when_capable` | ✅ PASS |
| `whatsapp_contact_not_auto_merged_with_marketplace` | ✅ PASS |
| `marketplace_customer_not_in_whatsapp_automation` | ✅ PASS |
| `sync_cursor_updates` | ✅ PASS |
| `ai_suggestion_only_mode` | ✅ PASS |
| `whatsapp_raw_payload_not_in_support_message` | ⚠️ RİSKLİ (Test konuşmayı oluşturuyor ancak `WaInboundMessage` oluşturmuyor. `fetchMessages()` boş döndüğü için `foreach` döngüsü hiç çalışmıyor ve sıfır assertion oluşuyor. Raw payload güvenliğini fiilen kanıtlamıyor.) ⚠️ |

### 15.2 Genel Test Değerlendirmesi

- **182 test** dosyası mevcut ✅
- WhatsApp: 25+ test dosyası ✅
- **Kritik eksiklik:** Testler gerçek outbound API'yi değil iskeleti kanıtlıyor — `TrendyolSupportChannelAdapter::sendReply` `false` döndürdüğü için bu path test edilmiyor ⚠️
- Golden dataset / shadow / integration test bulunmuyor ⚠️

---

## 16. Önerilen Canonical Çekirdek Kararı

**`support_*` tabloları ve `App\Services\Support` katmanı birleşik iletişim çekirdeğinin kanonik adayıdır.**

Gerekçe:

- 6 tabloluk olgun schema (konuşma, mesaj, capability, cursor, agent action)
- `SupportChannelAdapterInterface` ile genişletilebilir adapter contract
- `SupportMessage.body_encrypted` şifreleme cast mevcut
- WhatsApp adapter `wa_conversations`'dan güvenli projection yapıyor (çift kayıt yok)
- SupportChannelTest 10/11 geçiyor

**Paralel `care_*` tablo ailesi oluşturulmamalıdır.**

---

## 17. İlk Trendyol Copilot Pilotunun Minimum Kapsamı

Bu 6 bileşen tamamlanmadan copilot pilotu açılmamalıdır:

1. `TrendyolSupportChannelAdapter::sendReply` → `MarketplaceQuestionAnswerService` gerçek bağlantısı (Faz 4)
2. `SupportConversation` Livewire inbox — Trendyol sorularını birleşik konuşma olarak gösteren temel UI
3. Copilot UI — AI taslağını + kaynaklarını gösteren, insan onayı gerektiren panel
4. `ai_confidence` sabit 60'tan kaynak sinyallerine dayalı hesaplamaya geçiş
5. `SupportReplyService` queue/outbox mimarisine taşınması
6. Marka sesi — minimum `tone`, `greeting`, `closing` alanları

---

## 18. Faz 1'e Geçiş ve Mimari Karar Durumu

### Faz 1 için onaylanmış başlangıç kararları

- **`support_*` Birleşik İletişim Çekirdeği:** `support_*` tabloları ve `App\Services\Support` katmanının canonical çekirdek olduğu onaylanmıştır. Yeni paralel `care_*` tabloları oluşturulmayacaktır.
- **Kanal Source-of-Truth Kayıtları:** Trendyol için `MarketplaceQuestion`, WhatsApp için `wa_*` modellerinin kanonik yapısı korunacaktır.
- **Trendyol Soru Entegrasyonu:** `MarketplaceQuestion` ↔ `SupportConversation` ilişkisi ilk aşamada idempotent projection (deterministic external ID ve source reference ile) olacaktır. Legacy yapılara zorunlu yabancı anahtar (FK) veya büyük refactoring yapılmayacaktır.
- **Otomatik Cevap Eşikleri:** Otomatik cevap (auto-reply) başlangıçta kapalı kalacaktır. Güven eşikleri ve limitleri golden dataset ve shadow pilot verisiyle Faz 9–10 kapsamında kalibre edilecektir.
- **Database Queue:** Database queue driver MVP için korunacaktır. Redis'e geçiş zorunluluğu, Faz 3 öncesi veya production sertleştirmesinde yük ve performans testleriyle kararlaştırılacaktır.
- **KVKK Kısıtı:** KVKK alt işleyici (Gemini/Groq) belgesinin olmaması, Faz 1 ADR ve dokümantasyon sürecini engellemez; ancak gerçek müşteri verisinin LLM'e gönderilmesini ve production pilotunu engeller.

### Faz 1 içinde ADR ile sonuçlandırılacak kararlar

- **`WaKnowledgeArticle` Kapsamı:** WhatsApp bilgi tabanının birleşik `support` yapısı kapsamına alınıp alınmayacağı ve `store_id`'nin tenant izolasyonundaki rolü ADR ile kararlaştırılacaktır.
- **Organizasyon ve Rol Modeli:** Çok kullanıcılı firma yapısı ve roller (`user_id → legal_entity → organization` hiyerarşisi) için tasarlanacak tenant modeli ADR çıktısı olacaktır. Karar verilene kadar mevcut store/user sahipliği nihai SaaS tenant modeli olarak varsayılmayacaktır.
- **Outbox Servis Kısıtı:** WhatsApp'a özel `OutboxService`'in kanallar arası dispatch yapısına nasıl evrileceği ADR ile belirlenecektir.

---

## 19. ZCC-001–ZCC-018 Kapsam Matrisi

| ZCC | Başlık | Durum | Not |
|---|---|---|---|
| ZCC-001 | Katalog ve sipariş temelli cevap | **kısmi** | WA tool'ları mevcut; Trendyol support path bağlanmamış |
| ZCC-002 | Kaynak ve iddia defteri | **placeholder** | `WaAiRun` var ama kaynak ID/tür kaydı yok |
| ZCC-003 | Güven, risk ve sessiz insan devri | **kısmi** | `ai_status='handed_off'` kilidi var; `resolve()` ile fonksiyonel geri bırakma mevcut; ownership state machine, yetki/policy ve concurrency korumaları eksik. |
| ZCC-004 | Üç yanıt modu (auto/copilot/manual) | **placeholder** | `ai_mode` field var; UI ve mod geçiş mantığı yok |
| ZCC-005 | Kanal politika motoru | **kısmi** | `ResponseGuardService` var; kanal/intent kural seti yok |
| ZCC-006 | İnsan onaylı öğrenme merkezi | **yok** | `upsertArticle` doğrudan yazıyor; taslak/onay akışı yok |
| ZCC-007 | Marka sesi | **yok** | Kod tabanında hiçbir alan veya servis bulunamadı |
| ZCC-008 | Site canlı destek widget | **yok** | Hiçbir web widget altyapısı yok |
| ZCC-009 | Birleşik kanal deneyimi | **kısmi** | `support_*` temeli var; sosyal kanallar yok |
| ZCC-010 | Satış ve ürün danışmanlığı | **kısmi** | WA `ProductLookupTool` var; Trendyol copilot path yok |
| ZCC-011 | Kalite ve operasyon analitiği | **placeholder** | Log kayıtları var; toplama/sunum ve demo sayı yok |
| ZCC-012 | Site asistanı ve lead devri | **yok** | Widget ve CRM lead altyapısı yok |
| ZCC-013 | Çözülen iş problemleri ve sonuç ölçümü | **yok** | Before/after ölçüm altyapısı yok |
| ZCC-014 | Hızlı ve doğrulanabilir onboarding | **kısmi** | Marketplace onboarding var; capability/shadow adımı eksik |
| ZCC-015 | CRM, ERP ve iç sistem entegrasyon sınırı | **kısmi** | `IntegrationAdapterInterface` var; versiyonlu API sınırı yok |
| ZCC-016 | Veri güvenliği, KVKK ve RBAC | **kısmi** | `body_encrypted` cast ✅; credential şifreleme `credentials_encrypted` cast ile var ✅; support channel RBAC ve webhook_secret şifrelemesi yok ⚠️ |
| ZCC-017 | Yanlış cevap, geri alma ve düzeltme | **yok** | Retract capability veya düzeltme akışı yok |
| ZCC-018 | Türkçe öncelikli çok dilli çalışma | **kısmi** | Türkçe UI/prompt var; ölçülebilir değerlendirme seti yok |

---

## 20. Her ZCC Gereksinimi İçin Mevcut Kanıt Dosyaları ve Ana Boşluk

- **ZCC-001 — Kanıt:** `app/Services/WhatsApp/Tools/OrderStatusTool.php`, `ProductLookupTool.php` ✅ (WA için); `TrendyolConnector::pullCustomerQuestions` gerçek ✅. **Boşluk:** `TrendyolSupportChannelAdapter::syncConversations` 0 döndürüyor.
- **ZCC-002 — Kanıt:** `app/Models/WaAiRun.php` (run kaydı var). **Boşluk:** `source_name`, `source_type`, kayıt ID alanı yok; `MarketplaceQuestionAiService` kaynak bağlamıyor.
- **ZCC-003 — Kanıt:** `app/Services/WhatsApp/HumanHandoffService.php` (`initiateHandoff()` ✅ ve `resolve()` fonksiyonel geri bırakma mevcut ✅), `WaConversation.ai_status` ✅. **Boşluk:** Güven eşiği hesaplama motoru yok; ownership state machine, yetki/policy ve concurrency korumaları eksik.
- **ZCC-004 — Kanıt:** `SupportConversation.ai_mode` field mevcut. **Boşluk:** `copilot`, `automatic`, `manual` mod geçişi UI ve iş mantığı yok; varsayılan `automatic` bloğu enforced değil.
- **ZCC-005 — Kanıt:** `app/Services/WhatsApp/ResponseGuardService.php` (BLOCKED_PATTERNS, BLOCKED_INTENTS, PII regex) ✅. **Boşluk:** Kanal bazlı kural, karakter sınırı, template consent kontrolü yok.
- **ZCC-006 — Kanıt:** `app/Models/WaKnowledgeArticle.php` (status, effective_from, version alanları) ✅. **Boşluk:** `upsertArticle` doğrudan güncelleme yapıyor; gece analizi, kümeleme, taslak/onay akışı yok.
- **ZCC-007 — Boşluk:** Marka sesi profili için model, tablo, servis veya config alanı bulunamadı.
- **ZCC-008 — Boşluk:** Web widget kodu, route, controller, embed script bulunamadı.
- **ZCC-009 — Kanıt:** `SupportChannelManager` (WA + Trendyol + Hepsiburada adapter) ✅; `app/Livewire/WhatsApp/WhatsAppInbox.php` ✅. **Boşluk:** Birleşik inbox UI yok; sosyal kanal adapter yok; N11 support adapter yok.
- **ZCC-010 — Kanıt:** `ProductLookupTool.php`, `StockAvailabilityTool.php` ✅. **Boşluk:** Trendyol copilot path bağlanmamış; beden/sağlık risk kuralı yok.
- **ZCC-011 — Kanıt:** `app/Services/WhatsApp/AnalyticsService.php`, `WaAuditLog.php` ✅. **Boşluk:** Copilot kabul/düzenleme/ret oranı hesaplama yok; demo sabit sayı bulunamadı (olumlu).
- **ZCC-012 — Boşluk:** Site asistanı ve lead devri altyapısı yok.
- **ZCC-013 — Boşluk:** Tekrar soru oranı, AI çözüm oranı, after-hours otomasyon oranı ölçümü yok.
- **ZCC-014 — Kanıt:** `MarketplaceConnectionReadinessService` ✅, `MarketplaceOnboardingGuideService` ✅. **Boşluk:** İletişim merkezi için özel capability test, shadow pilot ve marka ayarı adımı yok.
- **ZCC-015 — Kanıt:** `IntegrationAdapterInterface`, `IntegrationAdapterRegistry`, `TrendyolIntegrationAdapter`, `WooCommerceIntegrationAdapter` ✅. **Boşluk:** Versiyonlu public API sınırı, tenant kapsamlı API erişim kontrolü yok.
- **ZCC-016 — Kanıt:** `SupportMessage.body_encrypted` 'encrypted' cast ✅, `EnsureWhatsAppFeatureEnabled` middleware ✅, `IntegrationConnection.credentials_encrypted` 'encrypted:array' cast ✅. **Boşluk:** `webhook_secret` şifrelemesi yok; support channel RBAC yok.
- **ZCC-017 — Boşluk:** Gönderilmiş mesaj için retract/edit capability sistemi yok; düzeltme mesajı ve agent görevi oluşturma akışı yok.
- **ZCC-018 — Kanıt:** Türkçe sistem promptları ✅, Türkçe UI ✅. **Boşluk:** Dil tespit servisi, dil bazlı golden dataset, test edilmemiş dilde otomatik mod engeli yok.

---

## En Kritik 7 Bulgu

1. **`TrendyolSupportChannelAdapter::sendReply` çalışmıyor** — Daima `['success' => false]` döndürüyor. Gerçek `MarketplaceQuestionAnswerService` çağrısı bağlanmamış. Trendyol copilot pilotunun en öncelikli bloker'ıdır. Dosya: `app/Services/Support/TrendyolSupportChannelAdapter.php` satır 52.
2. **AI provider fiilen `FakeAiProvider`'a koşulsuz bağlı** — `AiProviderInterface` `AppServiceProvider.php:18`'de koşulsuz olarak `FakeAiProvider`'a bind edilmiştir. Container çözümünde Gemini fiilen kullanılmamaktadır. Ayrıca `GeminiAiProvider` de hata durumlarında `FakeAiProvider`'a fail-open / sahte yanıt döndürmektedir.
3. **WhatsApp Support adapter outbox'a yazmıyor (sahte başarı)** — `WhatsAppSupportChannelAdapter::sendReply` metodu gerçek bir outbox veya Meta API çağrısı yapmadan doğrudan `success=true` döndürüyor.
4. **AI güven puanı sabit `60`** — `MarketplaceQuestionAiService::suggestAnswer()` içinde `'ai_confidence' => 60` hardcoded. Sinyal bazlı güven hesaplaması tamamen eksiktir.
5. **Marka sesi ve çalışma modu altyapısı eksik** — ZCC-007 (marka sesi) ve ZCC-004 (çalışma modları) için ne model ne de servis var. Sadece `ai_mode` ve `ai_status` gibi placeholder/başlangıç alanları mevcuttur.
6. **Tenant izolasyonu sorgu bazında enforce edilmiyor** — `SupportConversation` ve `WaKnowledgeArticle` sorgularında otomatik store filtresi (global scope veya middleware) yok.
7. **`SupportReplyService` senkron ve retry-free çalışıyor** — `SupportReplyService` haricî gönderimi request akışında senkron yapıyor; generic dispatch/outbox ve retry olmadan güvenilir production gönderimi sağlayamaz. Dosya: `app/Services/Support/SupportReplyService.php`.

---

## 21. Mimari Kararlar (Codex Baş Mühendis Onayı)

Faz 0 revizyonu kapsamında Codex tarafından onaylanan mimari kararlar aşağıdadır:

1. **`support_*` birleşik conversation/message projection çekirdeği onaylandı.** Yeni paralel `care_conversations` ve `care_messages` oluşturulmayacak.
2. **Kanal source-of-truth kayıtları korunacak.** Trendyol için `MarketplaceQuestion`, WhatsApp için `wa_*` kanoniktir.
3. **MarketplaceQuestion → SupportConversation bağlantısı ilk aşamada idempotent projection olacaktır.** Legacy kayda zorunlu FK veya big-bang taşıma yapılmayacaktır. Deterministic external ID ve source reference kullanılacaktır.
4. **Otomatik cevap için başlangıç güven eşiği şu anda belirlenmeyecek.** Auto-reply kapalı kalacak; eşikler golden dataset ve shadow verisiyle Faz 9–10'da kalibre edilecektir.
5. **KVKK alt işleyici belgesi Faz 1 ADR/dokümantasyonu engellemez**, ancak gerçek müşteri verisinin haricî LLM'e gönderilmesini ve production pilotunu engeller.
6. **Organization kararı Faz 1 ADR çıktısıdır.** Karar verilene kadar mevcut store/user sahipliği yeni SaaS tenant modeli gibi varsayılmayacaktır.
7. **Database queue şimdilik korunabilir.** Redis zorunluluğu ölçümsüz mimari karar değildir.

---

*Bu rapor Faz 0 kapsamında salt okunur keşifle oluşturulmuştur. Hiçbir uygulama kodu, migration, route, config veya test değiştirilmemiştir.*
