# Dalga T — Kanıt Paketi (Revizyon 02)

**Dalga:** T — Birleşik Müşteri Hafızası / Identity  
**Tarih:** 2026-07-13

---

## 1. Uygulanan Değişiklikler

### Yeni Dosyalar

| Dosya | Amaç |
|---|---|
| `app/Services/Support/CustomerCareIdentityResolver.php` | Store-scoped kimlik çözümleme; no auto-merge, phone hash only |
| `app/Services/Support/CustomerCareCustomerSummaryService.php` | PII-masked, store-scoped müşteri özeti; empty state uydurma yok |
| `tests/Feature/CustomerCare/CustomerCareCustomerSummaryTest.php` | Dalga T test suite (8 test) |

### Düzenlenen Dosyalar

| Dosya | Değişiklik |
|---|---|
| `app/Services/Support/AI/CustomerCareContextBuilder.php` | `CustomerCareCustomerSummaryService` entegrasyonu; `customer_summary` AI context'e eklendi |

---

## 2. Güvenlik Tasarımı

| Prensip | Uygulama |
|---|---|
| No auto-merge | `canAssociate()` farklı channel type'ları reddeder |
| Phone hash only | Raw telefon değil, `phone_hash` (HMAC-SHA256) kullanılır |
| Store-scoped | Her resolver çağrısı store_id ile sınırlandırılmış |
| No fabrication | `getSummary()` veri yoksa `data_available=false` döner, uydurma yapılmaz |
| PII masked | Sipariş numaraları `PiiRedactor` ile maskelenir |
| AI safe context | `buildAiContextString()` yalnız store-scoped redacted veri döner |

---

## 3. Dalga T Test Sonuçları

```
PASS  Tests\Feature\CustomerCare\CustomerCareCustomerSummaryTest
✓ cross store customer summary does not leak
✓ whatsapp contact not auto merged with marketplace customer
✓ channel histories do not merge without deterministic key
✓ customer summary masks pii in order numbers
✓ ai context only includes current store data
✓ empty state does not fabricate orders or conversations
✓ identity resolver allows same store deterministic key
✓ cross store association blocked
```

**8/8 test geçti.**

---

## 4. Test Suite Durumu

- **Customer Care Tests**: **219 passed / 838 assertions** (100% Green)
- **npm run build**: ✓ başarılı
- **git diff --check**: temiz
