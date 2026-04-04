# Pazaryeri Smoke Test Karar Ağacı

Bu doküman, smoke test ve diagnostik rapor sonrası hangi adımın atılacağını hızlıca belirlemek için hazırlanmıştır.

## 1. Önce bağlantı hazır mı?

- `marketplace:health-check`
- `marketplace:smoke-test`

Durumlar:
- `Eksik`:
  - önce credential tamamlanır
  - `seller_id`, `api_key`, `api_secret`, `api_base_url`, gerekiyorsa `webhook_secret`
- `Uyarılı`:
  - smoke test çalışabilir
  - ama sonuçlar diagnostik raporla mutlaka okunmalıdır
- `Hazır`:
  - tam smoke test ve ardından gerçek sync yapılabilir

## 2. Smoke test sonrası hangi komut?

Önerilen sıra:

```bash
php artisan marketplace:smoke-test {store_id} --type=all --hours=24 --preview=2 --persist
php artisan marketplace:diagnostics-report --store={store_id} --type=all --smoke-only
php artisan marketplace:diagnostics-guidance {user_id} --store={store_id} --type=all --smoke-only
```

## 3. Karar ağacı

### A. Ürün eşleşme riski yüksekse

Belirti:
- eksik `stock_code`
- eksik `barcode`

Yapılacak:
- önce connector normalize alanları gözden geçirilir
- sonra [Marketplace Matching Center](/Users/canerramazanunal/zolm/app/Livewire/MarketplaceMatchingCenter.php) üzerinden issue kontrol edilir
- gerekiyorsa manuel eşleştirme yapılır

Etkisi:
- kâr hesabı bozulur
- master ürün bağlantısı düşer
- stok/fiyat push güveni azalır

### B. Sipariş kimlik riski yüksekse

Belirti:
- eksik `order_number`
- eksik `package_id`
- eksik `line_id`

Yapılacak:
- connector normalize alanları sıkılaştırılır
- order/package/line fallback alanları canlı payload üzerinden yeniden eşlenir
- tekrar smoke test çalıştırılır

Etkisi:
- dedupe bozulur
- iade, parsiyel kargo ve mutabakat akışı zayıflar

### C. Finans alan riski yüksekse

Belirti:
- eksik `amount`
- eksik `settlement_date`

Yapılacak:
- finans endpoint mapping alanları düzeltilir
- tekrar `Finans çek`
- sonra `Finans V2` mutabakat ekranı kontrol edilir

Etkisi:
- kesin kâr gecikir
- mutabakat güveni düşer

### D. Listing tamlık riski yüksekse

Belirti:
- eksik `listing_id`
- eksik `sale_price`
- eksik `stock_quantity`

Yapılacak:
- ürün/listing normalize alanları düzeltilir
- ardından `Ürün çek`
- gerekiyorsa push öncesi tekrar smoke test alınır

Etkisi:
- ürün paneli eksik görünür
- fiyat/stok push güveni düşer

## 4. Ne zaman gerçek sync’e geçilir?

Şu koşullar sağlanıyorsa:
- bağlantı `Hazır` veya kabul edilebilir `Uyarılı`
- kritik diagnostik öneri kalmamış
- ürün eşleşme riski kontrol altında
- sipariş kimlik alanları dolu
- finans alanları en azından temel seviyede dolu

## 5. Ne zaman toplu onarım kullanılır?

Eğer:
- gerçek sync sonrası failed kayıtlar biriktiyse
- payload mapping düzeltildi ve aynı işleri yeniden çalıştırmak gerekiyorsa

Komut:

```bash
php artisan marketplace:repair-failures --type=all --limit=25
```
