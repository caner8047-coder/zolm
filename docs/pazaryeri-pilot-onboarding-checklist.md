# Pazaryeri Pilot Onboarding Checklist

Bu dokuman, ilk pilot magaza canliya alinmadan once operatorun izleyecegi net uygulama sirasini tanimlar.

Amaç:

- magaza bilgilerini eksiksiz toplamak
- entegrasyon ekranina dogru girmek
- smoke test turunu sirasiyla calistirmak
- sonucu yorumlayip pilot rollout'a hazir olmak

## 1. Pilot Magaza Secimi

Ilk pilot icin magaza secilirken su kriterler tercih edilir:

- siparis hacmi orta seviyede olsun
- urun/stok kodu dolulugu temiz olsun
- webhook kurulumu yapilabilir olsun
- operasyon ekibi magazayi taniyor olsun
- ilk gun kritik kampanya yogunlugu olmasin

## 2. Zorunlu Bilgiler

Her pilot magaza icin su bilgiler tek yerde hazir olmalidir:

- firma adi
- vergi numarasi
- magaza adi
- pazar yeri / altyapi tipi
- `seller_id` veya kanal karsiligi
- `api_key`
- `api_secret`
- `api_base_url`
- `webhook_secret`
- gerekiyorsa ek auth alani

WooCommerce icin ek:

- site kok URL
- consumer key
- consumer secret
- webhook secret

Shopify icin ek:

- store URL
- Admin API access token
- app secret key

Hepsiburada icin ek:

- `merchantId`
- `serviceKey`
- `User-Agent`

## 3. Entegrasyon Ekrani Girisi

Sirayla:

1. firma olustur veya mevcut firmayi sec
2. magaza olustur
3. provider sec
4. credential alanlarini gir
5. `Bağlantıyı doğrula`
6. capability rozetlerini kontrol et

Kontrol:

- readiness failure olmamali
- warning varsa not alinmali
- provider label ve base URL dogru olmali

## 4. Guvenli Profil Uygulama

Magaza aktif etmeden once ilgili safe profile uygulanir.

Komutlar:

```bash
php artisan marketplace:apply-trendyol-safe-profile --store={id} --dry-run
php artisan marketplace:apply-hepsiburada-safe-profile --store={id} --dry-run
php artisan marketplace:apply-woo-safe-profile --store={id} --dry-run
php artisan marketplace:apply-shopify-safe-profile --store={id} --dry-run
```

Dry-run sonucu uygunsa ayni komut `--dry-run` olmadan tekrar calistirilir.

WooCommerce ve Shopify icin onerilen webhook topic seti de uygulanir:

```bash
php artisan marketplace:apply-recommended-webhook-topics --store={id}
```

## 5. Ilk Smoke Test

Her pilot magazada ilk test `persist` ile yapilir.

Komut:

```bash
php artisan marketplace:smoke-test {store_id} --type=all --hours=24 --preview=2 --persist
```

Gerekirse tek tip test:

```bash
php artisan marketplace:smoke-test {store_id} --type=orders --hours=24 --preview=2 --persist
php artisan marketplace:smoke-test {store_id} --type=products --hours=24 --preview=2 --persist
php artisan marketplace:smoke-test {store_id} --type=finance --hours=24 --preview=2 --persist
```

Siparis odakli hata ayiklama icin:

```bash
php artisan marketplace:smoke-test {store_id} --type=orders --order-number={order_no} --preview=1 --persist
```

## 6. Sonuc Degerlendirme

Smoke test sonrasi su 3 yere bakilir:

1. Kontrol Merkezi
2. Entegrasyonlar
3. diagnostics guidance / diagnostics report

Bakilacak alanlar:

- baglanti basarili mi
- kritik failure var mi
- `order/package/line` eksigi var mi
- `stock_code/barcode` eksigi var mi
- `amount/settlement` eksigi var mi
- warning sayisi kabul edilebilir mi

## 7. Sonuc Karari

### Durum A - Temiz

Kosullar:

- readiness failure yok
- smoke test basarili
- kritik mapping warning yok

Karar:

- webhook kurulumuna gec
- pilot rollout icin hazir

### Durum B - Kismi Sorunlu

Kosullar:

- smoke test calisiyor
- ama warning sayisi yuksek
- mapping fallback gerekli

Karar:

- connector mapping hardening ac
- tekrar smoke test kos

### Durum C - Blokeli

Kosullar:

- baglanti basarisiz
- readiness failure var
- payload yeterli gelmiyor

Karar:

- credential / auth / base URL kontrolu yap
- gerekiyorsa pazaryeri destegi ile ilerle

## 8. Webhook Kaniti

Smoke test gectikten sonra webhook akisi gercek callback ile kanitlanir.

Beklenen:

- webhook geldi
- imza gecti
- event loglandi
- gerekiyorsa `debounced` veya `ignored` mantigi dogru calisti
- sync run olustu

## 9. Pilot Rollout Acilis Karari

Asagidaki 5 madde ayni anda saglanmadan pilot acilmaz:

1. safe profile uygulandi
2. smoke test temiz
3. kritik guidance kalmadi
4. webhook kanitlandi
5. queue + scheduler production'da stabil

## 10. Pilot Sonrasi Ilk 48 Saat Izleme

Yakindan izlenecekler:

- failed sync run
- auth/rate limit hatalari
- webhook `ignored/debounced` oranlari
- esitlenmeyen urun sayisi
- mutabakatsiz siparis sayisi
- push hata orani

## 11. Tek Satir Operasyon Ozeti

Pilot magazayi acmadan once operator kendine su soruyu sorar:

"Bu magaza icin baglanti, safe profile, smoke test, webhook ve kritik mapping uyarilari temiz mi?"

Cevap net evet degilse rollout yapilmaz.
