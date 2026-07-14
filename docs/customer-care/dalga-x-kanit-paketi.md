# Dalga X — Kanıt Paketi (Revizyon 02)

**Dalga:** X — Web/E-Ticaret Site Chat Bridge & Public Widget Contract  
**Tarih:** 2026-07-13

---

## 1. Uygulanan Değişiklikler

### Yeni Dosyalar

| Dosya | Amaç |
|---|---|
| `app/Services/Support/WebChatSupportChannelAdapter.php` | Canlı destek widget adaptörü |
| `tests/Feature/CustomerCare/WebChatSupportChannelAdapterTest.php` | Dalga X test suite |
| `docs/customer-care/web-chat-widget-contract.md` | Widget kontratı, JSON örnekleri ve HMAC imzası dokümantasyonu |

### Düzenlenen Dosyalar

| Dosya | Değişiklik |
|---|---|
| `config/customer-care.php` | `web_chat_enabled` config eklendi |
| `app/Services/Support/SupportChannelManager.php` | `web_chat` adaptörü register edildi |
| `app/Services/Support/Policy/SupportPolicyEngine.php` | `web_chat` policy profili eklendi |
| `app/Services/Support/WebChatSupportChannelAdapter.php` | Gelen webhook payload'larında imza kontrolü ve connection webhook_secret kullanımı zorunlu kılındı. (P0-4 Düzeltmesi) İmza doğrulandıktan sonra payload'un sadece imzalanmış raw_json kısmı decode edilerek kanonik veri olarak kullanılmaktadır. Dış manipülatif payload alanları tamamen göz ardı edilir. |
| `app/Services/Support/SupportOutboxService.php` | Adapter'dan gelen `queued` statüsü korunarak outbox dispatch ve mesaj statülerinin ezilmeden `queued` kalması sağlandı. (P0-3 Düzeltmesi) |

---

## 2. Test Sonuçları

- `tests/Feature/CustomerCare/WebChatSupportChannelAdapterTest.php` (PASS)
  - `test_project_message_fails_closed_when_signature_missing` (PASS - İmzasız payload'lar 403 / fail-closed döner)
  - `test_project_message_fails_closed_when_signature_invalid` (PASS - Geçersiz imzalı payload'lar fail-closed döner)
  - `test_outbox_dispatch_queued_status_when_customer_offline` (PASS - Müşteri offline ise outbox dispatch ve bağlı mesaj statüleri queued olarak kalır)
  - `test_project_message_uses_signed_raw_json_not_outer_payload_fields` (PASS - İmza doğrulandıktan sonra verinin sadece imzalanmış raw_json kısmından okunduğunu, dış manipüle alanların ezilerek göz ardı edildiğini kanıtlar)

**11/11 test geçti.**

---

## 3. Güvenlik ve Uyumluluk Kontrolleri

| Kontrol | Sonuç | Açıklama |
|---|---|---|
| Widget Auth / HMAC | ✅ Geçti | `verifySignature` HMAC-SHA256 signature doğrulaması sağlar. İmzasız veya geçersiz imzalı istekler reddedilir. |
| Kanonik Signed Veri Kullanımı | ✅ Geçti | İmza doğrulandıktan sonra sadece imzalanmış `raw_json` decode edilip işlenir, dış alanlar manipülasyona karşı göz ardı edilir. |
| Inbound Idempotency | ✅ Geçti | Mükerrer mesaj projeksiyonu `idempotency_key` bazlı engellenir. |
| Guest Session Hashing | ✅ Geçti | Raw session ID sistemde saklanmaz; connection secret'ı ile hash'lenerek `external_conversation_id` üretilir. |
| IP/UA Redaction | ✅ Geçti | Inbound webhook projeksiyonu sırasında IP ve User-Agent bilgileri loglara sızmaz, redakte edilir. |
| Offline Delivery (No Fake Sent) | ✅ Geçti | Müşteri offline ise outbound yanıt `queued` olarak sıraya alınır (fake sent önlenir). |
| AI Hallucination Guard | ✅ Geçti | Uydurma sipariş veya katalog dışı ürün önerisi engeli mevcuttur. |

---

## 4. Test Suite Durumu

- **Customer Care Tests**: **219 passed / 838 assertions** (100% Green)
- **npm run build**: ✓ başarılı
- **git diff --check**: temiz
