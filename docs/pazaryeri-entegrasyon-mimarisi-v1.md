# Pazaryeri Entegrasyon Mimarisi v1

Bu dokuman, ZOLM icin kurulacak yeni pazaryeri entegrasyon omurgasinin teknik ve urunsel tasarim referansidir.

Amac sadece Trendyol entegrasyonu yapmak degildir. Amac:

- Trendyol ile baslayan
- Hepsiburada, N11, Pazarama, Amazon, Ciceksepeti, Koctas gibi pazaryerlerine genisleyebilen
- WooCommerce ve Shopify gibi e-ticaret altyapilarini da kapsayabilen
- Siparis, urun, stok, fiyat, finans ve karlilik akisini tek bir merkezde toplayan
- Cok firmali ve cok magazali calisabilen
- Excel fallback akisini bozmadan API tabanli canli sisteme gecebilen

bir cekirdek sistem kurmaktir.

## 1. Onaylanan Is Kurallari

Bu dokuman asagidaki kullanici kararlarini esas alir:

- Bir kullanici birden fazla vergi numarasina sahip olabilir.
- Her magaza tek bir vergi numarasina bagli olur.
- Magaza bazinda KDV, stopaj, kendi kargo kullanimi, finans ve operasyon kurallari degisebilir.
- Stok kodu urunun ana parmak izidir.
- Barkod ikinci ana eslestirme anahtari olarak kullanilabilir.
- Iade senaryosunda varsayilan zarar urun maliyeti degil; kargo ve varsa ambalaj maliyetidir.
- Ilk fazda kaynak ve veri toplama, detayli analiz ve saglam mimari kurulumu onceliklidir.
- Webhook + polling birlikte kullanilacaktir.
- Varsayilan polling agresif olmayacak, rate limit dostu ve optimize olacaktir.
- Excel akisi sistemde fallback olarak kalacaktir.
- Backfill su seceneklerle yonetilecektir:
  - Son 7 gun
  - Son 30 gun
  - Son 90 gun
  - Son 180 gun
  - Maksimum izin verilen
  - Ozel aralik
- Urun otomatik eslestirme acilip kapatilabilir bir ayar olacaktir.

## 2. Ana Urunsel Karar

ZOLM bir "pazaryeri veri okuyucu" degil, bir "e-ticaret operasyon ve finans veri merkezi" olacaktir.

Bu nedenle veri otoriteleri su sekilde ayrilir:

- Ic urun masteri:
  - maliyet
  - ambalaj
  - kendi kargo maliyeti
  - ic urun kimligi
  - bundle/set mantigi
  - karlilik motorunun ana veri kaynagi
- Kanal/listing verisi:
  - stok
  - satis fiyati
  - yayinda mi
  - listeleme basligi
  - kategori
  - kanal bazli performans
- Siparis ve finans verisi:
  - siparis
  - paket
  - satir
  - komisyon
  - hakedis
  - hizmet bedeli
  - stopaj
  - ceza
  - iade etkisi

Sonuc:

- Karlilik hesaplamasi pazaryerindeki ham urun verisine guvenmeyecek.
- Karlilik hesaplamasi ZOLM ic urun masteri + kanal finans olaylari uzerinden yapilacak.

## 3. Kavramsal Alanlar

Sistemi asagidaki bounded context mantigi ile kurmak uygundur:

### 3.1 Firma Yonetimi

- Kullaniciya ait vergisel yapilar
- Vergi numarasi, unvan, banka, muhasebe ayarlari
- KDV ve stopaj defaultlari

### 3.2 Magaza ve Baglanti Yonetimi

- Trendyol magaza baglantisi
- Hepsiburada baglantisi
- WooCommerce baglantisi
- API anahtarlari
- webhook ayarlari
- polling ayarlari
- backfill ayarlari

### 3.3 Urun Masteri

- Stok kodu bazli tekil urun kimligi
- maliyet
- ambalaj
- kendi kargo
- bundle/set
- varyant mantigi

### 3.4 Kanal Listingleri

- Her ic urunun bir veya daha fazla kanal karsiligi
- kanal urun numarasi
- listing durumu
- fiyat
- stok
- kategori
- gorunurluk

### 3.5 Siparis Omurgasi

- Siparis
- paket
- satir
- kanal durumlari
- musteri
- teslimat
- kargo

### 3.6 Finans Omurgasi

- hakedis
- komisyon
- hizmet bedeli
- stopaj
- kargo kesintisi
- iade finans hareketi
- ceza
- mutabakat

### 3.7 Karlilik Motoru

- tahmini kar
- kesin kar
- iade etkisi
- finans bekleniyor
- manuel/otomatik duzeltme gecmisi

## 4. Veri Modeli

Asagidaki tablo seti onerilir.

## 4.1 Firma Katmani

### `legal_entities`

Kullaniciya ait vergisel yapilar.

Onerilen alanlar:

- `id`
- `user_id`
- `name`
- `tax_number`
- `tax_office`
- `mersis_number`
- `company_type`
- `phone`
- `email`
- `address`
- `iban`
- `bank_name`
- `currency`
- `is_active`
- `created_at`
- `updated_at`

Kural:

- `user_id + tax_number` benzersiz olmali.

### `legal_entity_settings`

Firma bazli finans ve operasyon ayarlari.

Onerilen alanlar:

- `id`
- `legal_entity_id`
- `settings_json`
- `created_at`
- `updated_at`

Bu alanda tutulabilecek ayarlar:

- default KDV orani
- stopaj orani
- kendi kargo default acik/kapali
- varsayilan ambalaj fallback maliyeti
- iade muhasebe kurali
- finans mutabakat toleranslari

## 4.2 Magaza ve Baglanti Katmani

### `marketplace_stores`

Gercek magaza kaydi.

Onerilen alanlar:

- `id`
- `user_id`
- `legal_entity_id`
- `marketplace`
- `store_name`
- `store_code`
- `seller_id`
- `status`
- `timezone`
- `currency`
- `is_active`
- `created_at`
- `updated_at`

Kural:

- Bir magaza tek firmaya bagli olur.
- `marketplace + seller_id` benzersiz olmali.

### `integration_connections`

API baglantisi ve erisim bilgileri.

Onerilen alanlar:

- `id`
- `store_id`
- `provider`
- `auth_type`
- `credentials_encrypted`
- `webhook_secret`
- `webhook_url`
- `api_base_url`
- `status`
- `last_verified_at`
- `last_error`
- `created_at`
- `updated_at`

Not:

- API key ve secret alanlari dogrudan duz metin tutulmamalidir.
- Laravel encryption + masked display kullanilmalidir.

### `integration_sync_profiles`

Kullaniciya acik optimize edilebilir senkron ayarlari.

Onerilen alanlar:

- `id`
- `store_id`
- `orders_poll_minutes`
- `finance_poll_minutes`
- `products_poll_minutes`
- `backfill_mode`
- `backfill_days`
- `backfill_custom_from`
- `backfill_custom_to`
- `orders_enabled`
- `finance_enabled`
- `products_enabled`
- `webhook_enabled`
- `price_push_enabled`
- `stock_push_enabled`
- `auto_match_enabled`
- `barcode_fallback_enabled`
- `strict_unique_match_enabled`
- `nightly_repair_sync_enabled`
- `max_parallel_jobs`
- `request_jitter_seconds`
- `created_at`
- `updated_at`

Bu tablo kritik cunku "varsayilana gore optimize et ama costimize edilsin" beklentisini karsilar.

### `integration_sync_runs`

Her sync calismasinin kayit defteri.

Onerilen alanlar:

- `id`
- `store_id`
- `sync_type`
- `trigger_type`
- `status`
- `cursor_before`
- `cursor_after`
- `started_at`
- `finished_at`
- `duration_ms`
- `items_received`
- `items_created`
- `items_updated`
- `items_skipped`
- `rate_limit_hits`
- `error_count`
- `notes_json`

### `integration_webhook_events`

Webhook gelen tum ham olaylar.

Onerilen alanlar:

- `id`
- `store_id`
- `provider`
- `event_type`
- `external_event_id`
- `signature_valid`
- `payload_json`
- `received_at`
- `processed_at`
- `status`
- `error_message`

Kural:

- `provider + external_event_id` uniq olmali.

## 4.3 Urun Katmani

### Mevcut `mp_products`

Var olan urun tablosu korunur ve "ic urun masteri" olarak devam eder.

Bu tablonun rol degisimi:

- mevcut maliyet, ambalaj, kargo maliyeti, KDV alanlari korunur
- ana urun kimligi olarak davranir
- stok kodu eslestirmesinin merkezi olur

Gerekirse sonradan eklenebilecek alanlar:

- `legal_entity_id`
- `product_group_type`
- `bundle_strategy`
- `return_value_loss_rate`
- `is_sellable_after_return`

### `channel_products`

Pazaryerinden gelen urun nesnesi.

Onerilen alanlar:

- `id`
- `store_id`
- `external_product_id`
- `external_parent_id`
- `stock_code`
- `barcode`
- `title`
- `brand`
- `category_name`
- `vat_rate`
- `raw_payload`
- `last_synced_at`

### `channel_listings`

Kanal listing kaydi.

Onerilen alanlar:

- `id`
- `store_id`
- `channel_product_id`
- `mp_product_id`
- `listing_id`
- `listing_status`
- `sale_price`
- `list_price`
- `currency`
- `stock_quantity`
- `published_at`
- `last_price_sync_at`
- `last_stock_sync_at`
- `last_synced_at`

Kural:

- Bir `channel_listing` tercihen tek bir `mp_product` kaydina baglanir.
- Eslesmeyen kayitlar manuel eslestirme havuzuna dusurulur.

### `product_match_issues`

Otomatik eslesmeyen veya supheli eslesen urunler.

Onerilen alanlar:

- `id`
- `store_id`
- `channel_listing_id`
- `match_status`
- `match_reason`
- `candidate_ids_json`
- `resolved_by`
- `resolved_at`

## 4.4 Siparis Katmani

### `channel_orders`

Kullaniciya siparis no seviyesinde gosterilen ana siparis kaydi.

Onerilen alanlar:

- `id`
- `store_id`
- `legal_entity_id`
- `external_order_id`
- `order_number`
- `order_status`
- `commercial_type`
- `customer_name`
- `customer_email`
- `customer_phone`
- `billing_name`
- `billing_tax_number`
- `shipment_country`
- `shipment_city`
- `shipment_district`
- `ordered_at`
- `approved_at`
- `delivered_at`
- `cancelled_at`
- `returned_at`
- `last_synced_at`
- `raw_payload`

UI kimligi:

- `order_number`

Teknik kimlik:

- `store_id + external_order_id`

### `channel_order_packages`

Paket/sevkiyat seviyesindeki kayit.

Onerilen alanlar:

- `id`
- `channel_order_id`
- `external_package_id`
- `package_number`
- `package_status`
- `cargo_company`
- `cargo_tracking_number`
- `cargo_barcode`
- `cargo_desi`
- `shipment_provider`
- `shipped_at`
- `delivered_at`
- `last_synced_at`
- `raw_payload`

Kural:

- UI siparis no odakli olsa da, backend paket seviyesini bilmelidir.
- Bu sayede parsiyel sevkiyat ve kargo mutabakati saglikli olur.

### `channel_order_items`

Siparisin urun satirlari.

Onerilen alanlar:

- `id`
- `channel_order_id`
- `channel_order_package_id`
- `external_line_id`
- `channel_listing_id`
- `mp_product_id`
- `stock_code`
- `barcode`
- `product_name`
- `quantity`
- `unit_price`
- `gross_amount`
- `discount_amount`
- `marketplace_discount_amount`
- `billable_amount`
- `commission_rate`
- `vat_rate`
- `line_status`
- `is_matched`
- `match_source`
- `last_synced_at`
- `raw_payload`

Kural:

- `store_id + external_line_id` veya `order + package + sku + line sequence` ile benzersizlik kurulmalidir.
- Tek basina `order_number` backend anahtari olarak yeterli gorulmemelidir.

## 4.5 Finans Katmani

### `order_financial_events`

Tum finansal hareketlerin ortak ledger tablosu.

Onerilen alanlar:

- `id`
- `store_id`
- `legal_entity_id`
- `channel_order_id`
- `channel_order_package_id`
- `channel_order_item_id`
- `event_source`
- `event_type`
- `external_event_id`
- `reference_number`
- `event_date`
- `due_date`
- `settlement_date`
- `amount`
- `currency`
- `direction`
- `status`
- `notes`
- `raw_payload`
- `created_at`
- `updated_at`

`event_type` ornekleri:

- `gross_sale`
- `commission`
- `commission_refund`
- `service_fee`
- `cargo_charge`
- `cargo_refund`
- `withholding_tax`
- `settlement`
- `penalty`
- `return_charge`
- `discount`
- `coupon_support`
- `manual_adjustment`

Bu tablo sayesinde farkli pazaryerleri tek bir ortak finans modeline normalize edilir.

### `order_profit_snapshots`

Siparis veya siparis satiri bazli hesaplanan sonuc.

Onerilen alanlar:

- `id`
- `channel_order_id`
- `channel_order_item_id`
- `profit_state`
- `gross_revenue`
- `net_receivable`
- `commission_total`
- `cargo_total`
- `service_fee_total`
- `withholding_total`
- `packaging_cost`
- `own_cargo_cost`
- `cogs_cost`
- `return_effect`
- `vat_effect`
- `estimated_profit`
- `confirmed_profit`
- `margin_percent`
- `calculated_at`
- `version`

`profit_state` ornekleri:

- `estimated`
- `awaiting_finance`
- `confirmed`
- `return_pending`
- `return_finalized`
- `cancelled_pre_ship`
- `cancelled_post_ship`

## 5. Eski Sistemle Uyum

Yeni sistem eski Excel akisini bozmayacak.

Bunun icin:

- Mevcut `mp_orders`, `mp_operational_orders`, `mp_operational_order_items`, `mp_settlements`, `mp_transactions` tablolari aniden kaldirilmaz.
- Yeni entegrasyon yapisi once paralel calisir.
- Gecis doneminde eski tablolar ile yeni projection tablolari yan yana kullanilabilir.
- Excel importlari yeni normalize pipeline'a baglanirsa orta vadede cift veri modeli sadeleşir.

Onerilen gecis yontemi:

1. Yeni generic entegrasyon tablolari eklenir.
2. Trendyol connector yeni tablolara yazar.
3. UI yeni projection uzerinden okunur.
4. Excel importlari da ayni normalize hatta baglanir.
5. Son asamada eskiya bagimli query'ler temizlenir.

## 6. Sync Mimarisi

## 6.1 Polling + Webhook Birlikte

Webhook tek basina guvenilecek kaynak degildir.

Onerilen model:

- Webhook:
  - olay geldi bilgisini verir
  - queue job tetikler
  - ayni event duplicate gelirse ignore edilir
- Polling:
  - gercek veri senkronu yapar
  - eksik eventleri yakalar
  - finans ve urun tarafini toparlar

Bu model rate limit dostudur ve veri kaybina daha dayaniklidir.

## 6.2 Varsayilan Senkron Ayarlari

Baslangic defaultlari:

- siparis sync: `15 dakika`
- finans sync: `30 dakika`
- settlement repair sync: `60 dakika`
- urun/listing sync: `6 saat`
- fiyat/stok push sonucu kontrol sync: `15-30 dakika`
- gece onarim sync: `02:00 - 05:00` arasinda

## 6.3 Rate Limit Koruma

Tum connector'larda zorunlu:

- store bazli rate limiter
- provider bazli limiter
- exponential backoff
- jitter
- retry with cap
- cursor/window based incremental sync
- idempotent write
- duplicate event protection

## 6.4 Sync Turleri

Desteklenecek sync tipleri:

- `orders_incremental`
- `orders_backfill`
- `order_status_refresh`
- `financial_incremental`
- `financial_repair`
- `products_incremental`
- `products_full`
- `price_push`
- `stock_push`
- `webhook_followup`

## 7. Profit Engine Tasarimi

## 7.1 Siparis Gelir Gelmez Gosterilecek Alanlar

Siparis panele dustugunde kullanici su bilgileri gorebilmelidir:

- satis tutari
- indirimler
- komisyon orani veya tahmini komisyon
- maliyet
- ambalaj
- kendi kargo maliyeti
- tahmini kar
- finans bekleniyor etiketi

Bu sayede "siparis daha yeni dustu, tahsilat bekleniyor ama satin karliligi ne durumda?" sorusu cevaplanir.

## 7.2 Kesin Kar Mantigi

Kesin kar su veri kaynaklari geldikce olusur:

- settlement / hakedis
- komisyon
- hizmet bedeli
- stopaj
- kargo faturasi veya kargo kesintisi
- iade veya ceza hareketleri

## 7.3 Iade ve Iptal Varsayilan Kurallari

### `cancelled_pre_ship`

- zarar: `0`

### `cancelled_post_ship`

- zarar: `giden kargo + ambalaj`

### `returned_sellable`

- zarar: `giden/donen kargo + ambalaj`

### `returned_damaged`

- zarar: `giden/donen kargo + ambalaj + opsiyonel deger kaybi`

Ilk faz varsayilani:

- urun maliyeti otomatik yanmis sayilmaz
- hasarli iade ozel durumu sonra eklenebilir

## 7.4 Kendi Kargo Mantigi

Magaza bazli toggle:

- `uses_own_cargo = true`
  - `mp_products.cargo_cost` veya urun master cargo maliyeti dahil edilir
- `uses_own_cargo = false`
  - sadece pazaryeri kesintileri dikkate alinir

Bu kural hem siparis ekraninda hem kar motorunda tutarli olmalidir.

## 8. Urun Eslestirme Mimarisi

## 8.1 Varsayilan Eslestirme Sirasi

1. `stock_code`
2. `barcode`
3. manuel eslestirme

## 8.2 Ayar Toggle'lari

Zorunlu ayarlar:

- `Otomatik eslestirme acik`
- `Sadece benzersiz eslesmede bagla`
- `Barkod fallback kullan`
- `Eslesmeyenleri manuel incelemeye gonder`
- `Supheli eslesmelerde otomatik baglama`

## 8.3 Manuel Eslestirme Ekrani

Bu ekran ilk gunden acik olmak zorunda degil ama mimariye dahil olmalidir.

Ekran amaci:

- eslesmeyen listingleri gormek
- olasi ic urun adaylarini listelemek
- toplu eslestirme yapmak
- eslesme kaynagini kaydetmek

## 9. UI Modulleri

Tum yeni ekranlar ZOLM Kurumsal Acik Panel Sistemi ile uyumlu olmalidir.

Referans:

- `docs/zolm-kurumsal-acik-panel-sistemi.md`

## 9.1 Entegrasyonlar Modulu

Amac:

- firma
- magaza
- baglanti
- sync ayarlari
- webhook durumu
- son sync sonucunu yonetmek

Alt ekranlar:

- Firma listesi
- Firma detay/ayar
- Magaza listesi
- Baglanti sihirbazi
- Sync profili
- Sync loglari
- Webhook olay kayitlari

## 9.2 Siparisler Modulu v2

Ana omurga:

- ust ozet karti
- filtre karti
- siparis tablosu
- sag yardimci panel

Kritik filtreler:

- siparis no
- musteri
- stok kodu / barkod / urun
- durum
- tarih araligi
- magaza
- firma
- karlilik durumu
- finans durumu
- iade/iptal durumu

Gelişmis filtreler:

- kargo firmasi
- fatura tipi
- ulke / sehir
- fulfillment tipi
- kendi kargo tipi
- webhook/poll kaynak durumu
- risk etiketi

Satir yapisi:

- siparis no
- magaza
- siparis tarihi
- durum
- musteri
- ciro
- tahmini kar
- kesin kar
- finans durumu
- hizli aksiyonlar

Satir acilinca:

- urun satirlari
- komisyon
- indirim
- platform indirimi
- kargo ve ambalaj etkisi
- finans olay ozeti

## 9.3 Finans Modulu v2

Amac:

- siparis bazli mutabakat
- magaza bazli finansal gorunuruluk
- settlement takibi
- stopaj ve komisyon analizi
- ceza ve iade etkisi

Ana alanlar:

- toplam hakedis
- bekleyen hakedis
- komisyon toplam
- hizmet bedeli toplam
- kargo toplam
- stopaj toplam
- ceza toplam
- mutabakatsiz siparisler

## 9.4 Urunler Modulu v2

Amac:

- ic urun masteri
- kanal listingleri
- stok/fiyat senkronu
- karlilik gorunumu

Iki katmanli gorunum:

- master urun listesi
- secilen urunun bagli listingleri

Master urunde:

- stok kodu
- urun adi
- maliyet
- ambalaj
- kendi kargo
- KDV
- bundle tipi
- toplam listing sayisi

Bagli listinglerde:

- kanal
- magaza
- listing durumu
- fiyat
- stok
- son sync
- push sonucu

## 10. Hizli Aksiyonlar

Ilk fazda tum operasyon aksiyonlari tam implement edilmek zorunda degildir fakat mimaride yer ayirilmalidir.

Siparis icin gelecekte desteklenecek aksiyonlar:

- kargo bilgisi
- kargo barkodu
- fatura goruntuleme / olusturma
- musteri bilgisi
- siparis notu
- muhasebe etkisi
- toplu onay
- toplu kargo guncelleme

Urun icin gelecekte desteklenecek aksiyonlar:

- fiyat push
- stok push
- listing durum guncelle
- kategori/fiyat/stock analysis

## 11. Connector Katmani

Kod tarafinda generic bir provider kontrati kurulmalidir.

Onerilen klasorleme:

```text
app/
├── Domain/
│   └── Marketplace/
│       ├── Connectors/
│       │   ├── Contracts/
│       │   │   ├── PullsOrders.php
│       │   │   ├── PullsProducts.php
│       │   │   ├── PullsFinancials.php
│       │   │   ├── ReceivesWebhooks.php
│       │   │   ├── PushesPrice.php
│       │   │   └── PushesStock.php
│       │   ├── DTO/
│       │   ├── Normalizers/
│       │   ├── Trendyol/
│       │   ├── Hepsiburada/
│       │   ├── N11/
│       │   ├── WooCommerce/
│       │   └── Shopify/
│       ├── Jobs/
│       ├── Services/
│       ├── Support/
│       └── ValueObjects/
```

Onerilen servisler:

- `IntegrationConnectionService`
- `IntegrationSyncScheduler`
- `IntegrationSyncRunner`
- `WebhookIngestionService`
- `OrderNormalizationService`
- `FinancialNormalizationService`
- `ProductMatchingService`
- `ProfitProjectionService`
- `PricePushService`
- `StockPushService`

## 12. Guvenlik ve Isletim Kurallari

- Tum credentials encrypted tutulmali
- Webhook imzalari dogrulanmali
- Tum ham payloadlar loglanmali ama hassas alanlar maskelenmeli
- Queue bazli isleme kullanilmali
- Tek store icin ayni sync tipi ayni anda iki kez kosmamali
- Circuit breaker mantigi olmali
- Ust uste rate limit veya auth hatasi alan baglantilar otomatik paused duruma alinabilmeli

## 13. Gecis Stratejisi

## Faz 0: Tasarim ve Omurga

- tablolar
- connector kontratlari
- sync log yapisi
- webhook omurgasi
- projection mantigi

## Faz 1: Trendyol Dikey Dilimi

- baglanti kur
- siparis cek
- paket ve satir cek
- urun/listing cek
- finans olaylarini cek
- tahmini ve kesin kar hesapla
- siparis ekrani v2 ilk versiyon

## Faz 2: Finans ve Mutabakat Derinlesmesi

- settlement repair
- ceza ve iade etkileri
- finans dashboard
- eksik/eslesmeyen finans olaylari

## Faz 3: Cift Yonlu Kanal Yonetimi

- fiyat push
- stok push
- push sonucu takibi
- listing sagligi

## Faz 4: Diger Connector'lar

- Hepsiburada
- N11
- Pazarama
- Amazon
- Ciceksepeti
- WooCommerce
- Shopify

## 14. Ilk Uygulama Backlog'u

## Epic 1: Firma ve Magaza Omurgasi

- `legal_entities` migration
- `marketplace_stores` migration
- `integration_connections` migration
- `integration_sync_profiles` migration
- firma ve magaza modelleri
- temel ayar ekranlari

## Epic 2: Generic Connector Cekirdegi

- connector contracts
- sync runner
- sync run logger
- webhook event logger
- provider factory

## Epic 3: Trendyol Siparis Ingestion

- Trendyol auth client
- order polling job
- webhook endpoint
- package normalization
- line normalization
- duplicate koruma

## Epic 4: Trendyol Urun ve Listing Ingestion

- urun/listing cekme
- `channel_products`
- `channel_listings`
- otomatik stock_code eslestirme
- eslesmeyenlerin issue havuzuna dusmesi

## Epic 5: Trendyol Finans Ingestion

- settlement normalization
- commission/service/cargo/stopaj eventleri
- `order_financial_events`
- mutabakat katmani

## Epic 6: Profit Engine v2

- tahmini kar hesaplayici
- kesin kar hesaplayici
- iade ve iptal state machine
- `order_profit_snapshots`

## Epic 7: Entegrasyonlar Ekrani

- baglanti listeleme
- baglanti olusturma
- sync ayarlari
- backfill secenekleri
- son sync sonucu

## Epic 8: Siparisler Ekrani v2

- yeni veri kaynagi
- filtreler
- satir acilimi
- tahmini/kesin kar badgeleri
- magaza ve firma filtreleri

## Epic 9: Finans Ekrani v2

- settlement paneli
- komisyon ve stopaj ozeti
- mutabakat listesi
- sorunlu siparisler

## Epic 10: Urunler Ekrani v2

- master urun + listing baglantilari
- stok/fiyat durumu
- push ayarlari
- eslesme sorunlari paneli

## 15. Ertelenebilecek Ama Mimaride Yer Ayrilacak Konular

- hasarli iade icin deger kaybi yuzdesi
- bundle/BOM bazli maliyet dagitimi
- operasyon aksiyonlari tam otomasyonu
- ERP ile derin cift yonlu entegrasyon
- kanal bazli SLA ve performans puanlari
- magaza saglik skoru

## 16. Onerilen Ilk Teknik Uygulama Sirasi

Kod yazimina su sirayla baslamak en saglikli yoldur:

1. yeni migration'lar ve temel modeller
2. generic connector omurgasi
3. Trendyol auth + order polling
4. webhook endpoint + event log
5. order/package/item normalization
6. urun/listing normalization
7. finans event normalization
8. profit snapshot hesaplayici
9. entegrasyonlar ekrani
10. siparisler ekrani v2

## 17. Sonuc

Bu mimari ile ZOLM:

- cok magazali
- cok firmali
- API ve Excel birlikte calisabilen
- polling + webhook destekli
- urun masteri merkezli
- tahmini ve kesin kari ayiran
- yeni connector eklemeye uygun

bir entegrasyon cekirdegine sahip olur.

Bu belge, implementasyon baslamadan once ana referans olarak kullanilmalidir.
