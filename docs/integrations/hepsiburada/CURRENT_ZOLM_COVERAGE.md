# ZOLM Hepsiburada Mevcut Entegrasyon Kapsamı ve Analizi

Bu doküman ZOLM'ün Hepsiburada entegrasyonundaki 20 ana başlığı, ilişkili connector metotlarını, normalize edilen alanları, veritabanına kayıt yapılarını, hata yönetimi politikalarını ve test kapsamını detaylandırır.

---

## 1. Authentication
* **Connector Metodu:** `resolveAuth(MarketplaceStore $store)` ve `request(MarketplaceStore $store, string $service)`
* **Kullanılan Endpoint:** Basic Auth yapısı tüm endpoint'lerde kullanılır.
* **API'den Gelen Alanlar:** Merchant ID, Service Key. Eski credentials için extra_user ve extra_password desteği.
* **Normalize Edilen / DB'ye Yazılan:** `credentials_encrypted` alanı `IntegrationConnection` modelinde AES şifreli olarak saklanır.
* **Tenant İzolasyonu:** Store bazlı `resolveAuth` çözümlenerek cross-tenant sızıntıları engellenir.
* **Güvenlik/Maskeleme:** Log ve istisnalarda Service Key / Password / Authorization header maskelenir.
* **Test Kapsamı:** `HepsiburadaConnectorTest::test_it_uses_merchant_id_and_service_key_auth` ve `test_it_falls_back_to_legacy_basic_auth_when_service_key_is_missing`

---

## 2. Kategori
* **Connector Metodu:** `getCategories(MarketplaceStore $store, array $options = [])`
* **Kullanılan Endpoint:** `product/api/categories/get-all-categories` (`not_verified`)
* **API'den Gelen Alanlar:** `id`, `name`, `subCategories`
* **Normalize Edilen / DB'ye Yazılan:** `MpCategory` tablosuna `platform_category_id`, `name`, `parent_id`, `level`, `is_leaf`, `is_active` kaydedilir.
* **Tenant İzolasyonu:** Kategori verisi tenant-independent referans verisidir.
* **Pagination:** Ağaç yapısı şeklinde tek seferde çekilir veya recursive işlenir.
* **Idempotency:** Unique `[marketplace, platform_category_id]` bazlı upsert.
* **Test Kapsamı:** `HepsiburadaReferenceSyncTest::test_it_syncs_categories`

---

## 3. Kategori Özellikleri (Attributes)
* **Connector Metodu:** `getCategoryAttributes(MarketplaceStore $store, string $categoryId, array $options = [])`
* **Kullanılan Endpoint:** `product/api/categories/{id}/attributes` (`not_verified`)
* **API'den Gelen Alanlar:** `id`, `name`, `mandatory`, `varianter`, `multipleSelect`, `allowedDataType`, `attributeValues`
* **Normalize Edilen / DB'ye Yazılan:** `MpCategoryAttribute` ve `MpCategoryAttributeValue` tablolarına upsert edilir.
* **Reconciliation:** Kategori attribute'ları değiştiğinde eski attribute ve değer kayıtları veritabanından güvenli bir şekilde silinir (`delete`).
* **Test Kapsamı:** `HepsiburadaReferenceSyncTest::test_it_syncs_category_attributes`

---

## 4. Marka/Referans Verileri
* **Connector Metodu:** `getBrands(MarketplaceStore $store, int $page = 0, int $size = 500)`
* **Durum:** `not_available_via_api`. Hepsiburada portal API'lerinde genel marka listesi endpoint'i bulunamadığından capabilities'de false işaretlenmiş ve sync akışında skipped kalmıştır.

---

## 5. Tam Katalog
* **Connector Metodu:** `pullCatalogProducts(MarketplaceStore $store, array $options = [])`
* **Kullanılan Endpoint:** `product/api/products/merchant/{id}` (`not_verified`)
* **API'den Gelen Alanlar:** `hepsiburadaSku`, `merchantSku`, `barcode`, `productName`, `description`, `brand`, `categoryName`, `images`, `attributes`, `productStatus`
* **Normalize Edilen / DB'ye Yazılan:** `ChannelProduct` tablosunda `description`, `images`, `attributes`, `approval_status`, `rejection_reasons` alanlarına işlenir.
* **Tenant İzolasyonu:** `store_id` ile sınırlıdır, Store A Store B'nin katalog verisini göremez.
* **Test Kapsamı:** `HepsiburadaCatalogProductsTest::test_it_syncs_catalog_products_successfully`

---

## 6. Listing
* **Connector Metodu:** `pullProducts(MarketplaceStore $store, array $options = [])`
* **Kullanılan Endpoint:** `listings/merchantid/{id}`
* **API'den Gelen Alanlar:** `merchantSku`, `hepsiburadaSku`, `barcode`, `price`, `availableStock`, `isActive`
* **Normalize Edilen / DB'ye Yazılan:** `ChannelListing` tablosunda fiyat, stok, aktiflik durumu güncellenir.
* **Test Kapsamı:** `HepsiburadaConnectorTest::test_it_normalizes_listing_payloads`

---

## 7. Fiyat
* **Connector Metodu:** `pushPrice(ChannelListing $listing, float $price, array $context = [])`
* **Kullanılan Endpoint:** `listings/merchantid/{id}/price-uploads` (POST XML)
* **API'den Gelen Alanlar:** `Id` (batch request id)
* **Normalize Edilen / DB'ye Yazılan:** XML formatında gönderim yapılır, dönüşte batch ID `IntegrationPushRun` veya response logs'ta tutulur.
* **Test Kapsamı:** `HepsiburadaConnectorTest::test_it_pushes_price_with_xml_payload`

---

## 8. Stok
* **Connector Metodu:** `pushStock(ChannelListing $listing, int $quantity, array $context = [])`
* **Kullanılan Endpoint:** `listings/merchantid/{id}/stock-uploads` (POST XML)
* **API'den Gelen Alanlar:** `Id` (batch request id)
* **Test Kapsamı:** `HepsiburadaConnectorTest::test_it_pushes_stock_with_xml_payload`

---

## 9. Sipariş
* **Connector Metodu:** `pullOrders(MarketplaceStore $store, array $options = [])`
* **Kullanılan Endpoint:** `packages/merchantid/{id}/*` ve detay için `orders/merchantid/{id}/ordernumber/{no}`
* **API'den Gelen Alanlar:** `orderNumber`, `customerName`, `customerEmail`, `shippingAddress`, `items`
* **Normalize Edilen / DB'ye Yazılan:** `ChannelOrder` ve `ChannelOrderItem` modellerine kaydedilir.
* **Tenant İzolasyonu:** `store_id` tabanlı sorgu izolasyonu mevcuttur.
* **Test Kapsamı:** `HepsiburadaConnectorTest::test_it_normalizes_package_based_orders`

---

## 10. Paket
* **Connector Metodu:** `pullOrders` içinde paket normalization zinciri
* **Kullanılan Endpoint:** `packages/merchantid/{id}` ve statü bazlı alt endpointler (Shipped, Delivered, Undelivered, MissingInvoice, Prepared, Split, Cancelled, Unpaid)
* **Normalize Edilen / DB'ye Yazılan:** `ChannelOrderPackage` tablosuna external_package_id, status ve kargo bilgileri yazılır.

---

## 11. Kargo
* **API'den Gelen Alanlar:** `cargoCompany`, `trackingNumber`, `barcode`, `desi`
* **Normalize Edilen / DB'ye Yazılan:** `cargo_company`, `cargo_tracking_number`, `cargo_barcode`, `cargo_desi`
* **Test Kapsamı:** Paket çekimi testinde kargo firması ve takip numarası doğrulanmıştır.

---

## 12. Etiket
* **Connector Metodu:** `getCommonLabel(ChannelOrderPackage $package, array $context)` ve `createCommonLabel`
* **Kullanılan Endpoint:** `packages/merchantid/{id}/packagenumber/{no}/labels`
* **API'den Gelen Alanlar:** Binary veri (ZPL, PDF, veya Plain Text)
* **Normalize Edilen / DB'ye Yazılan:** Content-Type'a göre ZPL text veya Base64 binary olarak normalize edilir.
* **Test Kapsamı:** `HepsiburadaConnectorTest::test_it_fetches_common_label_with_safe_response_payload` ve `test_it_uses_same_official_endpoint_for_common_label_create`

---

## 13. Finans
* **Connector Metodu:** `pullFinancialEvents(MarketplaceStore $store, array $options = [])`
* **Kullanılan Endpoint:** `transactions/merchantid/{id}`
* **API'den Gelen Alanlar:** `id`, `transactionType`, `orderNumber`, `packageNumber`, `amount`, `currency`, `transactionDate`, `dueDate`, `paymentDate`
* **Normalize Edilen / DB'ye Yazılan:** `OrderFinancialEvent` tablosunda commission, cargo, withholding, service_fee, seller_revenue gruplarına atanır. Brüt/net tutarlar kaydedilir.
* **Test Kapsamı:** `HepsiburadaConnectorTest::test_it_normalizes_financial_transactions`

---

## 14. E-Fatura
* **Durum:** `not_implemented`. E-Fatura / E-Arşiv mükellef sorgulama ve fatura linki servisleri capabilities'de false'tur ve ZOLM connector'ında kod karşılığı yoktur.

---

## 15. Sorular
* **Connector Metodu:** `pullCustomerQuestions(MarketplaceStore $store, array $options = [])` ve `answerCustomerQuestion`
* **Kullanılan Endpoint:** `issues` ve `issues/{id}/answer`
* **API'den Gelen Alanlar:** `issueNumber`, `question`, `asked_at`, `expires_at`
* **Normalize Edilen / DB'ye Yazılan:** `MarketplaceQuestion` tablosuna soru metni, tipi (ürün/sipariş), ve ürün SKU'su kaydedilir.

---

## 16. İadeler
* **Connector Metodu:** `pullClaims(MarketplaceStore $store, array $options = [])`, `approveClaim`, `rejectClaim`
* **Kullanılan Endpoint:** `claims/merchantid/{id}`, `claims/number/{id}/accept`, `claims/number/{id}/reject`
* **API'den Gelen Alanlar:** `claimNumber`, `status`, `reason`, `customerNote`, `customerName`, `items`
* **Normalize Edilen / DB'ye Yazılan:** `ChannelClaim` ve `ChannelClaimItem` tablolarına işlenir.

---

## 17. Kampanyalar
* **Durum:** `not_implemented`. Hepsiburada Kampanya/Promosyon katılım ve performans API'leri için ZOLM üzerinde herhangi bir entegrasyon bulunmamaktadır.

---

## 18. Webhook
* **Durum:** `not_implemented` (`webhooks => false`). Sipariş veya paket değişiklik webhook altyapısı Hepsiburada connector'ı üzerinde tanımlı değildir.

---

## 19. Ticket
* **Durum:** `not_implemented`. Hepsiburada entegrasyon destek ticket yönetimi API'si mevcut değildir.

---

## 20. Tedarikçi Entegrasyonu
* **Durum:** `not_implemented`. Tedarik, sipariş karşılama veya B2B entegrasyonu Hepsiburada connector'ında yer almamaktadır.
