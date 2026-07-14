# Dalga AE — Kanıt Paketi
## Kalite Denetim Merkezi, Skor Kartları ve Agent Coaching

**Tarih:** 2026-07-13  
**Durum:** Kalite Kapısı 01 Revizyonu Tamamlandı

---

## 1. Amaç

Dalga AE; yapay zeka ve temsilci yanıtlarının kalitesini denetleyen, skor kartları üreten ve PII/Cross-Store güvenlik korumalarını içeren bir kalite güvence arayüzü sunar.

---

## 2. Değişen Dosyalar

| Dosya | Değişiklik |
|---|---|
| [`QualityCenter.php`](../../../app/Livewire/CustomerCare/QualityCenter.php) | P0-4: `selectItem` ve `submitReview` store-scoped resolver, PII maskelemeli preview ve redacted KB suggestion |
| [`CustomerCareQualityTest.php`](../../../tests/Feature/CustomerCare/CustomerCareQualityTest.php) | 10 test (route, cross-store check, PII masking, sample reviews, golden dataset) |
| [`routes/web.php`](../../../routes/web.php) | P0-1: `/customer-care/quality` rotası `customer-care.feature:quality_center_enabled` middleware'ine bağlandı |
| [`config/customer-care.php`](../../../config/customer-care.php) | P0-1: `quality_center_enabled` özellik bayrağı eklendi |

---

## 3. Migration / Rollback Kanıtı

Bu dalga için `2026_07_30_100000_create_quality_reviews_tables.php` tablosu oluşturulmuştur.

### Migration Up:
```bash
php artisan migrate
# support_quality_reviews ve support_quality_review_items tabloları oluşturuldu.
```

### Rollback:
```bash
php artisan migrate:rollback --path=database/migrations/2026_07_30_100000_create_quality_reviews_tables.php
# Tablolar başarıyla kaldırıldı.
```

---

## 4. Test İsimleri (10 test / 22 assertion)

```
✓ admin_can_submit_quality_review_with_scores
✓ non_admin_cannot_access_quality_review
✓ pii_masked_in_feedback_and_comments
✓ golden_candidate_review_does_not_change_live_dataset
✓ sample_command_in_dry_run_does_not_persist
✓ quality_route_blocks_when_flag_off
✓ quality_center_prevents_selecting_cross_store_items
✓ quality_center_prevents_submitting_cross_store_reviews
✓ quality_center_kb_candidate_redacts_pii_in_proposed_answer
✓ sample_command_with_execute_creates_pending_reviews
```

---

## 5. Feature Flag Varsayılanları

| Flag | ENV | Varsayılan |
|---|---|---|
| `quality_center_enabled` | `CUSTOMER_CARE_QUALITY_CENTER_ENABLED` | `false` |

---

## 6. Güvenlik ve Kalite Kapısı Düzeltmeleri (P0-4)

### P0-4 ✅ — Quality Center PII ve Tenant Sınırları
- **Store-Scoped Resolver:** `selectItem()` ve `submitReview()` metodları seçilen `SupportAiRun` veya `SupportMessage` kaydının `selectedStoreId` ile eşleştiğini DB sorgusunda doğrular. Farklı store'a ait verilerin seçilmesi veya güncellenmesi engellenmiştir.
- **Preview PII Masking:** Preview alanındaki prompt, response ve mesaj içerikleri `PiiRedactor::maskPii()` ile maskelenerek temsilci/admin ekranında ham verilerin görünmesi engellenmiştir.
- **redacted proposed_answer:** `kb_candidate` kararı alındığında oluşturulan bilgi bankası makale önerisinin (`SupportKnowledgeSuggestion`) gövdesi ve başlığı tamamen maskelenerek kaydedilmektedir.
- **Unique Hash Key:** Bilgi önerilerinde mükerrer kayıtları önlemek için unique `hash_key` alanı başarıyla set edilmiştir.
