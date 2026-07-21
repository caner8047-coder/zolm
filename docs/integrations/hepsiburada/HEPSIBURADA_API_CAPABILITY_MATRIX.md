# Hepsiburada API Kapsam ve Yetenek Matrisi

> [!NOTE]
> Durumlar aşağıdaki tek tip sözlüğe uygun olarak işaretlenmiştir:
> - `officially_verified`: Resmî referans sayfası ve tam endpoint kanıtı bulunuyor.
> - `implemented_mock_verified`: Connector implementasyonu yapılmış ve mock testlerle doğrulanmıştır.
> - `implemented_not_wired`: Connector'da metod var fakat ana senkronizasyon zincirine bağlı değil.
> - `not_verified`: Resmî dokümantasyonda birebir doğrulanamamış veya test edilmemiş alanlar.
> - `not_implemented`: ZOLM üzerinde henüz kod karşılığı olmayan alanlar.
> - `live_verification_required`: Canlı mağaza bağlantısı ve gerçek credentials gerektiren durumlar.

| Alan | Operasyon | HTTP | Resmî endpoint | Read/Write | Auth | Pagination | Tarih filtresi | Ana alanlar | ZOLM desteği | Kod kanıtı | Test | Eksik | Öncelik |
| ---- | --------- | ---- | -------------- | ---------- | ---- | ---------- | -------------- | ----------- | ------------ | ---------- | ---- | ----- | ------- |
| Bağlantı | Test Connection | GET | `listings/merchantid/{id}` | Read | Basic Auth | Limit/Offset | Yok | totalCount | officially_verified, implemented_mock_verified | `HepsiburadaConnector::testConnection` | Evet | Yok | P0 |
| Kategori | Kategori Ağacı | GET | `product/api/categories/get-all-categories` | Read | Basic Auth | Yok | Yok | id, name, subCategories | officially_verified, implemented_mock_verified | `HepsiburadaConnector::getCategories` | Evet | Yok | P0 |
| Kategori | Özellikler | GET | `product/api/categories/{id}/attributes` | Read | Basic Auth | Yok | Yok | id, name, values | officially_verified, implemented_mock_verified | `HepsiburadaConnector::getCategoryAttributes` | Evet | Yok | P0 |
| Marka | Marka Sorgulama | GET | (Resmî API doğrulanmadı) | Read | Basic Auth | Yok | Yok | - | not_implemented | Yok | Hayır | Marka API | P1 |
| Listing | Listing Sorgulama | GET | `listings/merchantid/{id}` | Read | Basic Auth | Limit/Offset | Yok | price, availableStock | officially_verified, implemented_mock_verified | `HepsiburadaConnector::pullProducts` | Evet | Yok | P0 |
| Katalog | Ürün Sorgulama | GET | `product/api/products/all-products-of-merchant/{id}` | Read | Basic Auth | Limit/Offset | Yok | title, description, images, attributes | officially_verified, implemented_mock_verified | `HepsiburadaConnector::pullCatalogProducts` | Evet | Yok | P0 |
| Fiyat | Fiyat Gönder | POST | `listings/merchantid/{id}/price-uploads` | Write | Basic Auth | Yok | Yok | HepsiburadaSku, Price | officially_verified, implemented_mock_verified | `HepsiburadaConnector::pushPrice` | Evet | Yok | P0 |
| Stok | Stok Gönder | POST | `listings/merchantid/{id}/stock-uploads` | Write | Basic Auth | Yok | Yok | HepsiburadaSku, AvailableStock | officially_verified, implemented_mock_verified | `HepsiburadaConnector::pushStock` | Evet | Yok | P0 |
| Batch | Batch Durumu | GET | `listings/merchantid/{id}/{op}/id/{batchId}` | Read | Basic Auth | Yok | Yok | status, successCount, failureCount | officially_verified, implemented_mock_verified | `HepsiburadaConnector::pullBatchStatus` | Evet | Yok | P0 |
| Sipariş | Sipariş Çek | GET | `packages/merchantid/{id}/*` | Read | Basic Auth | Limit/Offset | beginDate/endDate | orderNumber, customerName | officially_verified, implemented_mock_verified | `HepsiburadaConnector::pullOrders` | Evet | Yok | P0 |
| Paket | Açık Paketler | GET | `packages/merchantid/{id}` | Read | Basic Auth | Limit/Offset | beginDate/endDate | status, packageNumber | officially_verified, implemented_mock_verified | `HepsiburadaConnector::packageEndpoints` | Evet | Yok | P0 |
| Paket | Kargolanan | GET | `packages/merchantid/{id}/shipped` | Read | Basic Auth | Limit/Offset | beginDate/endDate | status, trackingNumber | officially_verified, implemented_mock_verified | `HepsiburadaConnector::packageEndpoints` | Evet | Yok | P0 |
| Paket | Teslim Edilen | GET | `packages/merchantid/{id}/delivered` | Read | Basic Auth | Limit/Offset | beginDate/endDate | status, deliveredDate | officially_verified, implemented_mock_verified | `HepsiburadaConnector::packageEndpoints` | Evet | Yok | P0 |
| Paket | Teslim Edilemeyen| GET | `packages/merchantid/{id}/undelivered` | Read | Basic Auth | Limit/Offset | beginDate/endDate | status, reason | officially_verified, implemented_mock_verified | `HepsiburadaConnector::packageEndpoints` | Evet | Yok | P0 |
| Paket | Faturasız | GET | `packages/merchantid/{id}/missing-invoice`| Read | Basic Auth | Limit/Offset | beginDate/endDate | status | officially_verified, implemented_mock_verified | `HepsiburadaConnector::packageEndpoints` | Evet | Yok | P0 |
| Paket | Hazırlanan | GET | (Resmî API'de doğrulanmadı) | Read | Basic Auth | - | - | - | not_verified, not_implemented | Yok | Hayır | - | P1 |
| Paket | Bölünmüş | GET | (Resmî API'de doğrulanmadı) | Read | Basic Auth | - | - | - | not_verified, not_implemented | Yok | Hayır | - | P1 |
| Paket | İptal Edilen | GET | (Resmî API'de doğrulanmadı) | Read | Basic Auth | - | - | - | not_verified, not_implemented | Yok | Hayır | - | P1 |
| Sipariş | Ödeme Bekleyen | GET | (Resmî API'de doğrulanmadı) | Read | Basic Auth | - | - | - | not_verified, not_implemented | Yok | Hayır | - | P1 |
| Kargo | Barkod/Etiket | GET | `packages/merchantid/{id}/packagenumber/{no}/labels` | Read | Basic Auth | Yok | Yok | label_content, content_type | officially_verified, implemented_mock_verified | `HepsiburadaConnector::createCommonLabel` | Evet | Yok | P0 |
| Finans | İşlemler | GET | `transactions/merchantid/{id}` | Read | Basic Auth | Limit/Offset | beginDate/endDate | amount, transactionType, dueDate | officially_verified, implemented_mock_verified | `HepsiburadaConnector::pullFinancialEvents` | Evet | Yok | P0 |
| Soru | Soruları Çek | GET | `issues` | Read | Basic Auth | Limit/Offset | minModifiedAt/maxModifiedAt| issueNumber, question, asked_at | officially_verified, implemented_mock_verified | `HepsiburadaConnector::pullCustomerQuestions` | Evet | Yok | P0 |
| Soru | Soruyu Cevapla | POST | `issues/{id}/answer` | Write | Basic Auth | Yok | Yok | Answer | officially_verified, implemented_mock_verified | `HepsiburadaConnector::answerCustomerQuestion` | Evet | Yok | P0 |
| İade | İadeleri Çek | GET | `claims/merchantid/{id}` | Read | Basic Auth | Limit/Offset | beginDate/endDate | claimNumber, status, reason | officially_verified, implemented_mock_verified | `HepsiburadaConnector::pullClaims` | Evet | Yok | P0 |
| İade | İade Onayla | POST | `claims/number/{id}/accept` | Write | Basic Auth | Yok | Yok | payload | officially_verified, implemented_mock_verified | `HepsiburadaConnector::approveClaim` | Evet | Yok | P0 |
| İade | İade Reddet | POST | `claims/number/{id}/reject` | Write | Basic Auth | Yok | Yok | reason, description | officially_verified, implemented_mock_verified | `HepsiburadaConnector::rejectClaim` | Evet | Yok | P0 |
| E-Fatura | Mükellef Sorgu | GET | (Resmî API doğrulanmadı) | Read | Basic Auth | Yok | Yok | - | not_implemented | Yok | Hayır | E-Fatura API | P2 |
| Kampanya | Kampanyalar | GET | (Resmî API doğrulanmadı) | Read | Basic Auth | - | - | - | not_implemented | Yok | Hayır | Kampanya API | P2 |
| Webhook | Webhook Alımları | POST | (Kanal webhook callback URL) | Write | HMAC | - | - | - | not_implemented | Yok | Hayır | Webhook handler | P2 |
