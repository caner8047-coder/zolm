# ZOLM ERP & On Muhasebe Gelecek Fazlar Yol Haritasi

Bu dokuman, Faz 2-11 kapsaminda arka planda kurulan ERP ve muhasebe altyapisini kullanici ekranlarina tasiyacak, gercek urune donusturecek ve ZOLM Kurumsal Acik Panel UI standartlarina kavuşturacak uzun soluklu yol haritasini tanimlar.

## Mevcut Durum Notu

Bu yol haritasi, mevcut party ledger ve muhasebe altyapisinin hazir oldugu varsayimi ile yazilmistir. Calisma agacinda `PartyLedgerService` eksik degildir; `postEntry`, `postReceivable`, `postCollection`, `postPayable`, `postPayment`, `voidEntry` ve `balanceForParty` metotlari mevcuttur. `PartyLedgerMigrationTest` ve `PartyLedgerServiceTest` dosyalari da mevcuttur.

Yerel kullanim icin `.env` icinde `PARTY_CORE_ENABLED=true` ve `ACCOUNTING_ENABLED=true` acilabilir. Ancak testlerde "feature flag default false" senaryolari calistirilirken test ortami bu local override'dan etkilenmemelidir. Gerekirse `phpunit.xml` veya test icinde ilgili config degeri kontrollu sekilde false'a alinmalidir.

## Genel Gelistirme Kurallari

Her fazin basinda ve sonunda uygulanacak rutin:

- Baslamadan once `git status --short` calistirilir.
- Diger is kollarindaki alakasiz dosyalara dokunulmaz.
- Gelistirme sirasinda commit atilmaz.
- Bitince `git status --short`, `git diff --stat`, degisen dosyalar, test sonucu, risk/notlar ve dokunulmayan alanlar raporlanir.
- Tasarim dili olarak ZOLM Kurumsal Acik Panel Sistemi kullanilir.
- Tum yeni route ve ekranlar `accounting_enabled` feature flag'i ve admin yetkilendirmesi arkasinda olur.
- Her sorguda `user_id` izolasyonu korunur; baska kullanicilarin verilerine erisim engellenir.
- Yeni tablo, servis ve Livewire aksiyonlarinda tenant ownership kontrolleri testlerle desteklenir.

## Faz 0 - Checkpoint / Commit Hazirligi

Amac: Su ana kadar gelistirilen ERP altyapisini guvenli bir checkpoint olarak kaydetmek.

Kapsam:

- Mevcut calisma agaci incelenir.
- `.env`, local swap/cache dosyalari, `vendor`, `node_modules` ve gecici ciktılar stage edilmez.
- Sadece ERP/muhasebe icin uretilen migration, model, servis, Livewire, route, test ve dokuman dosyalari stage edilir.
- `git diff --check` calistirilir.
- Secili ERP test seti calistirilir.
- Mumkunse full suite calistirilir.

Basari kriteri:

- Beklenen dosyalar disinda staged dosya yoktur.
- Secili ERP test seti yesildir.
- Commit mesaji onerisi: `feat: add ERP accounting foundation and party ledger workspace`

## Faz 1 - Muhasebe Ana Paneli

Amac: Sol menudeki Muhasebe baglantisini tek sayfa yerine tum ERP modullerine yonlendiren ana kontrol paneline cevirmek.

Yeni route:

- `/accounting`

Gelistirilecek ekran:

- `AccountingDashboard` Livewire component'i

Panel kartlari:

- Cari Acik Hesap
- Hesap Plani / Yevmiye
- Kasa & Banka
- Stok
- Satislar
- Satin Alma
- Hizli Satis
- e-Fatura
- Raporlar
- Asistan
- Pazaryeri Finans Koprusu

Her kartta bulunacak bilgiler:

- Kisa aciklama
- Durum badge'i: `Hazir`, `MVP`, `Altyapi Hazir`, `Yakinda`
- Ilgili route linki
- Anlik KPI veya ozet bilgi
- Feature flag kapaliysa pasif durum

Test kapsami:

- `ACCOUNTING_ENABLED=false` iken route 404 verir.
- `ACCOUNTING_ENABLED=true` iken panel render olur.
- Sol menude Muhasebe altinda alt moduller gorunur.
- Henuz route'u olmayan moduller pasif gorunur veya "Yakinda" durumu tasir.

## Faz 2 - Cari Acik Hesap Iyilestirmeleri

Amac: Mevcut `/accounting/party-ledger` ekranini gunluk kullanim seviyesine getirmek.

Iyilestirmeler:

- Yeni kayit formu modal veya drawer icine tasinir.
- Hareket tipleri net ayrilir: Alacak, Borc, Tahsilat, Odeme.
- Party secimi aramali autocomplete haline getirilir.
- Legal entity ve CRM contact secimleri opsiyonel ama tenant guvenlikli olur.
- Kayit iptalinde `void_reason` zorunlu hale getirilir.
- Tablo satirinda `Bakiye Etkisi` daha acik gosterilir.
- Party detay paneli eklenir.

Party detay paneli:

- Toplam borc
- Toplam alacak
- Net bakiye
- Son cari hareketler
- CRM contact linkleri
- Muhasebe/CRM karsilikli gecis linkleri

Test kapsami:

- Manuel alacak kaydi olusturulur.
- Tahsilat kaydi olusturulur.
- Borc ve odeme yonleri dogru calisir.
- Iptal edilen kayit bakiyeden duser.
- Baska user'in party, legal entity veya CRM contact kaydi kullanilamaz.
- Filtre ve siralama whitelist calisir.

## Faz 3 - Hesap Plani ve Yevmiye

Amac: Muhasebe cekirdegini ve cift tarafli kayit sistemini gorunur yapmak.

Yeni route'lar:

- `/accounting/chart-of-accounts`
- `/accounting/journal`

Hesap plani ekrani:

- Hesap kodu
- Hesap adi
- Hesap grubu
- Aktif/pasif durumu
- Borc/alacak normal yonu
- Sistem hesabi badge'i
- Kasa, banka, alici, satici badge'leri

Yevmiye ekrani:

- Fis listesi
- Manuel fis olusturma
- Satir bazli hesap secimi
- Borc/alacak kolonlari
- Toplam borc, toplam alacak, fark gostergesi
- Dengesiz fiste kayit engeli
- Fis iptal aksiyonu

Basari kriteri:

- Dengeli fis kaydedilir.
- Dengesiz fis arayuzde ve servis katmaninda reddedilir.
- Baska user hesabi kullanilamaz.
- Header `party_id` ve `legal_entity_id` sahiplik kontrolleri korunur.
- Line `party_id` sahiplik kontrolu korunur.

## Faz 4 - Kasa & Banka / Virman

Amac: Nakit ve banka hesap hareketlerini yonetilebilir arayuze kavusturmak.

Yeni route:

- `/accounting/cash-bank`

Ekran bolumleri:

- Kasa hesaplari
- Banka hesaplari
- Virman formu
- Hesap ekstresi
- Son hareketler

Virman akisi:

- Kaynak hesap secilir.
- Hedef hesap secilir.
- Tutar girilir.
- Aciklama girilir.
- Islem sonucunda dengeli journal fisi olusur.

Kurallar:

- Ayni hesaba virman yapilamaz.
- Sifir veya negatif tutar reddedilir.
- Kaynak ve hedef hesap ayni user'a ait olmalidir.
- Journal fisi baglantisi gorunmelidir.

Test kapsami:

- Kasa hesabi olusturulur.
- Banka hesabi olusturulur.
- Virman journal uretir.
- Hatali tutarlar reddedilir.
- Tenant izolasyonu korunur.

## Faz 5 - Depo & Stok Yonetimi

Amac: Stok modulunu ilk MVP ekranina tasimak.

Yeni route:

- `/accounting/stock`

Ekran bolumleri:

- Depolar listesi
- Guncel urun stok bakiyeleri
- Stok hareketleri gecmisi
- Kritik stok limit uyarilari

Islemler:

- Depo olusturma
- Varsayilan depo belirleme
- Stok girisi
- Stok cikisi
- Manuel sayim duzeltme
- Urun bazli stok gecmisi

Pazaryeri baglantisi:

- `mp_products.stock_quantity` ile senkronize envanter sayilari goruntulenir.
- Stok hareketi urun kartina yansiyorsa kullaniciya acikca gosterilir.

Test kapsami:

- Giris stok artirir.
- Cikis stok azaltir.
- Varsayilan depo otomatik cozulur.
- Kritik stok uyarisi dogru calisir.
- Baska user urunu veya deposu kullanilamaz.

## Faz 6 - Satis Siparisleri Yonetimi

Amac: Satis siparislerini ekrandan yonetmek.

Yeni route:

- `/accounting/sales`

Ekran ozellikleri:

- Satis siparisi listesi
- Yeni satis siparisi formu
- Musteri/party secimi
- Urun satirlari ekleme
- KDV ve toplam tutar hesaplama
- Taslak, onaylandi, iptal durumlari

Onaylama akisi:

- Satis siparisi onaylanir.
- Cari alacak faturasi `Receivable` olusur.
- Double-entry GL fisi kesilir.
- Depodan stok dusumu tetiklenir.

Test kapsami:

- Taslak satis olusturulur.
- Onaylaninca cari alacak olusur.
- Stok duser.
- Journal dengeli olusur.
- Baska user party veya legal entity reddedilir.

## Faz 7 - Satin Alma Siparisleri

Amac: Tedarikci alis siparislerini yonetilebilir hale getirmek.

Yeni route:

- `/accounting/purchases`

Ekran ozellikleri:

- Satin alma siparisleri listesi
- Yeni alis siparisi formu
- Tedarikci/party secimi
- Urun satirlari
- Toplam ve KDV hesabi
- Taslak, onaylandi, iptal durumlari

Onaylama akisi:

- Tedarikciye borc faturasi `Payable` acilir.
- GL fisi kesilir.
- Depoya stok girisi yapilir.

Test kapsami:

- Alis siparisi taslak olusturulur.
- Onaylaninca payable olusur.
- Stok artar.
- Journal dengelidir.
- Tenant izolasyonu korunur.

## Faz 8 - Hizli Satis / POS Checkout

Amac: Basit perakende satis ekranini acmak.

Yeni route:

- `/accounting/pos`

Ekran ozellikleri:

- Terminal secimi
- Vardiya ac/kapat butonlari
- Urun hizli arama veya barkod sepet alani
- Nakit/kart tahsilat secimi
- Satisi tamamlama

Arka plan akisi:

- Vardiya acik degilse satis yapilamaz.
- Satis tamamlaninca satis siparisi olusur.
- Tahsilat olusur.
- Stok duser.
- Journal kaydi olusur.
- POS sale kaydi olusur.

Test kapsami:

- Vardiya acilir.
- Vardiya olmadan satis reddedilir.
- POS satis tamamlanir.
- Stok ve tahsilat dogru olusur.

## Faz 9 - e-Fatura / e-Arsiv Portali

Amac: Gercek entegrator yerine simule edilmis e-belge kontrol ekrani yapmak.

Yeni route:

- `/accounting/e-documents`

Ekran ozellikleri:

- e-Belge taslak listesi
- Onayli satis siparisinden fatura taslagi olusturma
- Entegratore "Gonder" simulasyonu
- GIB benzeri belge numarasi uretimi
- e-Fatura iptal sureci
- Event log goruntuleme

Test kapsami:

- Taslak olusur.
- Gonderim belge numarasi uretir.
- Iptal event kaydeder.
- Baska user belgesi gorunmez.

## Faz 10 - Yonetim Raporlari

Amac: Finansal veriyi grafikler ve tablolar ile yonetim raporlarina donusturmek.

Yeni route:

- `/accounting/reports`

Raporlar:

- Alacak yaslandirma
- Borc yaslandirma
- 30 gunluk nakit akis tahmini
- Gelir/gider ozeti
- Stok envanter degeri
- Cari bakiye ozeti

Filtreler:

- Tarih araligi
- Legal entity
- Party
- Rapor tipi

Test kapsami:

- Her rapor dogru hesap dondurur.
- Tenant izolasyonu korunur.
- Bos veri ekranlari bozulmaz.

## Faz 11 - AI On Muhasebe Asistani

Amac: Dogal dil ile finansal analiz yapan asistani arayuze baglamak.

Yeni route:

- `/accounting/assistant`

Ekran ozellikleri:

- Soru sorma alani
- Sık sorulan hazir soru butonlari
- Gecmis cevaplar paneli
- Kaydedilmis sorular

Hazir sorular:

- Bu ayki nakit akisim nasil?
- Geciken alacaklarim kimde?
- En cok borcum olan tedarikciler kim?
- Stok degerim ne kadar?
- Bu ay kar/zarar durumum ne?

Guvenlik:

- Asistan sadece salt-okunur rapor servislerini cagirir.
- Veri yazma, silme, iptal etme veya guncelleme yapmaz.

Test kapsami:

- Bilinen sorgular dogru metoda yonlenir.
- Bilinmeyen sorgu fallback verir.
- Assistant query log olusur.
- Tenant izolasyonu korunur.

## Faz 12 - Pazaryeri Finans Koprusu UI

Amac: Trendyol, Hepsiburada ve diger pazaryeri siparislerinin ERP'ye aktarim durumlarini takip etmek.

Yeni route:

- `/accounting/marketplace-bridge`

Ekran ozellikleri:

- Aktarilan siparisler tablosu
- Party esleme statuleri
- Satis siparisi olustu mu bilgisi
- Stok dusuldu mu bilgisi
- Komisyon ve payout journal kayitlari eslesme durumlari
- Hata loglari
- Yeniden dene aksiyonu

Test kapsami:

- Bridge order satis siparisi uretir.
- Finans event journal uretir.
- Baska tenant order ile eslesmez.
- Null-safe kontroller korunur.

## Faz 13 - CRM 360 Entegrasyonu

Amac: Musteri 360 ekranlarinda on muhasebe entegrasyonunu gorsellestirmek.

CRM ekran eklentileri:

- Party durumu badge'i
- Guncel cari bakiye
- Acik borc/alacak KPI'lari
- Son cari hareketler tablosu
- "Muhasebede Cari Ac" linki

Test kapsami:

- Feature flag kapaliyken link gorunmez.
- Flag acikken link gorunur.
- Party yoksa ekran bozulmaz.
- Baska user party gorunmez.

## Faz 14 - Yetki, Feature Flag ve Demo Verisi

Amac: ERP modulunun ilk kurulumunu, demo verilerini ve yetki sinirlarini otomatiklestirmek.

Kapsam:

- `accounting_enabled` flag'i kapaliyken tum accounting route'lari 404 verir.
- `AdminMiddleware` korunur.
- `.env.example` icine flag notlari eklenir.
- `artisan accounting:seed-demo` idempotent demo veri seeder komutu eklenir.

Demo veriler:

- Ornek party
- Ornek yevmiye fisi
- Ornek cari hareket
- Ornek kasa/banka
- Ornek stok hareketi
- Ornek satis ve satin alma kaydi
- Ornek rapor verisi

Test kapsami:

- Flag kapali route 404.
- Admin olmayan kullanici erisemez.
- Demo seed ikinci kez duplicate uretmez.

## Faz 15 - Final Standartlar ve QA

Amac: Tum ekranlarin tasarim, mobil responsive ve kalite standartlarini denetlemek.

Standartlar:

- ZOLM Kurumsal Acik Panel baslik sistemi
- KPI kutulari
- Responsive tablolar
- Mobil kart gorunumu
- Loading state'ler
- Alpine.js kolon toggle
- Sort whitelist
- Turkce UI metin butunlugu
- Bos durumlar

Test kapsami:

- Tum route'lar render olur.
- Tum filtreler calisir.
- Mobil temel gorunum bozulmaz.
- Full test suite calisir.
- `git diff --check` temiz olur.

## Onerilen Gelistirme Siralamasi

Iskelet ve muhasebe omurgasinin once oturmasi, ardindan operasyonel ekranlarin akmasi icin su sira onerilir:

1. Faz 0 - Commit / Checkpoint
2. Faz 1 - Muhasebe Ana Paneli
3. Faz 3 - Hesap Plani / Yevmiye Ekranlari
4. Faz 4 - Kasa / Banka / Virman
5. Faz 5 - Stok Hareketleri
6. Faz 6 & 7 - Satis & Satin Alma Siparisleri
7. Faz 10 - Raporlar
8. Faz 11 - AI Asistan Arayuzu
9. Faz 8 - POS
10. Faz 9 - e-Fatura
11. Faz 12 - Pazaryeri Finans Koprusu
12. Faz 13 - CRM 360 Entegrasyonu
13. Faz 14 - Yetki, Feature Flag ve Demo Verisi
14. Faz 15 - Final Standartlar ve QA
