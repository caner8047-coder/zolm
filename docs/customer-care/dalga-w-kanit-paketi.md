# Dalga W — Kanıt Paketi (Revizyon 02)

**Dalga:** W — Google Business Profile Reviews / Reputation Inbox  
**Tarih:** 2026-07-13

---

## 1. Uygulanan Değişiklikler

### Yeni Dosyalar

| Dosya | Amaç |
|---|---|
| `app/Services/Support/GoogleBusinessSupportChannelAdapter.php` | Google Maps & Google Business Profile yorum adaptörü |
| `app/Services/Support/GoogleBusinessConnectorInterface.php` | Google Business Profile API yanıt gönderim kontratı (P0-2 Düzeltmesi) |
| `tests/Feature/CustomerCare/GoogleBusinessSupportChannelAdapterTest.php` | Dalga W test suite |

### Düzenlenen Dosyalar

| Dosya | Değişiklik |
|---|---|
| `config/customer-care.php` | `google_reviews_enabled` config eklendi |
| `app/Services/Support/SupportChannelManager.php` | `google_business` adaptörü register edildi |
| `app/Services/Support/Policy/SupportPolicyEngine.php` | `google_business` policy profili (sert kısıtlar) eklendi |
| `app/Services/Support/AI/CustomerCareAutomationGate.php` | Düşük yıldızlı (1-2) ve config izin listesinde olmayan yüksek yıldızlı GBP yorumlarına otomatik yanıt engeli eklendi |
| `app/Services/Support/GoogleBusinessSupportChannelAdapter.php` | `sendReply()`, `canReply()` ve `getCapabilities()` metotları container'da `GoogleBusinessConnectorInterface` bağlı olup olmadığını kontrol edecek şekilde güncellendi. Bağlantı yoksa fail-closed çalışır, sahte ID'ler ve loglar oluşturmaz. |

---

## 2. Test Sonuçları

- `tests/Feature/CustomerCare/GoogleBusinessSupportChannelAdapterTest.php` (PASS)
  - `test_send_reply_fails_closed_when_no_connector_bound` (PASS - Connector yoksa getCapabilities unavailable olur ve sendReply fail-closed döner)
  - `test_outbox_dispatch_does_not_succeed_without_connector` (PASS - Outbox gönderim yolunda connector yoksa dispatch/message durumu 'failed' olur ve sızıntı/sahte log yazılması engellenir)

**9/9 test geçti.**

---

## 3. Güvenlik Kontrolleri

| Kontrol | Sonuç | Açıklama |
|---|---|---|
| Feature Flag | ✅ Geçti | Config default `false` olarak ayarlandı. |
| Connector Kontrolü | ✅ Geçti | Gerçek connector yoksa `sendReply` fail-closed döner (sahte başarılı yok). |
| Inbound Idempotency | ✅ Geçti | Mükerrer yorum projeksiyonu `review_id` bazlı engellenir. |
| Cross-Store IDOR | ✅ Geçti | Mağaza eşleşmesi doğrulanmayan review projeksiyonları engellenir. |
| GBP Stricter Policy Guard | ✅ Geçti | Linkler, platform dışı yönlendirmeler ("dm atın", "whatsapp") ve agresif savunma dili ("hata bizde degil") bloklanır. |
| Auto Reply Rating Limits | ✅ Geçti | 1-2 yıldızlı yorumlara otomatik yanıt kapalıdır. 4-5 yıldızlılar için `google_reviews_auto_reply_enabled` config ayarı aranır. |
| Reputation Metrics Integrity | ✅ Geçti | KPI hesaplamalarında uydurma veri kullanılmaz. Veri yoksa boş array (empty state) döner. |

---

## 4. Test Suite Durumu

- **Customer Care Tests**: **219 passed / 838 assertions** (100% Green)
- **npm run build**: ✓ başarılı
- **git diff --check**: temiz
