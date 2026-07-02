# Pazaryeri Entegrasyonu Canlıya Alma Checklist

Bu dokuman, ZOLM pazaryeri entegrasyon omurgasini lokalden canli ortama tasirken izlenecek operasyon listesidir.

Amaç:
- entegrasyon modulu
- siparisler v2
- urunler v2
- finans v2
- kar merkezi, risk merkezi ve otomatik rapor katmani
- webhook + polling + queue akislari

tek tek degil, kontrollu sekilde birlikte devreye alinsin.

## 1. Ortam Hazirligi

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://m.zolm.com.tr`
- SSL sertifikasi aktif ve webhook URL disaridan erisilebilir olmali
- server saati ve timezone `Europe/Istanbul` ile uyumlu olmali
- queue driver production icin `database`, `redis` veya kullanilan kalici driver olmali
- cache, session ve queue ayarlari production ortamina uygun olmali

Kontrol:
- `https://m.zolm.com.tr/login` aciliyor mu
- `https://m.zolm.com.tr/api/webhooks/marketplaces/trendyol/{store_id}` route'u POST icin imza dogrulama response'u veriyor mu
- WooCommerce/Shopify webhook URL'leri production domain ile disaridan HTTPS erisimi aliyor mu
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

Kar Merkezi ve raporlama surecleri:

- `marketplace:sync-risk-signals` scheduler ile saatlik calisir; `MARKETPLACE_RISK_CENTER_ENABLED=true` olmadan veri yazmadan uyarir
- `marketplace:send-report-digests` scheduler ile her 30 dakikada calisir; `MARKETPLACE_REPORT_DIGEST_ENABLED=true` olmadan veri yazmadan uyarir
- `marketplace:backfill-profit-snapshots` scheduler'a bagli degildir; ilk geciste filtreli veya `--all` onayli manuel calistirilir
- ilk backfill mutlaka `--dry-run` ile aday siparis sayisi gorulerek baslatilmalidir

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

Kar Merkezi feature flag env ayarlari:

- `MARKETPLACE_PROFIT_CENTER_ENABLED`
- `MARKETPLACE_ONBOARDING_GUIDE_ENABLED`
- `MARKETPLACE_PRICING_SIMULATOR_ENABLED`
- `PUBLIC_TRENDYOL_PROFIT_TOOL_ENABLED`
- `MARKETPLACE_SETTLEMENT_AUDIT_ENABLED`
- `MARKETPLACE_CAMPAIGN_DECISION_CENTER_ENABLED`
- `MARKETPLACE_RISK_CENTER_ENABLED`
- `MARKETPLACE_REPORT_DIGEST_ENABLED`

Onerilen ilk rollout ayari:

- `MARKETPLACE_PROFIT_CENTER_ENABLED=true`
- `MARKETPLACE_ONBOARDING_GUIDE_ENABLED=true`
- `MARKETPLACE_PRICING_SIMULATOR_ENABLED=false`
- `PUBLIC_TRENDYOL_PROFIT_TOOL_ENABLED=false`
- `MARKETPLACE_SETTLEMENT_AUDIT_ENABLED=false`
- `MARKETPLACE_CAMPAIGN_DECISION_CENTER_ENABLED=false`
- `MARKETPLACE_RISK_CENTER_ENABLED=false`
- `MARKETPLACE_REPORT_DIGEST_ENABLED=false`

Pilot magaza karlilik verisi kanitlandikca simulator, mutabakat, risk ve rapor modulleri kademeli acilmalidir.

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
- varsayilan REST base URL `https://api.n11.com`
- varsayilan SOAP soru/urun endpointi `https://api.n11.com/ws/productService/`
- siparis, urun, soru ve iade cekimi desteklenir; finans sync kapali kalir
- `api_key` ve `api_secret` zorunludur
- ilk canli kontrolde once `questions` smoke testi kisa tarih penceresiyle calistirilmalidir

Koctas icin:
- varsayilan Mirakl base URL `https://koctas.mirakl.net`
- siparis, urun, soru ve iade cekimi desteklenir; finans sync kapali kalir
- `api_key` zorunludur; shop secimi gerekiyorsa baglanti ekstra ayarlari kontrol edilmelidir
- fiyat/stok push capability vardir ama ilk rollout'ta kapali tutulmalidir

Pazarama icin:
- varsayilan API base URL `https://isortagimapi.pazarama.com`
- token URL `https://isortagimgiris.pazarama.com/connect/token`
- siparis, urun, soru ve iade cekimi desteklenir; finans sync kapali kalir
- `api_key` ve `api_secret` ile token alinabildigi `Bağlantıyı doğrula` ekraninda kontrol edilmelidir

Amazon icin:
- su anda guvenli skeleton asamasindadir
- `api_key` ve `api_secret` alanlari saklanabilir
- region, role ve SP-API auth modeli dogrulanmadan `Bağlantıyı doğrula` bilerek basarisiz donecektir
- readiness ekraninda warning gorulmesi beklenir; bu durum bilincli tasarimdir
- canlı smoke test ve sync capability'leri resmi dokuman netlestikten sonra acilacaktir

Ciceksepeti icin:
- varsayilan API base URL `https://apis.ciceksepeti.com/api/v1`
- siparis, urun, soru ve iade cekimi desteklenir; finans sync kapali kalir
- `api_key` zorunludur
- soru endpointi varsayilan olarak `sellerquestions` kabul edilir; canli hesap farkli endpoint istiyorsa env ile ezilmelidir

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
- soru capability
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
- Soru polling: `15 dk`
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

- Siparis polling: `15 dk`
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
- panelden ayni magazada ayni `Siparis cek / Soru cek / Urun cek / Finans cek` aksiyonu art arda tiklansa bile ikinci queue kaydi acilmamasi beklenir
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

- Siparis polling: `15 dk`
- Finans polling: `360 dk` ve kapalidir
- Urun polling: `720 dk`
- Max parallel jobs: `1`
- Request jitter seconds: `15`
- Fiyat push: kapali
- Stok push: kapali
- Backfill: `7 gun`

Shopify icin daha guvenli baslangic:

- Siparis polling: `15 dk`
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
3. `Soru çek`
4. Finans destekleyen kanallarda `Finans çek`
5. `php artisan marketplace:smoke-test {store_id} --type=orders --hours=24 --preview=2`
6. `php artisan marketplace:smoke-test {store_id} --type=questions --hours=168 --preview=2`
7. `php artisan marketplace:smoke-test {store_id} --type=all --hours=24 --preview=2`
8. `Siparişler V2` ekranı kontrol edilir
9. `Sorular` ekranı kontrol edilir
10. `Ürünler V2` ekranı kontrol edilir
11. Finans destekleyen kanallarda `Finans V2` ekranı kontrol edilir
12. `Özet` ekranı kontrol edilir
13. `Eşleştirme Merkezi` kontrol edilir
14. `php artisan marketplace:backfill-profit-snapshots --store={id} --missing --dry-run` ile snapshot adaylari kontrol edilir
15. Adaylar dogruysa `php artisan marketplace:backfill-profit-snapshots --store={id} --missing` calistirilir
16. `Veri Hazırlık Rehberi` bloklari tamamlanma durumuna gore kontrol edilir
17. `Kâr Merkezi` ekraninda ciro, maliyet, net kar ve hazirlik rozetleri kontrol edilir
18. `Hakediş Kontrolü`, `Risk Merkezi` ve `Otomatik Raporlar` yalniz ilgili feature flag aciksa smoke edilir
19. Public Trendyol kar hesaplama araci kullanilacaksa `PUBLIC_TRENDYOL_PROFIT_TOOL_ENABLED=true` ile `/tools/trendyol-kar-hesaplama` route'u ayrica kontrol edilir
20. Lokal sertlestirme paketinde Kar Merkezi, Fiyat Simulatoru, Hakediş Kontrolü, Risk Merkezi, Otomatik Raporlar, Kampanya Karar Merkezi ve public Livewire middleware route/feature flag testleri gecer

Kontrol sorulari:
- siparisler geliyor mu
- sorular geliyor mu
- paketler geliyor mu
- urun satirlari geliyor mu
- finans event kayitlari geliyor mu
- tahmini / kesin kar durumu dogru mu
- eslesmeyen satirlar issue olarak dusuyor mu
- profit snapshot adaylari beklenen magaza ve tarih araligiyla sinirli mi
- urun maliyeti eksikleri `Veri Hazırlık Rehberi` ve `Kâr Merkezi` icinde gorunuyor mu
- risk sinyali ve rapor aboneligi feature flag kapaliyken sessiz, acikken gorunur calisiyor mu
- Kar Merkezi dahil yeni ekranlar feature flag kapaliyken URL uzerinden 404 veriyor mu

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
- `Pazaryeri > Veri Hazırlık Rehberi`
- `Pazaryeri > Entegrasyonlar`
- `Pazaryeri > Siparişler`
- `Pazaryeri > Sorular`
- `Pazaryeri > Finans`
- `Pazaryeri > Eşleştirme`
- `Pazaryeri > Kâr Merkezi`
- `Pazaryeri > Fiyat Simülatörü`
- `Pazaryeri > Hakediş Kontrolü`
- `Pazaryeri > Risk Merkezi`
- `Pazaryeri > Otomatik Raporlar`

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
- profit snapshot eksigi ve urun maliyeti eksigi sayisi
- risk sinyali sayisi ve yeni bildirim uretimi
- rapor digest sonucunda failed abonelik sayisi

## 12. Rollout Stratejisi

Ilk rollout onerisi:

1. Tek firma + tek Trendyol mağazası
2. Sadece okuma sync acik
3. Kar Merkezi ve Veri Hazırlık Rehberi sadece okuma/yol gosterici olarak acik
4. Profit snapshot backfill tek magaza ve sinirli tarih araligiyla yapilmis
5. Push acik ama tek kullanıcı ile test
6. Paket operasyonları sadece iç ekipte test
7. Hakediş Kontrolü, Risk Merkezi ve Otomatik Raporlar pilot veri dogrulamasindan sonra acik
8. Sonra ikinci mağaza
9. Sonra ikinci pazaryeri connector

Pilot veri karar kapisi:

- Snapshot backfill dry-run 0 eksik aday gosteriyorsa yeni snapshot yazmadan Kar Merkezi okunabilir.
- Finans ledger bos ise kâr tahmini kabul edilir; Hakediş Kontrolü, Risk Merkezi ve Otomatik Raporlar kesin finans iddiasi ile acilmaz.
- Maliyet hazirligi %80 altindaysa once `cogs`, `packaging_cost`, `cargo_cost/desi` ve eslesmeyen satirlar tamamlanir.
- Pilot aday `store_id=4462` icin lokal kanit: snapshot kapsami tam, finans ledger bos, maliyet hazirligi %0; bu magaza once maliyet/ambalaj ve finans import/sync temizligi ister.

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
- gerekirse Kar Merkezi feature flagleri tekrar false yapilir
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
- Kar Merkezi feature flagleri hedef rollout seviyesinde
- `php artisan marketplace:health-check --fail-on-warning` temiz donuyor
- bir test magazada siparis/urun/soru sync gecti
- finans destekleyen kanallarda finans sync gecti
- `marketplace:backfill-profit-snapshots --store={id} --missing --dry-run` beklenen adaylari gosteriyor
- gerekiyorsa profit snapshot backfill sinirli kapsamda tamamlandi
- `marketplace:repair-match-issues --store={id} --dry-run` eslesmemis siparis satirlarini gosteriyor
- dry-run sonucu dogruysa `marketplace:repair-match-issues --store={id}` ile listing'siz satirlar Eslestirme Merkezi'ne aksiyonlanabilir issue olarak tasindi
- onarimdan sonra `channel_order_items` icinde `mp_product_id IS NULL OR is_matched = 0` kalan satirlarin `channel_listing_id` degeri bos degil
- fuzzy/model ailesi adaylari `candidate_found` olarak listelenir; bu adaylar otomatik onerilen baglama butonu yerine manuel operator karariyla kapatilmalidir
- `marketplace:sync-risk-signals --user={id} --limit=1` feature flag durumuna gore beklenen sonucu veriyor
- `marketplace:send-report-digests --user={id} --dry-run` feature flag durumuna gore beklenen sonucu veriyor
- bir test siparisinde order action gecti
- bir test listinginde push gecti
- public hesaplama araci acilacaksa route ve middleware ayri feature flag ile dogrulandi
- lokal route/feature flag sertlestirme paketi gecti: 39 test, 683 assertion
- Kar Merkezi yonetici raporundaki `Maliyet Eksikleri` sayfasi pilot maliyet temizligi icin indirildi ve kontrol edildi
- `Maliyet Eksikleri` sayfasindaki `Maliyet` ve `Ambalaj Gideri` kolonlari doldurulduktan sonra Urunler > Maliyet Guncelle import akisi ile geri yuklenebilir
- Urunler maliyet import'u COGS yaninda ambalaj maliyeti kolonlarini da guncelleyebilir

Bu maddeler tamamlanmadan tum kullanicilar icin rollout yapilmasi onerilmez.
