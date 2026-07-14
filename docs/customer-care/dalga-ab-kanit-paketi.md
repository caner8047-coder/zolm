# Dalga AB — Kanıt Paketi
## Kaynaklı Bilgi Merkezi v2

**Tarih:** 2026-07-13  
**Durum:** Kalite Kapısı 01 Revizyonu Tamamlandı

---

## 1. Amaç

Dalga AB, müşteri soru/yorum sistemine stok, fiyat, bilgi bankası ve iade politikalarına dayalı **kaynaklı** (grounded) AI cevap desteği ekler.  
AI kaynak bulamazsa uydurma yapmaz; `has_stale_data=true` durumunda handoff tetiklenir.

---

## 2. Değişen Dosyalar

| Dosya | Değişiklik |
|---|---|
| [`CustomerCareKnowledgeGroundingService.php`](../../../app/Services/Support/CustomerCareKnowledgeGroundingService.php) | P0-1 PII redaction, P1-2 fallback store-scope + stale, HTML/XML strip, max content uzunluğu |
| [`CustomerCareKnowledgeGroundingTest.php`](../../../tests/Feature/CustomerCare/CustomerCareKnowledgeGroundingTest.php) | 8 test (PII redaction, stale, cross-store, injection, citation, length) |
| [`CustomerCareSyncKnowledgeCommand.php`](../../../app/Console/Commands/CustomerCareSyncKnowledgeCommand.php) | knowledge sync artisan komutu |
| [`config/customer-care.php`](../../../config/customer-care.php) | knowledge_enabled flag eklendi |

---

## 3. Migration Listesi

Dalga AB için yeni migration yoktur. `wa_knowledge_articles` tablosu Dalga S/T/U kapsamında oluşturulmuştur.

---

## 4. Test İsimleri (8 test / 15 assertion)

```
✓ pii_in_knowledge_article_is_redacted_before_llm_context
✓ pii_in_knowledge_article_title_is_redacted
✓ mp_product_fallback_marked_as_stale
✓ cross_store_mp_product_fallback_does_not_leak_to_other_store
✓ stale_stock_or_price_flags_stale_data_and_prevents_auto_reply
✓ prompt_injection_detection_returns_empty_grounding_safely
✓ article_content_length_is_bounded
✓ citation_type_is_recorded_for_kb_articles
```

---

## 5. Artisan Komutları

```bash
# Knowledge senkronizasyonu (dry-run)
php artisan customer-care:sync-knowledge --dry-run

# Knowledge öneri oluşturma
php artisan customer-care:generate-knowledge-suggestions
```

---

## 6. Feature Flag Varsayılanları (Hepsi KAPALI)

| Flag | ENV | Varsayılan |
|---|---|---|
| `knowledge_enabled` | `CUSTOMER_CARE_KNOWLEDGE_ENABLED` | `false` |
| `sales_copilot_enabled` | `CUSTOMER_CARE_SALES_COPILOT_ENABLED` | `false` |

---

## 7. Kalite Kapısı 01 — P0/P1 Düzeltmeleri

### P0-1 ✅ — PII Redaction
- `WaKnowledgeArticle.title` ve `content` LLM context'e eklenmeden önce `PiiRedactor::maskPii()` geçer.
- HTML/XML strip + kontrol karakter temizliği uygulandı.
- Makale içeriği 1500 karakter ile sınırlandırıldı.
- Prompt injection filtresi PII redaction'dan **bağımsız** çalışır.

### P1-2 ✅ — MpProduct Fallback Store-Scope + Stale
- `MpProduct` fallback yalnız `where('store_id', $storeId)` ile store-scoped sorgu yapar.
- Fallback kaynakları için `is_stale=true` ve `has_stale_data=true` zorunlu olarak set edilir.
- Fallback kaynak net stok/fiyat kaynağı gibi AI context'e girmez; `[Fiyat: Belirsiz]` yazılır.

---

## 8. Bilinen Eksikler / Rollback Notu

- `MpProduct.store_id` kolonu mevcut şemada yoksa fallback query boş döner (hata vermez, güvenli).
- Rollback: `CustomerCareKnowledgeGroundingService`'i önceki commit'e geri al.
- Bu dalga AI cevap üretimi değil, sadece **veri hazırlama** katmanıdır; AI çağrısı yapmaz.

---

## 9. Güvenlik Özeti

- Cross-store leak: `store_id` filtresi ile engellendi.
- PII sızıntısı: `PiiRedactor` ile engellendi.
- Prompt injection: bağımsız keyword filter ile engellendi.
- Stale data: `has_stale_data=true` flag'i `CustomerCareAiOrchestrator`'ı handoff'a yönlendirir.
