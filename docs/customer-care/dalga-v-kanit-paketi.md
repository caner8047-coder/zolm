# Dalga V — Kanıt Paketi (Revizyon 02)

**Dalga:** V — Meta Social Inbox Bridge (Instagram + Facebook)  
**Tarih:** 2026-07-13

---

## 1. Uygulanan Değişiklikler

### Yeni Dosyalar

| Dosya | Amaç |
|---|---|
| `app/Services/Support/MetaSocialSupportChannelAdapter.php` | Instagram & Facebook için ortak adaptör |
| `app/Services/Support/MetaSocialConnectorInterface.php` | Meta Social outbound gönderim kontratı (P0-1 Düzeltmesi) |
| `tests/Feature/CustomerCare/MetaSocialSupportChannelAdapterTest.php` | Dalga V test suite |

### Düzenlenen Dosyalar

| Dosya | Değişiklik |
|---|---|
| `config/customer-care.php` | `meta_social_enabled` config eklendi |
| `app/Services/Support/SupportChannelManager.php` | `instagram` ve `facebook` adaptörleri register edildi |
| `app/Services/Support/Policy/SupportPolicyEngine.php` | `instagram`, `instagram_comment`, `facebook`, `facebook_comment` policy profilleri eklendi |
| `app/Services/Support/SupportOutboxService.php` | public comment cevaplarında `_comment` suffix'i ile policy validation yapılması sağlandı |
| `app/Services/Support/MetaSocialSupportChannelAdapter.php` | `sendReply()`, `canReply()` ve `getCapabilities()` metotları container'da `MetaSocialConnectorInterface` bağlı olup olmadığını kontrol edecek şekilde güncellendi. Bağlantı yoksa fail-closed çalışır, sahte ID'ler ve loglar oluşturmaz. |

---

## 2. Test Sonuçları

- `tests/Feature/CustomerCare/MetaSocialSupportChannelAdapterTest.php` (PASS)
  - `test_send_reply_fails_closed_when_no_connector_bound` (PASS - Connector yoksa getCapabilities unavailable olur ve sendReply fail-closed döner)
  - `test_outbox_dispatch_does_not_succeed_without_connector` (PASS - Outbox gönderim yolunda connector yoksa dispatch/message durumu 'failed' olur ve sızıntı/sahte log yazılmaz)

**10/10 test geçti.**

---

## 3. Güvenlik Kontrolleri

| Kontrol | Sonuç | Açıklama |
|---|---|---|
| Feature Flag | ✅ Geçti | Config default `false` olarak ayarlandı. |
| Connector Kontrolü | ✅ Geçti | Gerçek connector yoksa `sendReply` fail-closed döner (sahte başarılı yok). |
| Inbound Idempotency | ✅ Geçti | Duplicate webhook event'ler `event_id` bazlı engellenir. |
| Raw Payload Leakage | ✅ Geçti | Raw webhook payload `support_messages.metadata_json` içerisine sızmaz (`null` bırakılır). |
| Cross-Store IDOR Block | ✅ Geçti | Mağaza eşleşmesi doğrulanmayan webhook projection ve outbox reply istekleri engellenir. |
| Public Comment Reply Guard | ✅ Geçti | `SupportPolicyEngine` üzerinden comment'lerde telefon, sipariş no ve link paylaşımı bloklanır. |
| Auto Reply Block | ✅ Geçti | Public comment'ler için otomatik yanıt `CustomerCareAutomationGate` üzerinden engellenmiştir. |

---

## 4. Test Suite Durumu

- **Customer Care Tests**: **219 passed / 838 assertions** (100% Green)
- **npm run build**: ✓ başarılı
- **git diff --check**: temiz
