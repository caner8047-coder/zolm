# Hepsiburada Entegrasyonu Operasyon Kılavuzu (Runbook)

Bu kılavuz, ZOLM yöneticileri ve operasyon ekipleri için Hepsiburada API entegrasyonunun elle tetiklenmesi, backfill yapılması ve hata giderilmesini anlatır.

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

### 1.3 Tam Katalog Senkronu
Mağazanın tam ürün içeriğini (açıklamalar, görseller, özellikler, onay statüsü) çekmek için:
```bash
php artisan marketplace:dispatch-sync {store_id} catalog_products
```
* **Resmî Endpoint:** `product/api/products/all-products-of-merchant/{merchantId}`

### 1.4 Finansal Hareketler Senkronu
Mağazanın hakediş, komisyon, kargo kesintileri vb. finans hareketlerini çekmek için:
```bash
php artisan marketplace:dispatch-sync {store_id} finance --start="2026-03-01T00:00:00+03:00" --end="2026-03-31T00:00:00+03:00"
```

---

## 2. Batch İşlem Sonuç Takibi (Durum Polling)

Toplu fiyat veya stok güncelleme işlemlerinin durumunu izlemek için `pullBatchStatus` kullanılır:
```php
$connector = app(\App\Services\Marketplace\MarketplaceConnectorManager::class)->resolve('hepsiburada');
$result = $connector->pullBatchStatus($store, 'batch-request-id', 'price-uploads');
```

---

## 3. Rate-Limit Backoff ve Jitter

ZOLM Hepsiburada entegrasyonu rate limit (429) durumlarında otomatik jitter ve backoff kuralları uygular. Hız sınırı sorunlarında:
* `IntegrationSyncProfile` modelinde `request_jitter_seconds` değerini artırın (örn. 5-10 saniye aralığı).
* Circuit Breaker'ın devreye girmesi durumunda mağaza loglarını "429 Too Many Requests" hata kalıbına göre süzün.
