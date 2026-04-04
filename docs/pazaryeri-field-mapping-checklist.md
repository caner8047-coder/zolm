# Pazaryeri Field Mapping Checklist

Bu liste canlı credential geldikten sonra connector mapping hardening için kullanılır.

## Siparişler

Kontrol edilecek alanlar:
- `order_number`
- `external_order_id`
- `external_package_id`
- `external_line_id`
- `stock_code`
- `barcode`
- `quantity`
- `unit_price`
- `gross_amount`
- `billable_amount`
- `commission_rate`
- `vat_rate`
- `package_status`
- `cargo_tracking_number`

Kabul kriteri:
- order/package/line alanları boş kalmamalı
- stok kodu veya barkoddan en az biri dolu olmalı
- tutar alanları satır bazında hesaplanabilir olmalı

## Ürünler / Listing

Kontrol edilecek alanlar:
- `external_product_id`
- `listing_id`
- `stock_code`
- `barcode`
- `title`
- `sale_price`
- `stock_quantity`
- `listing_status`

Kabul kriteri:
- listing id dolu olmalı
- fiyat/stok alanları push için güvenilir gelmeli
- stok kodu veya barkoddan en az biri dolu olmalı

## Finans

Kontrol edilecek alanlar:
- `external_event_id`
- `order_number`
- `external_package_id`
- `external_line_id`
- `event_type`
- `amount`
- `direction`
- `settlement_date`

Kabul kriteri:
- amount boş kalmamalı
- settlement date mümkün olduğunca dolu olmalı
- order/package eşleşmesi yapılabilmeli

## Karar destek komutları

```bash
php artisan marketplace:diagnostics-report --store={store_id} --type=all --smoke-only
php artisan marketplace:diagnostics-guidance {user_id} --store={store_id} --type=all --smoke-only
```
