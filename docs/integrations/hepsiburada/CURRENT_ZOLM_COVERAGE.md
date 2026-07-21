# ZOLM Hepsiburada Mevcut Entegrasyon Kapsamı ve Analizi

Bu doküman, ZOLM'ün Hepsiburada entegrasyonundaki 20 ana başlığı, ilişkili connector metotlarını, resmî endpoint durumlarını ve normalizasyon yapılarını detaylandırır.

---

## 1. Authentication
* **Connector Metodu:** `resolveAuth` ve `request`
* **ZOLM Durumu:** `officially_verified, implemented_mock_verified`
* **Resmî Endpoint:** Basic Auth yapısı tüm endpoint'lerde kullanılır.
* **Tenant İzolasyonu:** Store bazlı `resolveAuth` çözümlenerek cross-tenant sızıntıları engellenir. Log ve istisnalarda Service Key / Password maskelenir.

---

## 2. Kategori
* **Connector Metodu:** `getCategories`
* **ZOLM Durumu:** `officially_verified, implemented_mock_verified`
* **Resmî Endpoint:** `GET product/api/categories/get-all-categories`
* **İşlevi:** Kategori ağacı çekilerek `MpCategory` tablosuna unique upsert yapılır.

---

## 3. Kategori Özellikleri (Attributes)
* **Connector Metodu:** `getCategoryAttributes`
* **ZOLM Durumu:** `officially_verified, implemented_mock_verified`
* **Resmî Endpoint:** `GET product/api/categories/{id}/attributes`
* **İşlevi:** `MpCategoryAttribute` ve `MpCategoryAttributeValue` tablolarına upsert edilir. Reconciliation adımıyla eski nitelikler güvenli şekilde temizlenir.

---

## 4. Marka/Referans Verileri
* **Connector Metodu:** `getBrands`
* **ZOLM Durumu:** `not_implemented` (Hepsiburada marka listesi API'si doğrulanamadığı için `reference_brands_pull` capability `false` yapılmış ve connector çağrısı devre dışı bırakılmıştır).

---

## 5. Tam Katalog
* **Connector Metodu:** `pullCatalogProducts`
* **ZOLM Durumu:** `officially_verified, implemented_mock_verified`
* **Resmî Endpoint:** `GET product/api/products/all-products-of-merchant/{merchantId}`
* **İşlevi:** Ürün açıklaması, görsel listesi (images array), kategori özellikleri (attributes), onay statüsü (approval_status) ve red gerekçeleri (rejection_reasons) normalize edilir ve `ChannelProduct` tablosuna kaydedilir.
* **Tenant İzolasyonu:** `store_id` ile sınırlıdır, Store A Store B'nin katalog verisini göremez.

---

## 6. Listing
* **Connector Metodu:** `pullProducts`
* **ZOLM Durumu:** `officially_verified, implemented_mock_verified`
* **Resmî Endpoint:** `GET listings/merchantid/{merchantId}`
* **İşlevi:** Fiyat, stok ve aktiflik durumları `ChannelListing` tablosunda güncellenir.

---

## 7. Fiyat
* **Connector Metodu:** `pushPrice`
* **ZOLM Durumu:** `officially_verified, implemented_mock_verified`
* **Resmî Endpoint:** `POST listings/merchantid/{merchantId}/price-uploads` (XML payload)

---

## 8. Stok
* **Connector Metodu:** `pushStock`
* **ZOLM Durumu:** `officially_verified, implemented_mock_verified`
* **Resmî Endpoint:** `POST listings/merchantid/{merchantId}/stock-uploads` (XML payload)

---

## 9. Sipariş
* **Connector Metodu:** `pullOrders`
* **ZOLM Durumu:** `officially_verified, implemented_mock_verified`
* **Resmî Endpoint:** `GET packages/merchantid/{merchantId}` ve detay için `GET orders/merchantid/{merchantId}/ordernumber/{orderNumber}`

---

## 10. Paket
* **Connector Metodu:** `pullOrders` içindeki paket normalizasyon zinciri
* **ZOLM Durumu:** `officially_verified, implemented_mock_verified`
* **Resmî Endpoint'ler:** 
  - `GET packages/merchantid/{merchantId}` (Open)
  - `GET packages/merchantid/{merchantId}/shipped` (Shipped)
  - `GET packages/merchantid/{merchantId}/delivered` (Delivered)
  - `GET packages/merchantid/{merchantId}/undelivered` (Undelivered)
  - `GET packages/merchantid/{merchantId}/missing-invoice` (MissingInvoice)

---

## 11. Kargo
* **ZOLM Durumu:** `officially_verified, implemented_mock_verified`
* **API Alanları:** `cargoCompany`, `trackingNumber`, `barcode`, `desi`

---

## 12. Etiket
* **Connector Metodu:** `getCommonLabel` ve `createCommonLabel`
* **ZOLM Durumu:** `officially_verified, implemented_mock_verified`
* **Resmî Endpoint:** `GET packages/merchantid/{merchantId}/packagenumber/{packageNumber}/labels`

---

## 13. Finans
* **Connector Metodu:** `pullFinancialEvents`
* **ZOLM Durumu:** `officially_verified, implemented_mock_verified`
* **Resmî Endpoint:** `GET transactions/merchantid/{merchantId}`

---

## 14. E-Fatura
* **ZOLM Durumu:** `not_implemented` (E-Fatura / E-Arşiv mükellef sorgu ve fatura linki gönderme kodları capabilities false yapıldığı için devre dışıdır).

---

## 15. Sorular
* **Connector Metodu:** `pullCustomerQuestions` ve `answerCustomerQuestion`
* **ZOLM Durumu:** `officially_verified, implemented_mock_verified`
* **Resmî Endpoint:** `GET issues` ve `POST issues/{issueId}/answer`

---

## 16. İadeler
* **Connector Metodu:** `pullClaims`, `approveClaim`, `rejectClaim`
* **ZOLM Durumu:** `officially_verified, implemented_mock_verified`
* **Resmî Endpoint:** `GET claims/merchantid/{merchantId}`, `POST claims/number/{claimNumber}/accept`, `POST claims/number/{claimNumber}/reject`

---

## 17. Kampanyalar
* **ZOLM Durumu:** `not_implemented`

---

## 18. Webhook
* **ZOLM Durumu:** `not_implemented` (`webhooks => false`)

---

## 19. Ticket
* **ZOLM Durumu:** `not_implemented`

---

## 20. Tedarikçi Entegrasyonu
* **ZOLM Durumu:** `not_implemented`
