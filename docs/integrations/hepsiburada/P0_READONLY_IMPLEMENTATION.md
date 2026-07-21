# Hepsiburada P0 Salt-Okuma Entegrasyonu Geliştirme Raporu

> [!IMPORTANT]
> Tüm P0 salt-okuma geliştirmeleri local testler, SQL migrations, mock response fixtures ve tenant izolasyonu kontrolleriyle teknik olarak doğrulanmış, remote branch'e push edilmiştir.
> Geliştirmenin durum kodu: **`p0_readonly_complete_mock_verified`**

---

## 1. Doğrulanan Resmî API Endpoint'leri ve Metotlar

### P0.1 — Kategori Ağacı ve Referans Verileri
* **Connector Metodu:** `HepsiburadaConnector::getCategories`
* **Resmî Endpoint:** `GET product/api/categories/get-all-categories`
* **ZOLM Durumu:** `officially_verified, implemented_mock_verified`
* **İşlevi:** Hepsiburada kategori ağacını çekerek `MpCategory` tablosuna unique upsert uygular.
* **Test Senaryosu:** `tests/Feature/Hepsiburada/HepsiburadaReferenceSyncTest.php::test_it_syncs_categories`

### P0.2 — Kategori Özellikleri ve Değer Sözlüğü
* **Connector Metodu:** `HepsiburadaConnector::getCategoryAttributes`
* **Resmî Endpoint:** `GET product/api/categories/{categoryId}/attributes`
* **ZOLM Durumu:** `officially_verified, implemented_mock_verified`
* **İşlevi:** Yaprak (leaf) kategoriler taranarak zorunlu/seçimli özellikler ve olası değerleri çekilir, `MpCategoryAttribute` ve `MpCategoryAttributeValue` tablolarına idempotent şekilde kaydedilir. Eski nitelik ve değerler otomatik silinerek (`delete`) reconcile edilir.
* **Test Senaryosu:** `tests/Feature/Hepsiburada/HepsiburadaReferenceSyncTest.php::test_it_syncs_category_attributes`

### P0.3 — Tam Katalog Ürün Çekimi (Katalog vs. Listing Ayrımı)
* **Connector Metodu:** `HepsiburadaConnector::pullCatalogProducts`
* **Resmî Endpoint:** `GET product/api/products/all-products-of-merchant/{merchantId}`
* **ZOLM Durumu:** `officially_verified, implemented_mock_verified`
* **İşlevi:** Ürün açıklaması, görsel listesi (images array), kategori özellikleri (attributes), onay statüsü (approval_status) ve red gerekçeleri (rejection_reasons) normalize edilir ve `ChannelProduct` tablosundaki yeni P0 katalog alanlarına kaydedilir.
* **Test Senaryosu:** `tests/Feature/Hepsiburada/HepsiburadaCatalogProductsTest.php`

### P0.4 — Batch İşlem Sonuç Takibi
* **Connector Metodu:** `HepsiburadaConnector::pullBatchStatus`
* **Resmî Endpointler:** 
  - `GET listings/merchantid/{merchantId}/price-uploads/id/{batchId}`
  - `GET listings/merchantid/{merchantId}/stock-uploads/id/{batchId}`
* **ZOLM Durumu:** `officially_verified, implemented_mock_verified`
* **İşlevi:** Toplu fiyat veya stok güncellemelerinin Hepsiburada kuyruğundaki durumlarını sorgular, tamamen salt-okumadır.
* **Test Senaryosu:** `tests/Feature/Hepsiburada/HepsiburadaBatchStatusTest.php`

---

## 2. Doğrulanamayan ve Devre Dışı Bırakılan Tahmini Endpoint'ler

Resmî Hepsiburada dokümanlarında `/packages/...` altında bir endpoint karşılığı bulunmayan veya tahmin yoluyla üretilmiş aşağıdaki durumlar için capabilities `false` set edilmiş, connector çağrıları temizlenmiş ve sync akışında engellenmiştir:
* **Prepared paket endpoint'i** (`packages/.../prepared`) -> `not_verified, not_implemented`
* **Split paket endpoint'i** (`packages/.../split`) -> `not_verified, not_implemented`
* **Cancelled paket endpoint'i** (`packages/.../cancelled`) -> `not_verified, not_implemented`
* **Unpaid/ödeme bekleyen sipariş endpoint'i** (`packages/.../unpaid`) -> `not_verified, not_implemented`

---

## 3. Tenant İzolasyonu ve Güvenlik Güvenceleri

* **Sızıntı Koruması:** `store_id` ve `legal_entity_id` zorunlulukları hem veritabanında hem de normalizasyon aşamasında korunur. Store A, Store B'ye ait hiçbir credential veya sipariş verisine erişemez (`HepsiburadaTenantIsolationTest`).
* **Maskeleme:** Basic Auth header'ları, API key ve müşteri kişisel verileri loglardan arındırılmıştır.
