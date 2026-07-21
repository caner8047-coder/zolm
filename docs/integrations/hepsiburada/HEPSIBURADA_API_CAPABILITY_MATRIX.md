# Hepsiburada API Kapsam ve Yetenek Matrisi

> [!NOTE]
> `not_verified` işaretli endpoint URL'leri, Hepsiburada developer portal SPA yapısında olduğundan statik taramayla alınamamış ve tahmini olarak connector kodundaki base URL desenlerine göre yazılmıştır.

| Alan | Operasyon | HTTP | Resmî endpoint | Read/Write | Auth | Pagination | Tarih filtresi | Ana alanlar | ZOLM desteği | Kod kanıtı | Test | Eksik | Öncelik |
| ---- | --------- | ---- | -------------- | ---------- | ---- | ---------- | -------------- | ----------- | ------------ | ---------- | ---- | ----- | ------- |
| Bağlantı | Test Connection | GET | `listings/merchantid/{id}` | Read | Basic Auth | Limit/Offset | Yok | totalCount | complete | `HepsiburadaConnector::testConnection` | Evet | Yok | P0 |
| Kategori | Kategori Ağacı | GET | `product/api/categories/get-all-categories` | Read | Basic Auth | Yok | Yok | id, name, subCategories | complete (`not_verified`) | `HepsiburadaConnector::getCategories` | Evet | Yok | P0 |
| Kategori | Özellikler | GET | `product/api/categories/{id}/attributes` | Read | Basic Auth | Yok | Yok | id, name, values | complete (`not_verified`) | `HepsiburadaConnector::getCategoryAttributes` | Evet | Yok | P0 |
| Marka | Marka Sorgulama | GET | `product/api/brands` (tahmini) | Read | Basic Auth | Page/Size | Yok | id, name | not_implemented (`not_verified`) | Yok | Hayır | Marka API | P1 |
| Listing | Listing Sorgulama | GET | `listings/merchantid/{id}` | Read | Basic Auth | Limit/Offset | Yok | price, availableStock | complete | `HepsiburadaConnector::pullProducts` | Evet | Yok | P0 |
| Katalog | Ürün Sorgulama | GET | `product/api/products/merchant/{id}` | Read | Basic Auth | Limit/Offset | Yok | title, description, images, attributes | complete (`not_verified`) | `HepsiburadaConnector::pullCatalogProducts` | Evet | Yok | P0 |
| Fiyat | Fiyat Gönder | POST | `listings/merchantid/{id}/price-uploads` | Write | Basic Auth | Yok | Yok | HepsiburadaSku, Price | complete | `HepsiburadaConnector::pushPrice` | Evet | Yok | P0 |
| Stok | Stok Gönder | POST | `listings/merchantid/{id}/stock-uploads` | Write | Basic Auth | Yok | Yok | HepsiburadaSku, AvailableStock | complete | `HepsiburadaConnector::pushStock` | Evet | Yok | P0 |
| Batch | Batch Durumu | GET | `listings/merchantid/{id}/{op}/id/{batchId}` | Read | Basic Auth | Yok | Yok | status, successCount, failureCount | complete | `HepsiburadaConnector::pullBatchStatus` | Evet | Yok | P0 |
| Sipariş | Sipariş Çek | GET | `packages/merchantid/{id}/*` | Read | Basic Auth | Limit/Offset | beginDate/endDate | orderNumber, customerName | partial | `HepsiburadaConnector::pullOrders` | Evet | Ayrı Unpaid endpoint | P0 |
| Paket | Açık Paketler | GET | `packages/merchantid/{id}` | Read | Basic Auth | Limit/Offset | beginDate/endDate | status, packageNumber | complete | `HepsiburadaConnector::packageEndpoints` | Evet | Yok | P0 |
| Paket | Kargolanan | GET | `packages/merchantid/{id}/shipped` | Read | Basic Auth | Limit/Offset | beginDate/endDate | status, trackingNumber | complete | `HepsiburadaConnector::packageEndpoints` | Evet | Yok | P0 |
| Paket | Teslim Edilen | GET | `packages/merchantid/{id}/delivered` | Read | Basic Auth | Limit/Offset | beginDate/endDate | status, deliveredDate | complete | `HepsiburadaConnector::packageEndpoints` | Evet | Yok | P0 |
| Paket | Teslim Edilemeyen| GET | `packages/merchantid/{id}/undelivered` | Read | Basic Auth | Limit/Offset | beginDate/endDate | status, reason | complete | `HepsiburadaConnector::packageEndpoints` | Evet | Yok | P0 |
| Paket | Faturasız | GET | `packages/merchantid/{id}/missing-invoice`| Read | Basic Auth | Limit/Offset | beginDate/endDate | status | complete | `HepsiburadaConnector::packageEndpoints` | Evet | Yok | P0 |
| Paket | Hazırlanan | GET | `packages/merchantid/{id}/prepared` | Read | Basic Auth | Limit/Offset | beginDate/endDate | status | complete (`not_verified`) | `HepsiburadaConnector::packageEndpoints` | Evet | Yok | P0 |
| Paket | Bölünmüş | GET | `packages/merchantid/{id}/split` | Read | Basic Auth | Limit/Offset | beginDate/endDate | status | complete (`not_verified`) | `HepsiburadaConnector::packageEndpoints` | Evet | Yok | P0 |
| Paket | İptal Edilen | GET | `packages/merchantid/{id}/cancelled` | Read | Basic Auth | Limit/Offset | beginDate/endDate | status | complete (`not_verified`) | `HepsiburadaConnector::packageEndpoints` | Evet | Yok | P0 |
| Sipariş | Ödeme Bekleyen | GET | `packages/merchantid/{id}/unpaid` | Read | Basic Auth | Limit/Offset | beginDate/endDate | status | complete (`not_verified`) | `HepsiburadaConnector::packageEndpoints` | Evet | Yok | P0 |
| Kargo | Barkod/Etiket | GET | `packages/merchantid/{id}/packagenumber/{no}/labels` | Read | Basic Auth | Yok | Yok | label_content, content_type | complete | `HepsiburadaConnector::createCommonLabel` | Evet | Yok | P0 |
| Finans | İşlemler | GET | `transactions/merchantid/{id}` | Read | Basic Auth | Limit/Offset | beginDate/endDate | amount, transactionType, dueDate | complete | `HepsiburadaConnector::pullFinancialEvents` | Evet | Yok | P0 |
| Soru | Soruları Çek | GET | `issues` | Read | Basic Auth | Limit/Offset | minModifiedAt/maxModifiedAt| issueNumber, question, asked_at | complete | `HepsiburadaConnector::pullCustomerQuestions` | Evet | Yok | P0 |
| Soru | Soruyu Cevapla | POST | `issues/{id}/answer` | Write | Basic Auth | Yok | Yok | Answer | complete | `HepsiburadaConnector::answerCustomerQuestion` | Evet | Yok | P0 |
| İade | İadeleri Çek | GET | `claims/merchantid/{id}` | Read | Basic Auth | Limit/Offset | beginDate/endDate | claimNumber, status, reason | complete | `HepsiburadaConnector::pullClaims` | Evet | Yok | P0 |
| İade | İade Onayla | POST | `claims/number/{id}/accept` | Write | Basic Auth | Yok | Yok | payload | complete | `HepsiburadaConnector::approveClaim` | Evet | Yok | P0 |
| İade | İade Reddet | POST | `claims/number/{id}/reject` | Write | Basic Auth | Yok | Yok | reason, description | complete | `HepsiburadaConnector::rejectClaim` | Evet | Yok | P0 |
| E-Fatura | Mükellef Sorgu | GET | `e-invoice/taxpayer-query` (tahmini)| Read | Basic Auth | Yok | Yok | taxpayerStatus | not_implemented (`not_verified`) | Yok | Hayır | E-Fatura API | P2 |
| Kampanya | Kampanyalar | GET | `campaigns` (tahmini) | Read | Basic Auth | Limit/Offset | Yok | campaignId, name, dates | not_implemented (`not_verified`) | Yok | Hayır | Kampanya API | P2 |
| Webhook | Webhook Alımları | POST | (Kanal webhook callback URL) | Write | HMAC | Yok | Yok | eventType, payload | not_implemented | Yok | Hayır | Webhook handler | P2 |
