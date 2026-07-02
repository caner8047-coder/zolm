# Rakip Analizi: Melontik, SellerIQ ve ZOLM Karsilastirmasi

Tarih: 19 Haziran 2026  
Kapsam: Melontik dashboard/demo, Melontik ana sayfa/fiyatlandirma; SellerIQ landing, demo dashboard ve fiyatlandirma; ZOLM mevcut repo ve Kar Merkezi fazlari.

## 1. Arastirma Yontemi ve Kaynaklar

Incelenen kaynaklar:

- Melontik dashboard/demo: https://melontik.com/dashboard
- Melontik ana sayfa: https://melontik.com/
- Melontik fiyatlandirma: https://melontik.com/pricing
- Melontik rapor sayfasi denemesi: https://melontik.com/reports
- SellerIQ ana sayfa/landing: https://selleriq.co/
- SellerIQ demo dashboard: https://selleriq.co/yeni-dashboard
- SellerIQ fiyatlandirma: https://selleriq.co/fiyatlandirma
- ZOLM plan ve kod referanslari:
  - `docs/zolm-rakip-odakli-gelistirme-plani-2026-06-16.md`
  - `config/marketplace.php`
  - `routes/web.php`
  - `app/Livewire/*Marketplace*`
  - `app/Services/Marketplace/*`

Yontem:

- Sayfalar Jina Reader, ham HTML ve render edilmis Chrome/Playwright gorunumuyle incelendi.
- Melontik ve SellerIQ sayfalari Next.js/JS agirlikli oldugu icin yalniz metin okuyucu yeterli olmadi; gercek tarayici render metni esas alindi.
- Melontik `/dashboard` dogrudan login akisi gosterse de `Demo Hesabi Incele` ile demo moduna gecilebildi.
- SellerIQ `/yeni-dashboard` dogrudan acildiginda login gorunumu verdi; ana sayfadaki `Demo Izle` aksiyonu demo dashboard'u acti.
- Bulgular public/demo yuzey gozlemidir; gercek musteri hesabi ve kapali admin ekranlari incelenmedi.

## 2. Yonetici Ozeti

Melontik, urunlesmis ve kapsamli bir pazaryeri finansal analiz paketi gibi konumlaniyor. En guclu tarafi; dashboard, masraf kirilimi, kampanya/fiyat analizi, rapor mailleri, uyarilar, hakediş/desi kontrolu ve demo/onboarding turunu tek urun algisinda sunmasi. Trendyol merkezli baslasa da UI icinde Hepsiburada secimi de gorunuyor.

SellerIQ daha dar ama cok net bir odakla ilerliyor: Trendyol saticisina "gercek karini saniyeler icinde goster, zarar eden urunu aninda isaretle, kargo/desi ve komisyonu kontrol et" vaadi veriyor. Demo dashboard sade, tablo odakli ve hizli anlasiliyor. Ayrica fiyat sayfasinda "Chrome Uzantisi erisimi" ayri bir rekabet farki olarak gorunuyor.

ZOLM, teknik ve operasyonel derinlikte iki rakibin de onune gecebilir durumda: coklu pazaryeri, Excel fallback, V2 API omurgasi, kargo, iade, kampanya, risk, rapor ve muhasebe katmanlari zaten genis. Ancak rakiplerin en guclu yani "ilk 60 saniyede deger gostermek". ZOLM tarafinda Kar Merkezi fazlari buyuk olcude tamamlanmis olsa da public/demo deneyimi, pazarlama sayfasi, urun turu, Chrome/Trendyol panel ici yardimci katman ve fiyatlandirma anlatimi rakipler kadar disariya donuk degil.

## 3. Melontik Detayli Inceleme

### 3.1. Konumlandirma

Melontik kendini pazaryeri karliligini kontrol altina alan finansal analiz araci olarak konumluyor. Ana mesajlar:

- Pazaryerlerinde karli satis yapma.
- Trendyol magazasini API ile baglama.
- Urun maliyetlerini girme.
- Siparis, komisyon, iade, kargo, vergi ve giderleri otomatik isleme.
- Net kari ve karar aksiyonlarini tek panelde gosterme.

Ana sayfa akisi uclu onboarding olarak kurulmus:

1. Magazayi bagla.
2. Maliyetleri gir.
3. Karliligi yonet.

Bu anlatim cok net. Kullaniciya "neden variz?" ve "ilk ne yapacagim?" sorularini hemen cevapliyor.

### 3.2. Dashboard ve KPI Yetkinlikleri

Melontik demo dashboard'da gorulen ana metrikler:

- Toplam ciro.
- Maliyeti olan ciro.
- Brut kar tutari.
- Net kar.
- Net kar / satis fiyati orani.
- Net kar / urun maliyeti orani.
- Tarih araligi filtreleri.
- Platform secimi: Trendyol ve Hepsiburada gorundu.
- Ulke/bolge secimi ve mikro ihracat dahil bolge filtresi.
- Bugun, dun, hafta, son gun araliklari gibi hizli tarih secimleri.

Ozellikle "maliyeti olan ciro" ayrimi cok degerli. Kullanici toplam ciroyu gorurken, kar hesabinin ne kadarinin gercek maliyet verisine dayandigini de fark ediyor. ZOLM'de bu mantik Kar Merkezi hazirlik rozetleriyle var, fakat dashboard dilinde daha gorunur hale getirilmeli.

### 3.3. Masraf Kalemi Derinligi

Melontik masraf kiriliminda su kalemleri gorundu:

- Urun maliyeti.
- Komisyon.
- Kargo ucreti.
- Hizmet bedeli.
- Uluslararasi hizmet bedeli.
- Uluslararasi operasyon bedeli.
- Stopaj kesintisi.
- Net KDV.
- Reklam harcamasi.
- Ceza.
- Erken odeme kesintisi.
- Diger faturalar.
- Ekstra maliyet.

Bu kirilim Melontik'in en guclu taraflarindan biri. Kullanici sadece "kar" degil, karin nerede eridigini goruyor. Reklam, ceza, erken odeme ve diger fatura gibi kalemlerin dashboard seviyesinde anlatilmasi ZOLM icin de kritik.

### 3.4. Performans ve Analitik

Melontik demo dashboard'da su analitik bloklar gorundu:

- Kar performansi grafigi.
- Cirodan net kara inen ara adimlar:
  - ciro,
  - kargo ucreti dusulmus tutar,
  - pazaryeri masrafi dusulmus tutar,
  - vergi dusulmus tutar,
  - urun maliyeti dusulmus tutar.
- Urun metrikleri:
  - net satis adedi,
  - urun basina satis tutari,
  - urun basina ortalama kar,
  - urun basina ortalama kargo maliyeti,
  - ortalama komisyon orani,
  - ortalama indirim orani.
- Siparis metrikleri:
  - siparis sayisi,
  - siparis basina satis tutari,
  - siparis basina ortalama kar,
  - siparis basina ortalama kargo maliyeti.
- Iade metrikleri:
  - iade orani,
  - toplam iade maliyeti,
  - iade kargo zarari,
  - yurt disi operasyon bedeli.
- Reklam metrikleri:
  - toplam reklam harcamasi,
  - reklam bakiyesi,
  - brandcenter harcamasi,
  - influencer kesintisi,
  - reklam kar indeksi,
  - reklam ciro indeksi.

Bu, sadece muhasebe degil "yonetim muhasebesi" hissi veriyor. ZOLM Kar Merkezi bu yonde ilerliyor; fakat Melontik'teki ara adimli "cirodan net kara waterfall" anlatimi ZOLM'de daha belirginlestirilebilir.

### 3.5. Modul Haritasi

Melontik demo sol menude gorulen moduller:

- Dashboard.
- Canli Performans.
- Promosyon Karlilik Analizi.
- Urun Komisyon Tarifesi.
- Plus Komisyon Tarifesi.
- Avantajli Urun Etiketi.
- Flash Urunler.
- Indirimler.
- Kampanyalar.
- Raporlar.
- Siparis Karlilik Analizi.
- Urun Karlilik Analizi.
- Kategori Karlilik Analizi.
- Iade Zarar Analizi.
- Reklam Karlilik Analizi.
- Kampanya Karlilik Analizi.
- Kar Marji Listesi.
- Buybox.
- Urun Fiyatlandirma.
- Urun Ayarlari.
- Uyari Sayfasi.
- Hakediş & Desi Kontrolu.
- Ayarlar.
- Yenilikler.
- Hepsiburada Katalog Gelistirmesi.

Bu menu, Melontik'in pazaryeri kar zekasini cok sayida dikey analiz sayfasina boldugunu gosteriyor. ZOLM'de benzer fonksiyonlar var, ancak daha genis operasyon ailesinin icine dagilmis durumda. ZOLM icin risk, kullanicinin "kar icin hangi sayfaya gidecegim?" sorusunda kaybolmasi.

### 3.6. Kampanya ve Fiyatlandirma

Melontik ana sayfa ve fiyat sayfasinda su yetenekler vurgulaniyor:

- Promosyon karlilik analizi.
- Plus, mikro ihracat, kategori kampanyalari ve indirim kurgularinda kar hesaplama.
- Haftalik komisyon tarifeleri.
- Avantajli etiketler.
- Flash teklifler.
- Katalog kar marji listesi.
- Fiyat olusturma motoru.
- Hedef kar tutarina gore otomatik fiyat onerisi.
- Yeni fiyati pazaryerine gonderme iddiasi.

ZOLM'de Tariff Optimizer, Plus, Badge, Flash, Basket Discount ve Kampanya Karar Merkezi bu alanda cok guclu bir temel sagliyor. Melontik'in farki, bunu pazarlama dilinde tek hikaye olarak anlatmasi.

### 3.7. Uyari, Hakediş ve Desi

Melontik su riskleri one cikariyor:

- Zararina satis.
- Minimum kar marji altina dusen siparis.
- Yanlis kampanya kurgusu.
- Indirim cakismalari.
- Hatali komisyon veya kargo kesintisi.
- Eksik odeme.
- Hakediş kontrolu.

ZOLM'de `MarketplaceSettlementAudit`, `MarketplaceRiskCenter` ve bildirim merkezi bu kume icin teknik olarak guclu. Rakipten alinacak ders, bu riskleri dashboard ve onboarding dilinde daha basit, daha panic-free ama cok net gostermek.

### 3.8. Rapor Mailleri

Melontik ana sayfada rapor mailleri icin su vaatleri veriyor:

- Gunluk karlilik ozeti.
- Aylik finansal rapor.
- Haftanin en karli urunleri.
- Kritik kar marji uyarilari.
- Aylik reklam performans raporu.
- Iade raporu.

ZOLM'de `MarketplaceReportDigestService` ve `MarketplaceReportDigestSettings` bu ihtiyaci karsiliyor. ZOLM avantaji, rapor icerigini Kar Merkezi, Risk Merkezi ve Kampanya Karar Merkezi ile birlestirebilmesi.

### 3.9. Fiyatlandirma

Melontik fiyat sayfasinda yillik planlar goruldu:

- Starter: 0-1.000 aylik siparis, 10.000 urun, 599 TL/ay.
- Business: 1.000-5.000 aylik siparis, 20.000 urun, 999 TL/ay.
- Enterprise: 5.000-30.000 aylik siparis, 30.000 urun, 1.999 TL/ay.
- Ozellik seti planlar arasinda buyuk olcude ayni gorunuyor.
- Yillik planda indirim vurgusu var.

Ozellik listesinde:

- Magaza, siparis ve urun karlilik verileri.
- Avantajli urun etiketi karlilik analizleri.
- Urun komisyon tarifesi karlilik analizleri.
- Flash teklif analizleri.
- Kampanya analizleri.
- Katalog kar marji listesi.
- Fiyat olusturma motoru.
- Uyari bildirimleri.
- Hakediş kontrolu.

## 4. SellerIQ Detayli Inceleme

### 4.1. Konumlandirma

SellerIQ kendini Trendyol saticilari icin net kar, komisyon ve kargo analiz platformu olarak konumluyor. Mesaj cok keskin:

- Ciro yapmak kolay, gercek kar belirsiz.
- Saticilarin buyuk bolumu zarar ettigini fark etmiyor.
- Trendyol magazasina baglanir.
- Kargo, komisyon ve gizli giderleri ayiklar.
- Net kari saniyeler icinde gosterir.

SellerIQ'nun dili Melontik'e gore daha yalın, daha agresif ve daha acquisition odakli.

### 4.2. Landing Deneyimi

SellerIQ landing sayfasinda one cikanlar:

- Kredi karti gerekmeden 30 gun ucretsiz deneme.
- 2 dakikada kurulum.
- 169+ aktif satici iddiasi.
- "Kaos vs kontrol" karsilastirmasi.
- 3 adim:
  1. ucretsiz hesap,
  2. Trendyol magazasi baglama,
  3. kar analizi.
- WhatsApp destek.

SellerIQ, hedef pazari dar tuttugu icin mesajini cok hizli anlatiyor. ZOLM cok daha genis oldugu icin ayni sadeligi yakalamak daha zor, ama Kar Merkezi icin ayri landing/demo akisi bu acigi kapatabilir.

### 4.3. Demo Dashboard

Ana sayfadaki `Demo Izle` aksiyonu SellerIQ demo dashboard'u acti. Dogrudan `/yeni-dashboard` adresi ise login ekranina yonlendiriyor.

Demo dashboard'da gorulen ust metrikler:

- Bugun, dun, son 30 gun ve ozel aralik kartlari.
- Satis.
- Net kar.
- Siparis/adet.
- Iade/tutar/zarar.
- Tahmini hakediş.
- Kar/satis orani.
- Kar/maliyet orani.
- Net KDV.
- Gizli giderler: reklam, ceza, yanlis urun, diger faturalar.
- Excel indir.

Dashboard altinda siparis/urun tablosu var. Tablo kolonlari:

- Platform.
- Barkod.
- Urun adi.
- Siparis numarasi.
- Adet.
- Iade.
- Tutar.
- Urun maliyeti.
- Net kar.
- Kar / satis fiyati.
- Kar / urun maliyeti.
- Kargo.
- Komisyon.
- Komisyon orani.
- Hizmet bedeli.
- Stopaj.
- Uluslararasi hizmet bedeli.
- Net KDV.
- Siparis tarihi.

Bu tablo urun ve siparis bazli kar acikligini cok guclu gosteriyor. "Maliyet girilmedi" satirlari da veri hazirlik sorununu dogrudan gorunur yapiyor.

### 4.4. Ozellik Seti

SellerIQ landing ve fiyat sayfasinda gorulen ozellikler:

- Gercek net kar hesabi.
- Komisyon, kargo, KDV ve stopaj dahil hesaplama.
- Zarar eden urunleri aninda isaretleme.
- Kargo faturasi ve hatali desi kontrolu.
- Otomatik Trendyol API senkronizasyonu.
- Komisyon tarifesi hesaplama.
- Flash teklif karlilik analizi.
- Kampanya karlilik analizi.
- Katalog kar marji listesi.
- Chrome Uzantisi erisimi.
- Fiyat degisince kar aninda guncelleme.
- Komisyon araliklarini otomatik tanima.
- Urun maliyetini SellerIQ'dan cekme.

Chrome uzantisi iddiasi onemli. Bu, Trendyol panelinden cikmadan karar alma deneyimi saglayabilir. ZOLM'de su anda benzer bir browser extension veya Trendyol panel ici overlay gorunmuyor.

### 4.5. Fiyatlandirma

SellerIQ fiyat sayfasinda iki ana plan gorundu:

- Buyuyen Magaza:
  - 0-3.000 aylik siparis.
  - Yillik abonelikte 499 TL/ay.
  - Aylik fiyat 599 TL/ay.
- Guclu Magaza:
  - 3.000-10.000 aylik siparis.
  - Yillik abonelikte 1.198 TL/ay.
  - Aylik fiyat 1.598 TL/ay.

Her iki planda da tum ozelliklerin acik oldugu belirtiliyor. Bu fiyatlandirma Melontik'e gore daha sade ve daha giris dostu duruyor.

## 5. ZOLM Mevcut Durum Ozeti

Repo ve plan dosyasina gore ZOLM'de rakip analizi icin dogrudan ilgili ana moduller:

- `/marketplace-overview`: genel pazaryeri ozet ve onboarding rehberi.
- `/marketplace-profit-center`: Kar Merkezi.
- `/marketplace-pricing-simulator`: authenticated fiyat simulatörü.
- `/tools/trendyol-kar-hesaplama`: public Trendyol kar hesaplama araci.
- `/marketplace-settlement-audit`: hakediş, desi ve kesinti kontrolu.
- `/campaigns/decision-center`: kampanya karar merkezi.
- `/marketplace-risk-center`: risk merkezi.
- `/marketplace-report-digests`: otomatik raporlar.
- `/marketplace-orders`: siparis V2.
- `/marketplace-finance`: finans V2.
- `/marketplace-products`: urun/maliyet yonetimi.
- `/marketplace-integrations`: coklu pazaryeri entegrasyonlari.
- `/marketplace-matching-center`: urun/listing eslestirme.

Feature flag seti:

- `MARKETPLACE_PROFIT_CENTER_ENABLED`
- `MARKETPLACE_ONBOARDING_GUIDE_ENABLED`
- `PUBLIC_TRENDYOL_PROFIT_TOOL_ENABLED`
- `MARKETPLACE_PRICING_SIMULATOR_ENABLED`
- `MARKETPLACE_SETTLEMENT_AUDIT_ENABLED`
- `MARKETPLACE_CAMPAIGN_DECISION_CENTER_ENABLED`
- `MARKETPLACE_RISK_CENTER_ENABLED`
- `MARKETPLACE_REPORT_DIGEST_ENABLED`

Servis/test karsiliklari:

- `MarketplaceProfitCenterQueryService`
- `MarketplaceCostBreakdownService`
- `MarketplaceVatEffectService`
- `MarketplaceWithholdingEffectService`
- `MarketplacePricingSimulationService`
- `MarketplaceSettlementAuditQueryService`
- `CampaignDecisionCenterQueryService`
- `MarketplaceRiskSignalService`
- `MarketplaceReportDigestService`
- `MarketplaceOnboardingGuideService`
- ilgili feature testleri ve export testleri

ZOLM'un stratejik avantaji:

- Coklu pazaryeri mimarisi: Trendyol, Hepsiburada, N11, Pazarama, Amazon, Ciceksepeti, Koctas, WooCommerce, Shopify omurgasi.
- Excel fallback + API V2 ayni kar dogruluk motoruna akabilir.
- Kargo, iade, CRM, uretim ve operasyon modulleri ayni urun icinde.
- Kampanya modulleri rakiplerden daha genis.
- Riskler kalici durum, erteleme, cozum ve bildirim tercihleriyle yonetilebiliyor.
- Otomatik raporlar Kar Merkezi + Risk Merkezi + Kampanya Merkezi verisini tek mail payload'inda birlestirebiliyor.
- Hakediş/desi kontrolunde itiraz paketi Excel export'u var.

ZOLM'un zayif kalan tarafi:

- Public/demo pazarlama deneyimi rakipler kadar net degil.
- Plan dosyasinda Faz 0-3 hala kapanis diliyle isaretlenmemis.
- Chrome extension veya pazaryeri panel ici yardimci yok.
- Kar Merkezi mesajlari teknik olarak guclu, fakat rakiplerdeki kadar pazarlama/ilk deger diliyle paketlenmemis.
- Maliyet girilmedi, maliyeti olan ciro, gizli giderler gibi terimler daha vurucu dashboard KPI'lari olarak one alinabilir.

## 6. Ozellik Karsilastirma Matrisi

| Alan | Melontik | SellerIQ | ZOLM |
| --- | --- | --- | --- |
| Ana odak | Pazaryeri finansal analiz ve kar yonetimi | Trendyol net kar ve zarar tespiti | Coklu pazaryeri kar, finans, operasyon ve risk merkezi |
| Platform kapsami | Trendyol, demo UI'da Hepsiburada secimi de gorundu | Public sayfalarda Trendyol odakli | Trendyol, Hepsiburada, N11, Pazarama, Amazon, Ciceksepeti, Koctas, WooCommerce, Shopify |
| Dashboard KPI | Ciro, maliyeti olan ciro, brut/net kar, kar oranlari | Bugun/dun/son 30 gun satis, net kar, hakediş, iade, KDV | Kar Merkezi KPI, masraf kirilimi, risk ozeti, tarih/magaza/pazaryeri filtreleri |
| Masraf kirilimi | Cok detayli: komisyon, kargo, hizmet, stopaj, KDV, reklam, ceza, erken odeme, diger | Daha sade ama etkili: komisyon, kargo, KDV, stopaj, gizli giderler | `MarketplaceCostBreakdownService` ve snapshot taksonomisi var; UI dilinde daha da one alinmali |
| Siparis bazli kar | Var | Demo tabloda cok net var | V2 siparis, profit snapshot, Kar Merkezi ledger |
| Urun bazli kar | Var | Urun/siparis tablosu ve katalog kar marji listesi | Urun yonetimi, maliyet, listing, Kar Merkezi ve simulatör |
| Maliyet eksigi gorunurlugu | Maliyeti olan ciro ile guclu | `Maliyet Girilmedi` satir seviyesinde gorunur | Onboarding rehberi ve cost readiness var; KPI diline daha cok tasinmali |
| KDV/stopaj | Net KDV ve stopaj masraf kalemi | KDV/stopaj kolonlari ve KPI | `MarketplaceVatEffectService`, `MarketplaceWithholdingEffectService` |
| Mikro ihracat | Bolge/ulke secimi ve mikro ihracat filtreleri gorundu | Uluslararasi hizmet bedeli kolonu var | Simulatör ve snapshot kurallari var; UI'da rakip kadar gorunur olmayabilir |
| Kampanya analizi | Cok guclu: Plus, kategori, flash, avantajli etiket, indirim | Flash ve kampanya analizi | ZOLM cok guclu: mevcut kampanya modulleri + Campaign Decision Center |
| Fiyat simulatörü | Fiyat olusturma motoru, hedef kar ve pazaryerine fiyat gonderme iddiasi | Komisyon tarifesi ve Chrome uzantisi ile anlik kar | Public Trendyol araci + authenticated simulator + hedef fiyat |
| Hakediş/desi kontrolu | Var | Var | Settlement Audit, toleranslar, itiraz XLSX, finance/cargo deep link |
| Risk/uyari | Uyari sayfasi ve bildirimler | Zarar urunu kirmizi bayrakla isaretleme | Risk Center, risk state, erteleme/cozum, bildirim tercihleri |
| Otomatik rapor | Rapor mailleri vurgusu cok guclu | Public sayfada belirgin degil | Report Digest: gunluk/haftalik mail, run history |
| Onboarding | 3 adim + demo tur + nasil yapilir turlari | 3 adim + 30 gun deneme + demo dashboard | Onboarding Guide var; demo/tur deneyimi guclendirilmeli |
| Public acquisition | Demo hesap, referanslar, fiyatlandirma, QNB is birligi | Cok sade landing, demo, fiyatlandirma, WhatsApp | Public Trendyol kar araci var; tam landing/demoda geride |
| Chrome uzantisi | Gozlenmedi | Fiyat sayfasinda var | Yok |
| Export | Raporlar vurgulu | Excel indir var | Cok guclu Excel export standardi ve cok sayfali paketler |
| Teknik guvence | Publicten gorulmez | Publicten gorulmez | Test paketi, feature flag, scheduler, queue, fallback, migration disiplini |

## 7. ZOLM Icin Stratejik Yorum

ZOLM'un rakipleri gecme potansiyeli en yuksek alan "tek pazaryeri kar araci" degil; "coklu pazaryeri + operasyon + finans + risk + kampanya + iade + kargo" butunlesik karar merkezi.

Melontik ve SellerIQ, saticinin ilk acisini cok iyi yakaliyor:

- "Sattim ama kazanamadim."
- "Ciro var ama kar nerede?"
- "Hangi urun zarar ettiriyor?"
- "Kargo/desi veya komisyon beni yiyor mu?"
- "Fiyat dusurursem zarar eder miyim?"

ZOLM bu sorularin teknik cevabini verebiliyor, hatta bazi alanlarda daha derin verebiliyor. Fakat rakiplerin avantajli oldugu nokta, cevabi urun deneyimi ve pazarlama anlatiminda cok hizli gostermeleri.

## 8. Rakiplerden Alinacak En Onemli Dersler

### 8.1. Melontik'ten Alinacaklar

- Dashboard'a "maliyeti olan ciro" gibi veri guvenilirligi KPI'lari eklenmeli veya daha gorunur yapilmali.
- Masraf kalemi sozlugu kullanici diliyle anlatilmali:
  - reklam,
  - ceza,
  - erken odeme,
  - diger faturalar,
  - iade kargo zarari,
  - hizmet bedeli,
  - net KDV.
- Cirodan net kara inen waterfall deneyimi guclendirilmeli.
- Rapor mailleri pazarlama ve onboarding icinde daha cok vurgulanmali.
- "Nasil yapilir" sanal tur sistemi ZOLM modullerine uyarlanabilir.
- Referans/testimonial ve fiyatlandirma sayfasi daha urunlesmis hale getirilmeli.
- Mikro ihracat ve bolge filtreleri Kar Merkezi'nde gorunur fark olarak islenmeli.

### 8.2. SellerIQ'dan Alinacaklar

- "Gercek kar" mesaji cok daha keskin verilmeli.
- Demo dashboard, login bariyerini asmadan ilk degeri gostermeli.
- Zarar eden urun/siparis kirmizi bayrak diliyle cok net isaretlenmeli.
- Maliyet girilmedi satirlari tablo icinde dogrudan gorunmeli.
- Chrome uzantisi veya Trendyol paneli uzerinden yardimci fiyat/kar katmani arastirilmali.
- Fiyatlandirma sade tutulmali: ozellik farki yerine siparis hacmi farki.
- "2 dakikada kurulum, kredi karti gerekmez, 30 gun deneme" benzeri acquisition metni ZOLM public aracinda test edilmeli.

## 9. ZOLM Icin Oncelikli Aksiyon Plani

### P0: Dokuman ve Urun Kapanisi

- `docs/zolm-rakip-odakli-gelistirme-plani-2026-06-16.md` icinde Faz 0-3 kapanis notlari tamamlanmali.
- Kar Merkezi icin nihai kabul durumu ve kalan saha dogrulamalari ayrilmali.
- Canliya alma checklist'i ile rakip plan dokumani ayni feature flag setini gostermeli.

### P1: Ilk Deger ve Demo Deneyimi

- ZOLM Kar Merkezi icin demo data/seed akisi veya guvenli demo modu tasarlanmali.
- Public Trendyol kar hesaplama araci landing CTA ile desteklenmeli.
- `MarketplaceOverview` icindeki onboarding rehberi bir "ilk 5 dakika" urun turuna donusturulebilir.
- Dashboard'a "maliyeti olan ciro", "maliyet eksigi ciro", "gizli giderler", "kar eriten ilk 5 neden" gibi net KPI'lar eklenmeli.

### P2: Rakiplerden Ayrisan Ozellikleri One Cikarma

- Coklu pazaryeri kapsami public anlatimda one alinmali.
- Excel fallback + API birlikte calisma rakiplerden ayrisan bir guven unsuru olarak anlatilmali.
- Hakediş/desi kontrolunde itiraz paketi export'u pazarlama diliyle one cikarilmali.
- Kampanya Karar Merkezi, Melontik/SellerIQ'daki parca parca kampanya analizlerinden daha ust bir karar merkezi olarak konumlandirilmali.
- Iade ve kargo operasyon derinligi "kar kacagi yakalama" hikayesine baglanmali.

### P3: Yeni Firsat

- Chrome extension fizibilitesi:
  - Trendyol panelinde fiyat girildiginde ZOLM maliyet ve komisyonla anlik kar gosterme.
  - Urun sayfasinda maliyet eksigi ve zarar riski uyarisi.
  - ZOLM public calculator'dan authenticated simulatöre gecis.
- Ajans modu:
  - Melontik referanslarinda ajans kullanimi vurgulaniyor.
  - ZOLM cok firmali mimarisiyle ajans/portfoy yonetimi icin daha uygun olabilir.
- AI destekli "bugun ne yapmaliyim" aksiyon ozetleri:
  - Risk Merkezi sinyalleri,
  - Kampanya firsatlari,
  - Hakediş farklari,
  - Maliyet eksikleri,
  - fiyat aksiyonlari tek gunluk operator planina donusturulebilir.

## 10. Sonuc

Melontik en kapsamli ve olgun gorunen rakip. SellerIQ en sade, hizli ve acquisition odakli rakip. ZOLM ise teknik kapsam, coklu pazaryeri, operasyon derinligi ve testli mimari acisindan daha buyuk bir platform olma sansina sahip.

ZOLM'un kazanmasi icin ana strateji su olmali:

1. Rakiplerin net kar, zarar, gizli gider ve fiyatlandirma mesajini ayni yalınlikta vermek.
2. Bunun uzerine ZOLM'un coklu pazaryeri, Excel fallback, kargo/iade/kampanya/risk derinligini koymak.
3. Demo ve public acquisition deneyimini guclendirmek.
4. Kar Merkezi'ni sadece bir dashboard degil, saticinin gunluk karar merkezi yapmak.

