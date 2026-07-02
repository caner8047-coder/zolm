# Pazaryeri Entegrasyonu Aksiyon Listesi - 25.03.2026

Bu dokuman, [Pazaryeri Entegrasyon Durum Raporu - 25.03.2026](./pazaryeri-entegrasyon-durum-raporu-2026-03-25.md) uzerinden cikarilan uygulanabilir backlog listesidir.

Amac:

- neyin bittigini tekrar tartismamak
- neyin bloke oldugunu net ayirmak
- dis bagimlilik bekleyen isleri ayri tutmak
- hemen baslanabilecek adimlari netlestirmek

## Genel Durum

Bugun itibariyla en dogru okuma su:

- cekirdek mimari kuruldu
- operator tooling kuruldu
- V2 moduller calisiyor
- gercek production guveni icin canli credential ve smoke test eksik

Bu nedenle kalan isler "yeni mimari kurma" degil, "kanitlama, sertlestirme ve rollout" agirliklidir.

## Uygulama Stratejisi

Kalan isleri uc gruba ayirmak gerekir:

### P0

Canliya cikmadan once mutlaka tamamlanacaklar

### P1

Pilot sonrasinda ilk saglamlastirma turunda tamamlanacaklar

### P2

Genisleme ve ikinci dalga connector fazi

---

## P0 - Canliya Cikis Oncesi Zorunlu Liste

### P0.1 - Credential Paketlerini Topla

Durum: Dis bagimlilik

Kanallar:

- Trendyol
- Hepsiburada
- WooCommerce
- Shopify

Her biri icin toplanacak minimum alanlar:

- magaza / firma eslestirmesi
- `seller_id` veya platform karsiligi
- `api_key`
- `api_secret`
- `api_base_url`
- `webhook_secret`
- gerekiyorsa ek auth alanlari

Tamamlanma kriteri:

- bilgilerin `Entegrasyonlar` ekranina girilebilir halde hazir olmasi

### P0.2 - Gercek Smoke Test Turu

Durum: Dis bagimlilik

Sirayla uygulanacak:

1. Trendyol
2. WooCommerce
3. Shopify
4. Hepsiburada

Temel komut:

```bash
php artisan marketplace:smoke-test {store_id} --type=all --hours=24 --preview=2 --persist
```

Tamamlanma kriteri:

- baglanti testi basarili
- readiness failure yok
- kritik mapping warning yok veya kabul edilebilir seviyede
- smoke test kaydi `integration_sync_runs` icinde `smoke_test` olarak gorunuyor

### P0.3 - Webhook Uctan Uca Kaniti

Durum: Dis bagimlilik

Kanitlanacak zincir:

- marketplace callback geldi
- imza dogrulandi
- event loglandi
- duplicate kontrolu dogru calisti
- queue job dispatch edildi
- ilgili sync run olustu

Tamamlanma kriteri:

- her aktif connector icin en az bir gercek webhook callback gozlemi

### P0.4 - Mapping Hardening

Durum: Smoke test sonrasina bagli

Ozellikle kontrol edilecek alanlar:

- siparis kimlikleri
  - `external_order_id`
  - `package_id`
  - `line_id`
- urun kimlikleri
  - `stock_code`
  - `barcode`
  - listing id
- finans alanlari
  - `amount`
  - `settlement_date`
  - komisyon
  - kargo
  - stopaj

Tamamlanma kriteri:

- smoke testte cikan kritik warning'lerin kapatilmasi
- gerekiyorsa connector bazli fallback alanlarin eklenmesi

### P0.5 - Pilot Rollout Provası

Durum: Iceride baslanabilir

Uygulanacak:

- production `.env` kontrolu
- migration provasi
- queue worker ve scheduler kontrolu
- feature flag kontrolu
- rollback planinin teyidi

Referans:

- [Pazaryeri Entegrasyonu Canlıya Alma Checklist](./pazaryeri-canliya-alma-checklist.md)
- [Pazaryeri Smoke Test Karar Agaci](./pazaryeri-smoke-test-karar-agaci.md)

Tamamlanma kriteri:

- tek bir pilot magaza icin kontrollu canliya alma sirasi hazir

---

## P1 - Pilot Sonrasi Ilk Sertlestirme Turu

### P1.1 - Excel Fallback Normalizasyon Dogrulamasi

Durum: Baslandi

Soru:

- Excel fallback verisi yeni normalize projection'lara tutarli akiyor mu?

Bugunki durum:

- legacy operasyon importu icin opsiyonel magaza secimli projection koprusu eklendi
- mevcut eski kayitlari tasimak icin `marketplace:project-legacy-orders` komutu eklendi
- legacy `mp_orders` finans satirlarini yeni ledger'a tasiyan projection koprusu eklendi
- mevcut eski legacy finans kayitlarini tasimak icin `marketplace:project-legacy-financials` komutu eklendi
- `Siparisler V2` ekraninda projection magazasi store filtresinden otomatik on seciliyor ve aday legacy finans satiri onizlemesi gosteriliyor
- `Finans V2` ekraninda legacy projection etkisi ozet karti gosteriliyor
- `Finans V2` ust guidance bandi legacy backlog icin tek tik odak karti gosteriyor
- `Finans V2` icinde legacy backlog ve confirmed etkisi icin ayri filtre/kisa yol mevcut
- `Kontrol Merkezi` ekraninda legacy projection backlog ve kesine donen siparis ozeti gosteriliyor
- `Kontrol Merkezi` ekraninda legacy projection backlog'u magaza bazli kirilimla da izlenebiliyor
- `Kontrol Merkezi` magaza kiriliminda siparis, finans backlog ve confirmed etki icin ayri kisayollar var
- `Kontrol Merkezi` ekranindan legacy backlog CSV disa aktarilabiliyor
- `Entegrasyonlar` ekranindaki magaza kartlari ve hazirlik export'u legacy backlog sinyalini gosteriyor
- `Entegrasyonlar` ekraninda secili magaza icin legacy projection operator paneli var:
  - `Dry-run onizle`
  - `Projection calistir`
  - `Finans backlogu ac`
  - `Confirmed etkisini ac`
  - CLI dry-run / gercek calistirma komutlari
- `Siparisler V2` ust guidance bandi legacy backlog icin tek tik odak karti gosteriyor
- `Siparisler V2` icinde legacy finans projection bolumu artik:
  - `Dry-run onizle`
  - `Legacy finansi V2'ye tasi`
  - son dry-run / projection sonucu ozeti
  - CLI dry-run / gercek calistirma komutlari
- hala saha davranisi dogrulanmali

Kontrol edilecek:

- siparis projection
- urun/listing projection
- finans projection
- duplicate koruma
- profit snapshot uyumu (`net_hakedis -> net_receivable`)

### P1.2 - Hepsiburada Write-side Derinlestirme

Durum: Kismi tamam

Acik kalanlar:

- webhook tarafi
- write-side operasyonlarin canli endpoint kaniti
- paket statu ve benzeri aksiyonlarin genisletilmesi

### P1.3 - Trendyol / Hepsiburada Paket Operasyon Kaniti

Durum: Canli dogrulama bekliyor

Kontrol:

- ortak barkod
- paket statu yazimi
- fatura linki

### P1.4 - Monitoring ve Log Akisi

Durum: Kismi

Ihtiyac:

- failed run trendi
- auth/rate limit trendi
- queue gecikmesi
- webhook hata dagilimi

### P1.5 - Pilot Sonrasi Kural Revizyonu

Durum: Smoke test sonrasina bagli

Ozellikle:

- safe profile defaultlari
- Woo / Shopify webhook topic setleri
- poll araliklari
- debounce sureleri

### P1.6 - Kar Merkezi Rollout Kaniti

Durum: Iceride baslandi

19.06.2026 notu:

- `Veri Hazırlık Rehberi` pilot operator akisini tek ekranda toplar
- `PUBLIC_TRENDYOL_PROFIT_TOOL_ENABLED` public hesaplama aracini ic simulator flag'inden ayirir
- risk sinyali ve otomatik rapor komutlari scheduler'a baglidir, feature flag kapaliyken sessiz uyarir
- Kar Merkezi route feature flag testi eklendi; Kar Merkezi, Fiyat Simulatoru, Hakediş Kontrolü, Risk Merkezi, Otomatik Raporlar, Kampanya Karar Merkezi ve public Livewire middleware sertlestirme paketi 39 test / 683 assertion ile gecti
- Lokal pilot adayinda `store_id=4462` Trendyol `ZEM HOME` icin `marketplace:backfill-profit-snapshots --store=4462 --missing --dry-run` sonucu 0 aday verdi; order-level snapshot kapsami tam.
- Ayni pilot adayinda 1.450 siparisin tamaminda finans ledger eksik gorundu; Kar Merkezi kâri tahmini kalir, kesin finans/settlement kapsamasi henuz yok.
- Maliyet hazirligi pilot adayinda %0: 1.507 satirin 1.502'si eslesmis olsa da 1.502 satir maliyet/ambalaj hazir degil, 5 satir eslesmemis.
- Ilk veri aksiyonu `packaging_cost` ve eksik `cogs` alanlarini toplu tamamlamak; ikinci aksiyon 5 eslesmeyen siparis satirini master urune baglamak; ucuncu aksiyon Trendyol finans event import/sync ile kesin kâra gecmek.
- Kar Merkezi yonetici raporuna `Maliyet Eksikleri` Excel sayfasi eklendi; stok kodu, barkod, etkilenen ciro, eksik COGS/ambalaj ve onerilen aksiyon tek listede verilir.
- `Maliyet Eksikleri` sayfasi `Stok Kodu`, `Barkod`, `Maliyet`, `Ambalaj Gideri` kolonlariyla Urunler > Maliyet Guncelle import akisiyle uyumlu hale getirildi.
- Urunler maliyet import'u artik `Ambalaj`, `Ambalaj Gideri`, `Ambalaj Maliyeti`, `Paketleme Maliyeti/Gideri` ve `Packaging Cost` kolonlarini `packaging_cost` olarak gunceller.
- `MarketplaceProductMatcher` listeleme bagi olmayan siparis satirlarindan guvenli `order-item:*` kanal urun/listing kaydi uretir; boylece satirlar magaza-geneli kor issue yerine Eslestirme Merkezi'nde tek tek aksiyonlanabilir hale gelir.
- Yeni `marketplace:repair-match-issues --store={id}` komutu gecmis eslesmemis siparis satirlarini ayni matcher akisiyle onarir; once `--dry-run` ile aday satir sayisi gorulmelidir.
- Lokal pilot `store_id=4462` icin komut once 5 aday satir gosterdi, gercek calistirmada 5 satir isledi: 4 satira listing bagi olusturdu, 1 satiri master urune otomatik esledi, listing'siz acik issue sayisini 0'a indirdi.
- Pilot sonrasi kalan eslesme aksiyonu 4 satira dustu; bu satirlar artik `product_match_issues` uzerinden listeleme bagli `not_found` kaydi olarak Eslestirme Merkezi'nde cozulebilir.
- Eslesme aday uretimi model kodu ailesi ve urun adi token'lariyla guclendirildi; `ZEMRMS`, `ZEMBNT`, `ZEMBOEY` gibi kod koklerinden aday listesi dolar, ancak fuzzy adaylar otomatik `Onerileni bagla` aksiyonuna acilmaz.
- Pilot `store_id=4462` tekrar onarimindan sonra kalan 4 satirin tamami `candidate_found` durumuna gecti ve 8'er adayla Eslestirme Merkezi'nde manuel karar bekliyor.
- Kar Merkezi pilot olcumu: snapshot eksigi 0, finans bekleyen siparis 1.450, maliyet hazirligi %0; 1.507 satirin 1.503'u master urune bagli ama maliyet/ambalaj eksigi tasiyor, kalan eslesme aksiyonu 4 satir.

Kontrol edilecek:

- tek pilot magazada profit snapshot backfill dry-run sonucu
- `Kâr Merkezi` ciro, maliyet, net kar ve hazirlik rozetleri
- maliyet eksigi olan urunun rehber ve Kar Merkezi icinde gorunmesi
- `marketplace:repair-match-issues --store={id} --dry-run` sonucunda listing'siz eslesme kor noktasi kalmamasi
- fuzzy adaylarin otomatik baglanmadigi, yalniz manuel secimle kapandigi
- `Hakediş Kontrolü`, `Risk Merkezi` ve `Otomatik Raporlar` icin feature flag bazli erisim
- public Trendyol kar hesaplama route'unun sadece `PUBLIC_TRENDYOL_PROFIT_TOOL_ENABLED=true` iken acilmasi

Tamamlanma kriteri:

- pilot magaza icin okuma/yol gosterici ekranlar guvenle acik
- aksiyon ureten veya disariya acik araclar ayri feature flag ile kontrollu
- [Canlıya Alma Checklist](./pazaryeri-canliya-alma-checklist.md) Kar Merkezi adimlariyla guncel

---

## P2 - Ikinci Dalga Genisleme

### P2.1 - Skeleton Connector'lari Gerceklestir

Sirayla:

- N11
- Pazarama
- Amazon
- Ciceksepeti
- Koctas

### P2.2 - Channel-specific Write-side Aksiyonlari Derinlestir

- daha fazla paket aksiyonu
- daha fazla listing operasyonu
- kanal bazli toplu is akislari

### P2.3 - ERP / Muhasebe Derin Entegrasyonu

- ek mutabakat akisları
- muhasebe projection'lari
- harici sistem baglantilari

---

## Hemen Baslayabilecegimiz Isler

Dis credential beklemeden simdi yapilabilecekler:

1. Pilot rollout provasi icin operasyon sirasini sabitlemek
2. Excel fallback ile normalize projection iliskisini test etmek
3. Monitoring ve log aksiyon listesini netlestirmek
4. Pilot magaza onboarding checklist'ini hazirlamak
5. Overview icinde readiness + smoke + legacy projection + guidance birlesik pilot panelini tamamlamak
6. Pilot rollout CSV ile operatör export'unu hazir tutmak

## Blokeli Isler

Asagidaki isler dis veri olmadan tamamlanamaz:

1. Trendyol gercek smoke test
2. Hepsiburada gercek smoke test
3. WooCommerce gercek smoke test
4. Shopify gercek smoke test
5. Gercek webhook callback dogrulamasi
6. Payload bazli son mapping hardening

## Onerilen Uygulama Sirasi

### Adim 1

Pilot rollout provasi ve onboarding checklist

### Adim 2

Excel fallback normalizasyon dogrulamasi

### Adim 3

Credential geldigi anda:

- Trendyol smoke test
- WooCommerce smoke test
- Shopify smoke test
- Hepsiburada smoke test

### Adim 4

Webhook uctan uca kaniti

### Adim 5

Field mapping hardening

### Adim 6

Tek pilot magaza rollout

## Bugunden Itibaren Calisma Karari

Bu backlog'a gore bugunden itibaren en dogru calisma sirası su olmalidir:

1. credential beklemeyen P0/P1 hazirliklarini bitir
2. credential gelir gelmez smoke test turunu calistir
3. smoke test sonucuna gore connector sertlestir
4. pilot rollout yap
5. sonra skeleton connector'lara don
