# Production Deploy Notlari

Bu klasor, ZOLM pazaryeri entegrasyon omurgasini production ortamina alirken kullanilacak ornek dosyalari icerir.

## Icerik

- `.env.production.example`
- `supervisor/zolm-queue.conf`
- `supervisor/zolm-scheduler.conf`
- `systemd/zolm-queue.service`
- `systemd/zolm-scheduler.service`
- `systemd/zolm-scheduler.timer`

## Onerilen Kullanim

Iki ornek yaklasim vardir:

1. `Supervisor`
   - queue worker icin `zolm-queue.conf`
   - scheduler icin `zolm-scheduler.conf`
2. `systemd`
   - queue worker icin `zolm-queue.service`
   - scheduler icin `zolm-scheduler.timer`

Ayni sunucuda ikisini birden aktif etmeyin. Tek bir process yonetim stratejisi secin.

## Temel Akis

1. `.env.production.example` dosyasini referans alip production `.env` hazirlayin
2. uygulama dizinini `/var/www/zolm` benzeri hedefe deploy edin
3. artisan cache komutlarini calistirin
4. `php artisan migrate --force` calistirin
5. `php artisan marketplace:health-check` ile ilk saglik kontrolunu alin
6. queue ve scheduler process'lerini secilen yonteme gore aktif edin
7. test magazada `Urun cek > Siparis cek > Finans cek` sirasi ile smoke test yapin
8. feature flag'leri ihtiyaca gore kademeli acin

## Kontrol Komutlari

```bash
php artisan marketplace:health-check
php artisan marketplace:health-check --fail-on-warning
php artisan marketplace:smoke-test 12 --type=orders --hours=24 --preview=2
php artisan marketplace:apply-woo-safe-profile --store=12 --dry-run
php artisan marketplace:apply-woo-safe-profile --store=12
php artisan marketplace:apply-woo-safe-profile --all --dry-run
php artisan marketplace:apply-shopify-safe-profile --store=12 --dry-run
php artisan marketplace:apply-shopify-safe-profile --store=12
php artisan marketplace:apply-shopify-safe-profile --all --dry-run
php artisan marketplace:apply-trendyol-safe-profile --store=12 --dry-run
php artisan marketplace:apply-trendyol-safe-profile --store=12
php artisan marketplace:apply-hepsiburada-safe-profile --store=12 --dry-run
php artisan marketplace:apply-hepsiburada-safe-profile --store=12
php artisan marketplace:apply-recommended-webhook-topics --store=12
php artisan marketplace:apply-recommended-webhook-topics --all --marketplace=woocommerce --dry-run
php artisan marketplace:apply-recommended-webhook-topics --all --marketplace=shopify --dry-run
php artisan marketplace:diagnostics-report --store=12 --type=orders --smoke-only
php artisan marketplace:diagnostics-guidance 1 --store=12 --type=all --smoke-only
php artisan marketplace:repair-failures --type=all --limit=25
php artisan marketplace:repair-failures --store=12 --type=syncs --dry-run
php artisan marketplace:project-legacy-orders 12 --only-unprojected --include-unassigned --dry-run
php artisan marketplace:project-legacy-orders 12 --only-unprojected --include-unassigned
php artisan marketplace:project-legacy-financials 12 --only-unprojected --dry-run
php artisan marketplace:project-legacy-financials 12 --only-unprojected
php artisan queue:restart
php artisan schedule:list
```

## Notlar

- `APP_URL` production domain ile ayni olmali
- webhook URL disaridan HTTPS uzerinden erisilebilir olmali
- queue driver production'da `sync` olmamali
- deploy sonrasi worker process'lerine `queue:restart` sinyali verilmeli
- V2 rollout gerekirse sadece `MARKETPLACE_V2_ENABLED=false` ile tek adimda geri alinabilir
- paneldeki manuel `Siparis cek / Urun cek / Finans cek` aksiyonlari magaza + veri tipi bazinda debounce edilir; ayni magazada kisa surede tekrar tiklama ikinci `integration_sync_runs` kaydi acmaz
- manuel sync korumasi icin `MARKETPLACE_MANUAL_SYNC_DEBOUNCE_SECONDS` ve `MARKETPLACE_MANUAL_SYNC_ACTIVE_RUN_BLOCK_SECONDS` env degerleri production'da acikca ayarlanmalidir
- paneldeki `Fiyat push / Stok push` aksiyonlari listing + push tipi bazinda sakinlestirilir; kuyruktaki ayni push kaydi guncellenir, `processing` durumundaki is icin ikinci kayit acilmaz
- listing push korumasi icin `MARKETPLACE_LISTING_PUSH_DEBOUNCE_SECONDS` ve `MARKETPLACE_LISTING_PUSH_ACTIVE_RUN_BLOCK_SECONDS` env degerleri production'da acikca ayarlanmalidir
- siparis ve paket operasyon butonlari da siparis/paket + aksiyon tipi bazinda debounce edilir; bekleyen ayni aksiyon guncellenir, `processing` durumundaki is icin ikinci kayit acilmaz
- order action korumasi icin `MARKETPLACE_ORDER_ACTION_DEBOUNCE_SECONDS` ve `MARKETPLACE_ORDER_ACTION_ACTIVE_RUN_BLOCK_SECONDS` env degerleri production'da acikca ayarlanmalidir
- Trendyol icin dusuk etkili baslangic profili gerekiyorsa `php artisan marketplace:apply-trendyol-safe-profile --store={id} --dry-run`, sonra ayni komut dry-run olmadan calistirilabilir
- Hepsiburada icin `seller_id` alani `merchantId`, `apiKey` alani `serviceKey`, `extraUser` alani ise `User-Agent`/entegrator kullanicisi olarak dusunulmeli
- Hepsiburada icin dusuk etkili baslangic profili gerekiyorsa `php artisan marketplace:apply-hepsiburada-safe-profile --store={id} --dry-run`, sonra ayni komut dry-run olmadan calistirilabilir
- N11 tarafi su anda guvenli skeleton modundadir; credential alanlari hazirlanabilir ancak resmi endpoint ve auth modeli netlesmeden canli verify/smoke acilmaz
- Koctas tarafi da su anda guvenli skeleton modundadir; credential alanlari hazirlanabilir ancak resmi endpoint ve auth modeli netlesmeden canli verify/smoke acilmaz
- Pazarama tarafi da su anda guvenli skeleton modundadir; credential alanlari hazirlanabilir ancak resmi endpoint ve auth modeli netlesmeden canli verify/smoke acilmaz
- Amazon tarafi da su anda guvenli skeleton modundadir; SP-API region, role ve auth modeli netlesmeden canli verify/smoke acilmaz
- Ciceksepeti tarafi da su anda guvenli skeleton modundadir; credential alanlari hazirlanabilir ancak resmi endpoint ve auth modeli netlesmeden canli verify/smoke acilmaz
- WooCommerce icin `apiKey=consumer key`, `apiSecret=consumer secret`, `api_base_url` ise site kok URL veya dogrudan `/wp-json/wc/v3` base URL olabilir
- mevcut WooCommerce magazalari icin guvenli dusuk etkili profil gerekirse once `php artisan marketplace:apply-woo-safe-profile --store={id} --dry-run`, sonra ayni komut dry-run olmadan calistirilabilir
- mevcut WooCommerce magazalari icin onerilen webhook topic seti tek komutla uygulanabilir: `php artisan marketplace:apply-recommended-webhook-topics --store={id}`
- WooCommerce webhook'lari icin burst/debounce korumasi vardir; kisa surede gelen tekrar event'ler ikinci bir refresh kuyruğu acmaz
- WooCommerce `product.*` webhook'lari siparis yerine urun sync'i tetikler; bu sayede gereksiz order polling yukunden kacınılır
- WooCommerce webhook topic listesi magaza profilinde tutulur; secili topic disindaki event'ler `ignored` olarak loglanir ve sync baslatmaz
- webhook event unique index'i store bazli hale getiren migration eklendi; deploy sonrasi `php artisan migrate --force` calistirilmadan eski unique anahtar uzerinden davranis surer
- `marketplace:repair-failures` komutu push ve siparis aksiyonu onariminda artik `Yeni / Guncellendi / Calisiyordu / Cok yeni` kirilimlarini da raporlar
- Shopify icin `apiKey=Admin API access token`, `apiSecret=app secret key`, `api_base_url` ise magaza kok URL veya dogrudan versioned GraphQL endpoint olabilir
- Shopify finans akisi marketplace hak edisi degil, odeme/gateway transaction ve fee kayitlari uzerinden normalize edilir
- mevcut Shopify magazalari icin guvenli dusuk etkili profil gerekirse once `php artisan marketplace:apply-shopify-safe-profile --store={id} --dry-run`, sonra ayni komut dry-run olmadan calistirilabilir
- mevcut Shopify magazalari icin onerilen webhook topic seti tek komutla uygulanabilir: `php artisan marketplace:apply-recommended-webhook-topics --store={id}`
- Shopify icin de webhook-first akisi onerilir; secili topic seti disindaki event'ler `ignored` olarak loglanir ve sync baslatmaz
- Shopify icin onerilen topic seti siparis, iade, urun ve stok degisimleri ile sinirlidir; fazladan topic acmak gereksiz API ve queue gurultusu uretebilir
- smoke test sonrasi operator karari icin `docs/pazaryeri-smoke-test-karar-agaci.md` ve `docs/pazaryeri-field-mapping-checklist.md` kullanilabilir
- legacy operasyon Excel fallback'i icin `Siparisler V2` ekraninda projection magazasi secilirse yeni importlar ayni anda `channel_orders` yapisina da yazilir
- daha once yuklenmis legacy operasyon siparislerini yeni projection yapisina tasimak icin `marketplace:project-legacy-orders` komutu kullanilabilir
- legacy `mp_orders` finans kayitlarini yeni ledger yapisina tasimak icin `marketplace:project-legacy-financials` komutu kullanilabilir; once `--dry-run` ile aday sayi gorulmeli, sonra projection calistirilmalidir
- bu kopru sayesinde eski muhasebe verisi `Finans V2` ve `order_profit_snapshots` tarafinda da okunabilir hale gelir; ilk pilotta `net_hakedis -> net_receivable` uyumu kontrol edilmelidir
