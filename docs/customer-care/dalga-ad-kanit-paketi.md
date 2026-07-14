# Dalga AD — Kanıt Paketi
## Satış Copilot'u ve Güvenli Ürün Öneri Çekirdeği

**Tarih:** 2026-07-13  
**Durum:** Kalite Kapısı 01 Revizyonu Tamamlandı

---

## 1. Amaç

Dalga AD; temsilcilere satış copilot önerileri sunar.  
Stok dışı alternatifleri ve sepet kurtarma taslakları üretir.  
Stale fiyat/kampanya ile net satış vaadi üretmez.  
Cart recovery yalnız verified + fresh signed web chat cart signal ile çalışır.

---

## 2. Değişen Dosyalar

| Dosya | Değişiklik |
|---|---|
| [`CustomerCareSalesAssistService.php`](../../../app/Services/Support/CustomerCareSalesAssistService.php) | P0-2: `last_price_sync_at` freshness kontrolü; net fiyat cümlesi kaldırıldı; geçersiz fiyat elendi. P0-3: `resolveVerifiedCartValue()` — verified+fresh web_chat cart signal zorunlu |
| [`CustomerCareSalesAssistTest.php`](../../../tests/Feature/CustomerCare/CustomerCareSalesAssistTest.php) | 10 test (stale price, zero price, draft format, cart verified/unverified/stale/source, max limit, public block, PII) |

---

## 3. Migration Listesi

Dalga AD için yeni migration yoktur. `ChannelListing`, `ChannelProduct` tabloları önceki dalgalarda oluşturulmuştur.

---

## 4. Test İsimleri (10 test / 18 assertion)

```
✓ stale_price_listing_not_recommended                        ← P0-2
✓ suggested_draft_does_not_contain_explicit_price            ← P0-2
✓ zero_price_listing_is_excluded_from_suggestions            ← P0-2
✓ unverified_cart_signal_does_not_generate_cart_recovery     ← P0-3
✓ verified_fresh_cart_signal_generates_cart_recovery         ← P0-3
✓ stale_verified_cart_signal_does_not_generate_recovery      ← P0-3
✓ cart_recovery_only_works_for_web_chat_source               ← P0-3
✓ sales_suggestions_are_limited_to_max_3_items
✓ proactive_sales_suggestions_blocked_for_public_channels
✓ catalog_suggestions_pii_redacted_in_title
```

---

## 5. Feature Flag Varsayılanları (Hepsi KAPALI)

| Flag | ENV | Varsayılan |
|---|---|---|
| `sales_copilot_enabled` | `CUSTOMER_CARE_SALES_COPILOT_ENABLED` | `false` |
| `cart_recovery_enabled` | `CUSTOMER_CARE_CART_RECOVERY_ENABLED` | `false` |

---

## 6. Kalite Kapısı 01 — P0/P1 Düzeltmeleri

### P0-2 ✅ — Stale Fiyat / Net Satış Vaadi Engeli
- `ChannelListing` sorgulamasına `last_price_sync_at >= now()-24h` koşulu eklendi.
- Stale fiyatlı ürünler alternatif öneri listesinden çıkarılır.
- `sale_price <= 0` geçersiz fiyatlar elenir.
- Önerilen taslak metinlerinden `"Fiyatı: {price} {currency}"` cümlesi tamamen kaldırıldı.

### P0-3 ✅ — Verified + Fresh Signed Cart Signal
- `resolveVerifiedCartValue()` private metodu eklendi.
- Cart recovery yalnız şu koşulların **tamamı** sağlandığında çalışır:
  1. `source_type === 'web_chat'`
  2. `source_reference_json.cart_signal_verified === true`
  3. `source_reference_json.cart_signal_at >= now() - 60dk`
  4. `cart_value > 0`
- WhatsApp, Instagram DM veya başka kaynak türlerinde cart recovery pasiftir.
- Stale (60 dakikadan eski) cart signal kullanılmaz.

---

## 7. Cart Signal Sözleşmesi

`WebChatSupportChannelAdapter::projectMessage()` tarafından set edilmesi beklenen alanlar:

```json
{
  "cart_signal_verified": true,
  "cart_signal_at": "2026-07-13T10:00:00+03:00",
  "cart_value": 750.0,
  "cart_items": ["sku-1", "sku-2"]
}
```

> **Not:** `cart_signal_verified=true` yalnız HMAC doğrulanmış `raw_json`'dan decode edilerek set edilmelidir. Dış `$payload` alanına güvenilmez.

---

## 8. Güvenlik Özeti

| Risk | Düzeltme |
|---|---|
| Stale fiyat ile müşteriye yanlış fiyat vaadi | `last_price_sync_at` freshness zorunlu |
| Manipüle edilmiş cart signal | `cart_signal_verified=true` + source_type=web_chat |
| Proaktif satış public kanallarda | `publicChannels` blacklist |
| PII ürün başlıklarında | `PiiRedactor::maskPii()` |

---

## 9. Bilinen Eksikler / Rollback Notu

- Kampanya freshness (`last_campaign_sync_at`) henüz `ChannelListing` tablosunda yok; kampanya vaadi için ayrı alan gerekebilir (v2 planı).
- Rollback: `CustomerCareSalesAssistService`'i önceki commit'e geri al.
- Sıfır/negatif fiyat elenmesi `ChannelListing.sale_price` üretim verisinde nadir ama kritik koruma.
