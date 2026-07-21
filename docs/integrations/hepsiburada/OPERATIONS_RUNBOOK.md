# Hepsiburada Entegrasyonu Operasyon Kılavuzu (Runbook)

Bu kılavuz, ZOLM yöneticileri ve operasyon ekipleri için Hepsiburada API entegrasyonunun elle tetiklenmesi, backfill yapılması, hata kodlarının yorumlanması ve rate-limit sorunlarının giderilmesini anlatır.

---

## 1. Manuel Senkronizasyon Komutları

### 1.1 Sipariş Senkronu
Mağazanın tüm siparişlerini ve paketlerini belirli bir tarih aralığında çekmek için:
```bash
php artisan marketplace:dispatch-sync {store_id} orders --start="2026-03-20T00:00:00+03:00" --end="2026-03-21T00:00:00+03:00"
```

### 1.2 Ürün ve Fiyat/Stok Listing Senkronu
Mağazanın güncel fiyat, stok ve listing durumlarını çekmek için:
```bash
php artisan marketplace:dispatch-sync {store_id} products
```

### 1.3 Tam Katalog Senkronu (P0 Özelliği)
Mağazanın tam ürün içeriğini (açıklamalar, görseller, özellikler, onay statüsü) çekmek için:
```bash
php artisan marketplace:dispatch-sync {store_id} catalog_products
```

### 1.4 Finansal Hareketler Senkronu
Mağazanın hakediş, komisyon, kargo kesintileri vb. finans hareketlerini çekmek için:
```bash
php artisan marketplace:dispatch-sync {store_id} finance --start="2026-03-01T00:00:00+03:00" --end="2026-03-31T00:00:00+03:00"
```

### 1.5 Referans Veri Senkronu (Kategori, Özellik ve Markalar)
Kategori ağacını ve kategori özelliklerini (attribute sözlüğü) güncellemek için `SyncMarketplaceReferenceJob` job'ı veya sistem scheduler'ı tetiklenmelidir:
```php
\App\Jobs\SyncMarketplaceReferenceJob::dispatch($store);
```

---

## 2. Batch İşlem Sorgulama (Fiyat/Stok Yükleme Takibi)

Gönderilen toplu güncellemelerin sonucunu sorgulamak için `HepsiburadaConnector::pullBatchStatus` metodu kullanılabilir:
```php
$connector = app(\App\Services\Marketplace\MarketplaceConnectorManager::class)->resolve('hepsiburada');
$result = $connector->pullBatchStatus($store, 'batch-request-id', 'price-uploads');
// Dönen veri: success_count, failure_count, status ('Completed', 'Failed', 'InProgress')
```

---

## 3. Sık Karşılaşılan Hata Kodları ve Çözümleri

### 3.1 Yetkilendirme Hataları (401 Unauthorized / 403 Forbidden)
* **Neden:** `api_key` (Service Key) veya `seller_id` (Merchant ID) yanlış girilmiş ya da Hepsiburada paneli üzerinden entegrasyon yetkisi pasifleştirilmiş.
* **Aksiyon:** `IntegrationConnection` credential ayarlarını satıcı panelindeki entegrasyon bilgileriyle karşılaştırın.

### 3.2 Hız Sınırı Aşıldı (429 Too Many Requests)
* **Neden:** Hepsiburada API saniyede/dakikada izin verilen istek sınırını aştı.
* **Aksiyon:** `IntegrationSyncProfile` modelinde `request_jitter_seconds` değerini artırın (örn. 5 veya 10 saniye jitter ekleyin).

### 3.3 Doğrulama Hataları (422 Unprocessable Entity)
* **Neden:** Fiyat veya stok yükleme XML formatında geçersiz karakter veya eksik alan var.
* **Aksiyon:** HepsiburadaConnector'daki XML generator'ı kontrol edin, merchantSku veya hepsiburadaSku alanlarının listing üzerinde boş olup olmadığını inceleyin.

---

## 4. Rate-Limit Backoff ve Hata Yönetimi

ZOLM Hepsiburada entegrasyonunda tüm HTTP istekleri `AbstractMarketplaceConnector` ve `MarketplaceSyncService` üzerinden otomatik hata toleransına ve rate-limit yönetimine tabidir:
* **Jitter & Delay:** İstekler arasına otomatik rastgele bekleme süresi (jitter) eklenir.
* **Circuit Breaker:** Sürekli hata veren mağazalar geçici olarak senkron zincirinden düşürülerek API blokajı önlenir.
