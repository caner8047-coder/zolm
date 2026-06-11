# ZOLM v0.7 cPanel Yukleme Notu

Bu klasor, `m.zolm.com.tr` canli gecisi icin cPanel uzerinde calistirilacak post-deploy araclarini tutar.

## Paket

Composer/SSH varsa kucuk paket kullanilabilir:

```bash
deploy/artifacts/zolm-v0.7-deploy.zip
```

Composer yoksa veya emin degilsen cPanel icin hazir vendor iceren full paket kullanilmalidir:

```bash
deploy/artifacts/zolm-v0.7-cpanel-full.zip
```

Bu zip cPanel File Manager ile `/home/zolmcomt/m.zolm.com.tr` dizinine yuklenip ayni dizinde acilmalidir. Full paket icinde `.env`, node_modules, local SQL dump, eski vendor backup ve public debug dosyalari yoktur; `vendor/autoload.php` hazir gelir.

## Sira

1. Zip dosyasini `/home/zolmcomt/m.zolm.com.tr` icine yukle.
2. Zip dosyasini ayni dizinde ac.
3. `.env` yoksa script production orneginden olusturur ve durur. DB bilgileri, `APP_KEY`, mail ve pazaryeri credential degerleri doldurulduktan sonra tekrar calistir.
4. Terminal veya SSH ile:

```bash
cd /home/zolmcomt/m.zolm.com.tr
chmod +x deploy/cpanel/after_upload_v07.sh
deploy/cpanel/after_upload_v07.sh
```

Script once `.env` yedegi ve `mysqldump` DB yedegi alir, sonra v0.6 -> v0.7 preflight SQL, migration, cache ve health-check adimlarini calistirir.

## Kritik

- Canli DB yedegi alinmadan `aizemure_v06.sql` dogrudan iceri aktarilmamalidir.
- Webhook adres formati: `https://m.zolm.com.tr/api/webhooks/marketplaces/{provider}/{store_id}`
- Acilis kontrolu: `https://m.zolm.com.tr/login`
- Pazaryeri testleri:

```bash
php artisan marketplace:smoke-test STORE_ID --type=orders --hours=24 --preview=2
php artisan marketplace:smoke-test STORE_ID --type=questions --hours=168 --preview=2
```
