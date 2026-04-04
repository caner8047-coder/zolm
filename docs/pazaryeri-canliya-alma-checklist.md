# Pazaryeri Entegrasyonu Canlıya Alma Checklist

Bu dokuman, ZOLM pazaryeri entegrasyon omurgasini lokalden canli ortama tasirken izlenecek operasyon listesidir.

Amaç:
- entegrasyon modulu
- siparisler v2
- urunler v2
- finans v2
- webhook + polling + queue akislari

tek tek degil, kontrollu sekilde birlikte devreye alinsin.

## 1. Ortam Hazirligi

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://ai.zemuretim.online`
- SSL sertifikasi aktif ve webhook URL disaridan erisilebilir olmali
- server saati ve timezone `Europe/Istanbul` ile uyumlu olmali
- queue driver production icin `database`, `redis` veya kullanilan kalici driver olmali
- cache, session ve queue ayarlari production ortamina uygun olmali

Kontrol:
- `https://ai.zemuretim.online/login` aciliyor mu
- `https://ai.zemuretim.online/api/webhooks/trendyol` route'u 405/419 benzeri beklendik response veriyor mu
- `php artisan marketplace:health-check` temel ortam problemlerini temiz veriyor mu

## 2. Migration ve Kod Deploy

- kod deploy edilir
- yeni migrationlar calistirilir
- config cache temizlenir ve yeniden build edilir
- route cache ve view cache build edilir

Calistirilacak temel komutlar:

```bash
php artisan migrate --force
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 3. Arka Plan Surecleri

Su surecler production'da kalici calismali:

- queue worker
- scheduler

Minimum:
- `php artisan queue:work --queue=default --sleep=3 --tries=3 --timeout=1800`
- `php artisan schedule:run` cron ile her dakika

Oneri:
- Supervisor veya systemd ile worker yonetimi
- ayrik log dosyalari
- deploy sonrasi `php artisan queue:restart`
- ornek dosyalar: `deploy/production/`

## 4. Gerekli Entegrasyon Alanlari

Her magaza icin minimum bilgiler:

- firma
- magaza
- `seller_id`
- `api_key`
- `api_secret`
- gerekiyorsa `store_front_code`
- `webhook_secret`
- `api_base_url`
- feature flag env ayarlari
- `MARKETPLACE_MANUAL_SYNC_DEBOUNCE_SECONDS`
- `MARKETPLACE_MANUAL_SYNC_ACTIVE_RUN_BLOCK_SECONDS`
- `MARKETPLACE_LISTING_PUSH_DEBOUNCE_SECONDS`
- `MARKETPLACE_LISTING_PUSH_ACTIVE_RUN_BLOCK_SECONDS`
- `MARKETPLACE_ORDER_ACTION_DEBOUNCE_SECONDS`
- `MARKETPLACE_ORDER_ACTION_ACTIVE_RUN_BLOCK_SECONDS`

Trendyol icin:
- varsayilan base URL `https://apigw.trendyol.com`
- mevcut Trendyol magazalari icin dusuk etkili profil gerekiyorsa `php artisan marketplace:apply-trendyol-safe-profile --store={id} --dry-run` ile once onizleme alinmali, sonra ayni komut dry-run olmadan uygulanmalidir

Hepsiburada icin:
- `seller_id` alani `merchantId`
- `api_key` alani `serviceKey`
- `extra_user` alani `User-Agent` / entegrator kullanicisi
- varsayilan OMS base URL `https://oms-external.hepsiburada.com`
- varsayilan Listing base URL `https://listing-external.hepsiburada.com`
- varsayilan Finance base URL `https://mpfinance-external.hepsiburada.com`
- fiyat push endpointi `price-uploads`, stok push endpointi `stock-uploads` olarak XML body bekler
- ayni anda bekleyen update sayisinin 5'i gecmemesi onerilir
- paket operasyonlarinda su an yalniz ortak barkod `labels` endpointi aktiflestirilmistir
- fatura linki ve paket statu yazma akislari canli payload netlesmeden kapali tutulur
- mevcut Hepsiburada magazalari icin dusuk etkili profil gerekiyorsa `php artisan marketplace:apply-hepsiburada-safe-profile --store={id} --dry-run` ile once onizleme alinmali, sonra ayni komut dry-run olmadan uygulanmalidir

N11 icin:
- su anda guvenli skeleton asamasindadir
- `api_key` ve `api_secret` alanlari saklanabilir
- resmi endpoint ve auth modeli dogrulanmadan `Bağlantıyı doğrula` bilerek basarisiz donecektir
- readiness ekraninda warning gorulmesi beklenir; bu durum bilincli tasarimdir
- canlı smoke test ve sync capability'leri resmi dokuman netlestikten sonra acilacaktir

Koctas icin:
- su anda guvenli skeleton asamasindadir
- `api_key` ve `api_secret` alanlari saklanabilir
- resmi endpoint ve auth modeli dogrulanmadan `Bağlantıyı doğrula` bilerek basarisiz donecektir
- readiness ekraninda warning gorulmesi beklenir; bu durum bilincli tasarimdir
- canlı smoke test ve sync capability'leri resmi dokuman netlestikten sonra acilacaktir

Pazarama icin:
- su anda guvenli skeleton asamasindadir
- `api_key` ve `api_secret` alanlari saklanabilir
- resmi endpoint ve auth modeli dogrulanmadan `Bağlantıyı doğrula` bilerek basarisiz donecektir
- readiness ekraninda warning gorulmesi beklenir; bu durum bilincli tasarimdir
- canlı smoke test ve sync capability'leri resmi dokuman netlestikten sonra acilacaktir

Amazon icin:
- su anda guvenli skeleton asamasindadir
- `api_key` ve `api_secret` alanlari saklanabilir
- region, role ve SP-API auth modeli dogrulanmadan `Bağlantıyı doğrula` bilerek basarisiz donecektir
- readiness ekraninda warning gorulmesi beklenir; bu durum bilincli tasarimdir
- canlı smoke test ve sync capability'leri resmi dokuman netlestikten sonra acilacaktir

Ciceksepeti icin:
- su anda guvenli skeleton asamasindadir
- `api_key` ve `api_secret` alanlari saklanabilir
- resmi endpoint ve auth modeli dogrulanmadan `Bağlantıyı doğrula` bilerek basarisiz donecektir
- readiness ekraninda warning gorulmesi beklenir; bu durum bilincli tasarimdir
- canlı smoke test ve sync capability'leri resmi dokuman netlestikten sonra acilacaktir

WooCommerce icin:
- `api_key` alani `consumer key`
- `api_secret` alani `consumer secret`
- `api_base_url` alanina site kok URL veya dogrudan `wp-json/wc/v3` base URL yazilabilir
- webhook imza dogrulamasi icin `webhook_secret` zorunlu doldurulmalidir
- finans servisi olmadigi icin ilk rollout'ta `finans` sync kapali kalir
- mevcut WooCommerce magazalari icin dusuk etkili profil gerekiyorsa `php artisan marketplace:apply-woo-safe-profile --store={id} --dry-run` ile once onizleme alinmali, sonra ayni komut dry-run olmadan uygulanmalidir
- webhook topic hijyeni icin `php artisan marketplace:apply-recommended-webhook-topics --store={id}` komutu ile onerilen set kalici uygulanabilir
- WooCommerce webhook burst'lerinde ayni magazada aktif refresh varsa ikinci sync run acilmaz; event `debounced` olarak loglanir
- WooCommerce `product.created|updated|deleted` benzeri webhook'lar urun sync'e, siparis webhook'lari siparis refresh akışına yönlenir
- WooCommerce icin magaza profilinde secili webhook topic listesi disindaki event'ler `ignored` olarak kaydedilir ve sync tetiklemez
- bu akisin store bazli saglikli calismasi icin webhook event unique index migration'inin deploy'da uygulanmasi gerekir

Legacy Excel fallback icin:

- `Siparisler V2` ekranindaki legacy import alaninda projection magazasi secilirse yeni operasyonel Excel kayitlari ayni anda `channel_orders` projection yapisina da yazilir
- mevcut eski legacy operasyon kayitlarini sonradan tasimak icin `php artisan marketplace:project-legacy-orders {store_id} --only-unprojected --include-unassigned --dry-run` komutu ile once onizleme alinmali, sonra dry-run olmadan projection calistirilmalidir
- legacy `mp_orders` finans kayitlarini yeni `order_financial_events` ledger yapisina tasimak icin `php artisan marketplace:project-legacy-financials {store_id} --only-unprojected --dry-run` komutu ile once onizleme alinmali, sonra dry-run olmadan projection calistirilmalidir
- finans projection sonrasinda ilgili magazada `Finans V2` ekraninda confirmed snapshot ve mutabakat gorunumu kontrol edilmelidir

Shopify icin:
- `api_key` alani `Admin API access token`
- `api_secret` alani `app secret key`
- `api_base_url` alanina magaza kok URL veya dogrudan versioned GraphQL Admin endpoint yazilabilir
- `webhook_secret` alanina app secret key ile ayni degerin girilmesi onerilir
- finans akisi odeme/gateway transaction ve fee kayitlarini ceker; pazaryeri tipi hak edis mantigi beklenmemelidir
- fiyat/stok push GraphQL Admin API uzerinden variant ve inventory mutation'lariyla yapilir
- mevcut Shopify magazalari icin dusuk etkili profil gerekiyorsa `php artisan marketplace:apply-shopify-safe-profile --store={id} --dry-run` ile once onizleme alinmali, sonra ayni komut dry-run olmadan uygulanmalidir
- Shopify webhook topic hijyeni icin `php artisan marketplace:apply-recommended-webhook-topics --store={id}` komutu ile onerilen set kalici uygulanabilir
- onerilen Shopify topic seti siparis, iade, urun ve stok degisimleriyle sinirlidir; fazladan topic acmak gereksiz API ve queue gurultusu uretebilir

## 5. Entegrasyon Ekrani Kontrolleri

Her secili magaza icin su adimlar uygulanir:

1. Baglanti kaydi acilir
2. `Bağlantıyı doğrula` calistirilir
3. capability rozetleri beklenen sekilde gorunur
4. sync profile toggle'lari kontrol edilir
5. polling araliklari dogrulanir
6. webhook URL kopyalanir
7. manuel sync debounce env'lerinin hedef ortama tanimli oldugu dogrulanir
8. listing push debounce env'lerinin hedef ortama tanimli oldugu dogrulanir
9. order/package action debounce env'lerinin hedef ortama tanimli oldugu dogrulanir

Kontrol listesi:
- siparis capability
- urun capability
- finans capability
- webhook capability
- fiyat push capability
- stok push capability
- paket statu capability
- ortak barkod capability
- fatura linki capability
- manuel sync debounce korumasi
- listing push debounce korumasi
- order/package action debounce korumasi

## 6. Scheduler ve Sync Ayarlari

Onerilen baslangic ayari:

- Siparis polling: `15 dk`
- Finans polling: `30-60 dk`
- Urun polling: `360 dk`
- Nightly repair sync: acik
- Auto match: acik
- Barcode fallback: acik
- Strict unique match: acik
- Max parallel jobs: `1`
- Request jitter seconds: `5`

Ilk canliya almada paralellik dusuk tutulmali.

Trendyol icin daha guvenli baslangic:

- Siparis polling: `15 dk`
- Finans polling: `60 dk`
- Urun polling: `720 dk`
- Max parallel jobs: `1`
- Request jitter seconds: `5`
- Fiyat push: kapali
- Stok push: kapali
- Backfill: `7 gun`

Hepsiburada icin daha guvenli baslangic:

- Siparis polling: `20 dk`
- Finans polling: `120 dk`
- Urun polling: `720 dk`
- Webhook: kapali
- Max parallel jobs: `1`
- Request jitter seconds: `10`
- Fiyat push: kapali
- Stok push: kapali
- Backfill: `7 gun`

Manuel sync korumasi icin:

- `MARKETPLACE_MANUAL_SYNC_DEBOUNCE_SECONDS=30`
- `MARKETPLACE_MANUAL_SYNC_ACTIVE_RUN_BLOCK_SECONDS=900`
- panelden ayni magazada ayni `Siparis cek / Urun cek / Finans cek` aksiyonu art arda tiklansa bile ikinci queue kaydi acilmamasi beklenir
- aktif bir sync run varsa UI yeni is acmak yerine mevcut calismanin zaten sirada oldugunu bildirmelidir

Listing push korumasi icin:

- `MARKETPLACE_LISTING_PUSH_DEBOUNCE_SECONDS=45`
- `MARKETPLACE_LISTING_PUSH_ACTIVE_RUN_BLOCK_SECONDS=900`
- ayni listing icin bekleyen fiyat/stok push kaydi varsa yeni is acmak yerine mevcut queued kayit guncellenmelidir
- `processing` durumundaki push isleri icin ikinci kayit acilmamasi ve UI'nin mevcut is no'sunu kullaniciya gostermesi beklenir

Order/package action korumasi icin:

- `MARKETPLACE_ORDER_ACTION_DEBOUNCE_SECONDS=45`
- `MARKETPLACE_ORDER_ACTION_ACTIVE_RUN_BLOCK_SECONDS=900`
- ayni siparis veya pakette ayni aksiyon tekrar tiklanirsa bekleyen `queued/retrying` kayit guncellenmeli, yeni kayit acilmamalidir
- `processing` durumundaki aksiyon icin ikinci kayit acilmamasi ve UI'nin mevcut is no'sunu kullaniciya gostermesi beklenir
- `php artisan marketplace:repair-failures` calistirildiginda push ve aksiyon tiplerinde `Yeni / Guncellendi / Calisiyordu / Cok yeni` ozet sutunlari ile davranis dogrulanabilir

WooCommerce icin daha guvenli baslangic:

- Siparis polling: `30 dk`
- Finans polling: `360 dk` ve kapalidir
- Urun polling: `720 dk`
- Max parallel jobs: `1`
- Request jitter seconds: `15`
- Fiyat push: kapali
- Stok push: kapali
- Backfill: `7 gun`

Shopify icin daha guvenli baslangic:

- Siparis polling: `20 dk`
- Finans polling: `240 dk`
- Urun polling: `720 dk`
- Max parallel jobs: `1`
- Request jitter seconds: `10`
- Fiyat push: kapali
- Stok push: kapali
- Backfill: `7 gun`

## 7. Webhook Kurulumu

Trendyol webhook ayarlari yapilirken:

- webhook URL production domain ile verilmeli
- secret ZOLM tarafindaki ile ayni olmali
- ilk asamada webhook tek basina veri kaynagi gibi dusunulmemeli
- webhook sadece hizli tetikleyici, asil veri yine kontrollu sync ile tamamlanmali

Beklenen akış:

1. webhook gelir
2. imza dogrulanir
3. event loglanir
4. ilgili sync run kuyruğa alınır

WooCommerce ve Shopify icin:

- webhook-first, poll-fallback mantigi kullanilmalidir
- secili topic seti disindaki webhook'lar `ignored` loglanir ve sync baslatmaz
- art arda gelen tekrar event'ler `debounced` olarak isaretlenir; ayni magazada ikinci refresh run acilmaz
- ilk canli rollout'ta onerilen topic seti mutlaka uygulanmalidir

## 8. Ilk Smoke Test Sirasi

Her yeni magaza icin:

1. `Ürün çek`
2. `Sipariş çek`
3. `Finans çek`
4. `php artisan marketplace:smoke-test {store_id} --type=all --hours=24 --preview=2`
5. `Siparişler V2` ekranı kontrol edilir
6. `Ürünler V2` ekranı kontrol edilir
7. `Finans V2` ekranı kontrol edilir
8. `Özet` ekranı kontrol edilir
9. `Eşleştirme Merkezi` kontrol edilir

Kontrol sorulari:
- siparisler geliyor mu
- paketler geliyor mu
- urun satirlari geliyor mu
- finans event kayitlari geliyor mu
- tahmini / kesin kar durumu dogru mu
- eslesmeyen satirlar issue olarak dusuyor mu

Not:
- smoke test artik baglanti alanlarini da once kontrol eder
- eksik zorunlu alan varsa komut fail olur
- zorlayarak devam etmek gerekirse `--skip-readiness` kullanilabilir
- smoke test sonrasi `php artisan marketplace:diagnostics-report --store={id} --type=all --smoke-only` ile mapping bosluklari toplu okunabilir
- sonraki karar adimi icin `php artisan marketplace:diagnostics-guidance {user_id} --store={id} --type=all --smoke-only` kullanilabilir
- topic ve dusuk etkili profil standardizasyonu icin rollout oncesi sirayla:
  - `php artisan marketplace:apply-trendyol-safe-profile --store={id}` veya `php artisan marketplace:apply-hepsiburada-safe-profile --store={id}`
  - `php artisan marketplace:apply-recommended-webhook-topics --store={id}`
  - `php artisan marketplace:apply-woo-safe-profile --store={id}` veya `php artisan marketplace:apply-shopify-safe-profile --store={id}`

## 9. Siparis Operasyon Smoke Testi

Tek bir test siparisi uzerinden:

- `Siparişi yenile`
- `Kargoyu yenile`
- `Finansı yenile`
- `Kârı hesapla`

Paket destekliyorsa:

- `Picking bildir`
- `Fatura kesildi`
- `Barkod talep et`
- `Barkodu getir`
- `Fatura linki gönder`

Kontrol:
- `integration_order_action_runs` kaydi aciliyor mu
- status `queued > processing > completed` akiyor mu
- `error_message` bos mu
- `external_action_id` veya `response_json` doluyor mu

## 10. Push Smoke Testi

Tek bir listing uzerinde:

- fiyat push
- stok push

Kontrol:
- `integration_push_runs` kaydi aciliyor mu
- queue calisiyor mu
- response loglaniyor mu

## 11. Log ve Saglik Takibi

Canliya alimin ilk gunlerinde su ekranlar aktif izlenmeli:

- `Pazaryeri > Özet`
- `Pazaryeri > Entegrasyonlar`
- `Pazaryeri > Siparişler`
- `Pazaryeri > Finans`
- `Pazaryeri > Eşleştirme`

Ozellikle takip edilmesi gerekenler:

- failed sync sayisi
- retrying order action sayisi
- failed push sayisi
- failed webhook sayisi
- gerekirse `php artisan marketplace:repair-failures --type=all --limit=25` ile toplu onarım çalıştırılması
- failed push sayisi
- failed webhook sayisi
- gecersiz webhook imza sayisi
- open match issue sayisi

## 12. Rollout Stratejisi

Ilk rollout onerisi:

1. Tek firma + tek Trendyol mağazası
2. Sadece okuma sync acik
3. Push acik ama tek kullanıcı ile test
4. Paket operasyonları sadece iç ekipte test
5. Sonra ikinci mağaza
6. Sonra ikinci pazaryeri connector

## 13. Rollback Planı

Sorun cikarsa:

- yeni sync profile toggle'lari kapatilir
- webhook kapatilir
- gerekli gorulurse `MARKETPLACE_V2_ENABLED=false`
- queue worker durdurulmaz, mevcut işler bosaltilir
- kullanici `legacy Excel fallback` ile devam eder

Geri donus senaryosu:

- `orders_enabled=false`
- `finance_enabled=false`
- `products_enabled=false`
- `webhook_enabled=false`
- ekranlar okunur halde kalir
- import fallback korunur

## 14. Canlıya Almadan Once Son Kontrol

- migration tamam
- route cache tamam
- view cache tamam
- queue worker aktif
- scheduler aktif
- `APP_URL` production domain
- SSL aktif
- webhook URL dogru
- webhook secret dogru
- `php artisan marketplace:health-check --fail-on-warning` temiz donuyor
- bir test magazada siparis/urun/finans sync gecti
- bir test siparisinde order action gecti
- bir test listinginde push gecti

Bu maddeler tamamlanmadan tum kullanicilar icin rollout yapilmasi onerilmez.
