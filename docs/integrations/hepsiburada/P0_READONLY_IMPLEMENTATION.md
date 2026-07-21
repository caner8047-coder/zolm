# Hepsiburada P0 Salt-Okuma Entegrasyonu Geliştirme Raporu

Bu doküman, `antigravity/hepsiburada-p0-readonly` branch'i kapsamında ZOLM sistemine eklenen yeni salt-okuma entegrasyon özelliklerini detaylandırır.

---

## 1. Geliştirilen Modüller ve Servis Katmanları

### P0.1 — Kategori Ağacı ve Referans Verileri
* **Connector Metodu:** `HepsiburadaConnector::getCategories` (tahmini endpoint `product/api/categories/get-all-categories`)
* **Veritabanı Entegrasyonu:** `MarketplaceReferenceSyncService::syncCategories` recursive şekilde kategori ağacını okur ve `MpCategory` tablosuna unique upsert uygular.
* **Test Dosyası:** `tests/Feature/Hepsiburada/HepsiburadaReferenceSyncTest.php::test_it_syncs_categories`

### P0.2 — Kategori Özellikleri ve Değer Sözlüğü
* **Connector Metodu:** `HepsiburadaConnector::getCategoryAttributes` (tahmini endpoint `product/api/categories/{id}/attributes`)
* **Veritabanı Entegrasyonu:** `MarketplaceReferenceSyncService::syncCategoryAttributes` metodu eklendi. Yaprak (leaf) kategoriler taranarak zorunlu/seçimli özellikler ve olası değerleri çekilir, `MpCategoryAttribute` ve `MpCategoryAttributeValue` tablolarına kaydedilir.
* **Reconciliation:** Kategori özellikleri değiştiğinde eski attribute ve değer ilişkileri `delete` edilerek veritabanında güncel tutulması sağlanır.
* **Test Dosyası:** `tests/Feature/Hepsiburada/HepsiburadaReferenceSyncTest.php::test_it_syncs_category_attributes`

### P0.3 — Tam Katalog Ürün Çekimi (Katalog vs. Listing Ayrımı)
* **Connector Metodu:** `HepsiburadaConnector::pullCatalogProducts` (tahmini endpoint `product/api/products/merchant/{merchantId}`)
* **Normalizasyon:** `normalizeCatalogProduct` metodu ile açıklama, görseller (images array), kategori özellikleri (attributes), onay statüsü (approval_status) ve red gerekçeleri (rejection_reasons) normalize edilir.
* **Veritabanı Entegrasyonu:** `MarketplaceSyncService` içine `catalog_products` senkronizasyon tipi entegre edildi. `MarketplaceCatalogSyncService::sync` metodu yeni katalog alanlarını `ChannelProduct` tablosuna nullable olarak yazar.
* **Test Dosyası:** `tests/Feature/Hepsiburada/HepsiburadaCatalogProductsTest.php`

### P0.4 — Batch İşlem Sonuç Takibi
* **Connector Metodu:** `HepsiburadaConnector::pullBatchStatus` (doğrulanmış endpoint `listings/merchantid/{merchantId}/{op}/id/{batchId}`)
* **Amacı:** Toplu fiyat veya stok güncelleme (pushPrice/pushStock) işlemlerinin Hepsiburada kuyruğundaki durumlarını (Completed, Failed vb.) sorgulamak için kullanılır. Tamamen salt-okumadır, veri mutasyonu yaratmaz.
* **Test Dosyası:** `tests/Feature/Hepsiburada/HepsiburadaBatchStatusTest.php`

### P0.5 — Paket Statü Kapsam Genişletmesi
* **Metot:** `HepsiburadaConnector::packageEndpoints` listesi genişletilerek `Prepared`, `Split`, `Cancelled` ve `Unpaid` (Ödeme bekleyen siparişler) endpoint'leri dahil edildi.
* **Önemi:** Aynı siparişin farklı endpoint'lerden mükerrer oluşması normalizasyondaki `orderNumber|packageId` unique anahtarı ile engellenmiştir.

---

## 2. Tenant İzolasyonu ve Güvenlik Güvenceleri

* **Cross-Tenant Sızıntı Koruması:** `store_id` tabanlı tenant izolasyonu `ChannelProduct`, `ChannelListing` ve `ChannelOrder` tablolarında unique anahtarlarla zorlanmıştır. Store A, Store B'nin credential veya sipariş verisine hiçbir koşulda erişemez.
* **Maskeleme:** Basic Auth header'ları, API key ve müşteri kişisel verileri loglardan arındırılmıştır.
* **Test Dosyası:** `tests/Feature/Hepsiburada/HepsiburadaTenantIsolationTest.php`
