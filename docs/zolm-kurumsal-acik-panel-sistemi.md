# ZOLM Kurumsal Acik Panel Sistemi

Bu dokuman ZOLM icindeki yeni moduller icin varsayilan tasarim sistemidir.

Bu surum artik Venture uyumlu varyanti tanimlar.
Yani tasarim dili:
- ZOLM'un acik, kurumsal ve veri yogunlugunu temiz gosteren yapisini korur
- Venture benzeri daha urunlesmis hiyerarsi, daha rafine panel akisi ve daha modern kontrol yuzeyi kullanir
- Tam tema kopyasi yapmaz
- Koyu hero, dev radius ve gosterisli dashboard efektlerine kaymaz

## 1. Referanslar

Ana referans sayfalar:
- `resources/views/livewire/production-revenue.blade.php`
- `resources/views/livewire/marketplace-orders.blade.php`
- `20.3 [UI8] - Venture CRM.fig`

Ilk sayfa genel acik panel dilini temsil eder.
Ikinci sayfa Venture uyumlu veri yogun modul yorumunu temsil eder.
Fig dosyasi ise Venture kaynak tasarim dili icin zorunlu gorsel referanstir.

## 1.1. Tasarima Baslamadan Once Zorunlu Venture Kontrolu

Yeni bir tasarim veya buyuk UI revizyonu baslamadan once mutlaka:
- Repo kokundeki `20.3 [UI8] - Venture CRM.fig` dosyasina bak
- Mumkunse `thumbnail.png`, `canvas.fig` ve genel paket yapisini kontrol et
- Venture'daki layout hissi, spacing, kart hiyerarsisi, chip dili ve control surface mantigini hatirla
- Sonra ZOLM acik panel sistemine uyarlanmis yorumla tasarima basla

Kurallar:
- Venture dosyasina bakmadan yeni tasarim dili uretme
- Venture'i birebir koyu tema kopyasi gibi uygulama
- Venture'i ZOLM acik panel sistemine adapte et
- Kararsiz kalindiginda son karar verici yerel Venture fig dosyasi + bu dokuman birlikteligi olsun

## 2. Tasarim Kimligi

Bu sistemin ana dili:
- Acik zeminli
- Kurumsal
- Sade
- Veri yogun ama duzenli
- Premium ama gosterissiz
- Venture etkili ama ZOLM ailesinden kopmayan

Gorsel karakter:
- `bg-slate-50` hissinde acik sayfa zemini
- `bg-white` ana section kartlari
- `bg-slate-50/60` veya `bg-slate-50/70` ic yuzeyler
- `border-slate-200` ince ve yumusak cerceve
- `text-slate-900` ana metin
- `text-slate-500` yardimci metin
- `bg-slate-900 text-white` ana aksiyon

Venture uyumlu yorumda beklenen fark:
- Sayfa daha urun paneli gibi organize olur
- Ustte workspace/command katmani daha net tanimlanir
- Ledger, filtre, durum ve arac yuzeyleri birbirine bagli hissedilir
- Chip, badge ve ust etiket kullanimi daha kontrollu ve urunlesmis olur
- Venture fig dosyasindaki ritim once okunur, sonra ZOLM'a adapte edilir

## 3. Kacinilacaklar

Su yaklasimlar kullanilmaz:
- Koyu hero alanlari
- Tam genislik koyu panel bloklari
- Gradient agirlikli dashboard dili
- Glassmorphism
- Neon renkler
- Dev radius kullanan yumusak/balloon kartlar
- Bir modulde cok keskin, diger modulde cok oval kutular
- Filtre ile tabloyu kopuk iki ayri urun gibi gosteren yerlesim
- Sadece guzel gorunuyor diye veri yogunlugunu dusuren bos tasarimlar

## 4. Onayli Radius ve Yuzey Sistemi

Bu proje icinde onaylanan standart radius ailesi budur:

### Ana section kart
- `rounded-[10px] border border-slate-200 bg-white shadow-sm`

### Ic kart / metrik karti / bilgi kutusu
- `rounded-[8px] border border-slate-200 bg-slate-50/70`

### Input / select / action chip / mini kutu
- `rounded-[6px] border border-slate-200 bg-white`

### Buton
- `rounded-[6px]` veya `rounded-lg`

Kurallar:
- Section ile input arasinda radius siralamasi tutarli olmali
- Ayni ekranda biri `rounded-3xl`, digeri `rounded-md` gibi kopuk sistem kurulmamali
- Mobilde de ayni radius ailesi korunmali

## 5. Golge ve Border

Standartlar:
- Ana kartlar: `shadow-sm`
- Hover varsa hafif ton degisimi veya cok hafif shadow artisi
- Border her zaman okunur ama bagirmaz olmali

Kurallar:
- Agir shadow yok
- 3D hissi yok
- Border ile golge birbirini dovmez

## 6. Yerlesim Mantigi

Her modulde ana omurga su mantikta kurulmalidir:

1. Ust workspace / ozet karti
2. Varsa import / guidance / durum kutusu
3. Command bar veya ana kontrol alani
4. Ledger / tablo / ana icerik alani
5. Alt detay, gecmis veya ek ozet bloklari

Standart akis:

```blade
<div class="w-full space-y-4 lg:space-y-6">
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        ...
    </section>

    <section class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        ...
    </section>
</div>
```

## 7. Command Bar ve Ledger Kurali

Venture uyumlu ZOLM yorumunda en kritik kural budur:

- Filtre, arama ve kontrol alani tabloyla ayni ana section icinde olmalidir
- `Kolonlar`, toplu islem, aktif filtre bilgisi gibi araclar verinin ustunde konumlanmalidir
- Sag yardimci panel desktopta ayri durabilir ama mobilde ana kontrol yuzeyi ile birlesmelidir
- Guidance veya kritik uyari kutulari command bar'in icine sikistirilmaz; gerekiyorsa ustte ayri, kompakt accordion kart olarak durur

Onayli mantik:
- Ayrik banner yok
- Ayrik siyah tool rail yok
- Ayrik ve dev ikinci toolbar yok
- Tek urune ait kontrol hissi olmali

## 8. Kart Dili

### Ana section kart
Kullanim:
- Modulu sarmalayan ana bloklar
- Workspace ozetleri
- Command bar
- Ledger kapsayicisi
- Detay panelleri

Sinif omurgasi:
- `rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm`

### Ic kart
Kullanim:
- KPI
- Durum kutulari
- Paket karti
- Ozet kutusu
- Alert ic detayi

Sinif omurgasi:
- `rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 lg:p-4`

### Kompakt bilgi kutusu
Kullanim:
- Hazir / Finans / Risk kutulari
- Mini stat bloklari
- Kisa label-value kartlari

Sinif omurgasi:
- `rounded-[6px] border border-slate-200 bg-slate-50/80 px-2 py-1.5`

## 9. Tipografi

### Kucuk ust etiket
```html
<p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">...</p>
```

### Ana baslik
```html
<h1 class="text-3xl lg:text-[40px] font-bold tracking-tight text-slate-950">...</h1>
```

### Section basligi
```html
<h3 class="text-lg font-semibold text-slate-900">...</h3>
```

### Aciklama
```html
<p class="text-sm text-slate-500">...</p>
```

Kurallar:
- Basliklari gereksiz buyutme
- Yardimci metni her zaman bir ton geri cek
- Metrik kutularinda truncation ve `min-w-0` kullan
- Uzun aciklamayi command bar veya tool rail icinde 1-2 satiri gecirmemeye calis

## 10. Buton Dili

### Primary button
```html
<button class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 sm:py-2 text-sm font-medium text-white transition hover:bg-slate-800">
```

### Secondary button
```html
<button class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
```

Kurallar:
- Butonlar mobilde tam genislik kullanabilmeli
- Desktopta gereksiz kaba gorunume izin verme
- Ayni yuzeyde uc farkli buton dili kullanma

## 11. Badge ve Chip Dili

Kullanim:
- Kisa metin
- Kisa durum ozeti
- Aktif filtre gostergesi
- Mini route / state etiketi

Standart:
- `rounded-[6px]`
- Ince border
- `px-2.5 py-0.5` veya `px-2.5 py-1`
- `text-xs` veya `text-[11px]`
- Tasma varsa `whitespace-nowrap`

Not:
- Eski `rounded-full` badge dili varsayilan degildir
- Cok yumusak pill gorunumu yerine kontrollu kose yaricapi kullanilir

## 12. Bosluk Sistemi

Sayfa standardi:
- `space-y-4 lg:space-y-6`

Section ic bosluk:
- `p-4 lg:p-6`

Ic kart bosluk:
- `p-3 lg:p-4`

Kartlar arasi gap:
- `gap-3 lg:gap-4`

Kurallar:
- Section basligi ile icerik arasinda gereksiz buyuk bosluk olmaz
- Command bar ve ledger arasinda kopukluk olusmaz
- Detail panellerinde bloklar birbirinin ustune binmis gibi durmaz; her ana blok kendi `mt-4` ritmini korur

## 13. Tablo Kurallari

Yeni modulde tablo varsa su davranislar zorunludur:
- Desktop tablo + mobil kart gorunumu
- Kolon gorunurlugu
- Backend siralama
- Kolon resize
- `table-layout: fixed`
- Hucre truncation
- Kaydirilabilir yatay alan

Ek Venture uyumlu kurallar:
- Ledger ust bandi tabloyun parcasidir
- `Kolonlar`, kolon sayisi ve toplu islem verinin ustunde gorunur
- Mobilde masaustu araclari ayri bir rail olarak kalmaz
- Veri yogunlugu korunur ama hucreler nefes alir
- Toplu islem dropdown'u ve aktif filtre gostergesi mobilde command bar icine tasinir

Referans:
- `resources/views/livewire/marketplace-orders.blade.php`

## 14. Mobil Responsive Kurallari

Tum yeni modullerde zorunlu:
- `flex flex-col sm:flex-row`
- `grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3`
- `w-full sm:w-auto`
- `text-base sm:text-sm`
- `p-4 lg:p-6`
- `gap-3 lg:gap-4`

Ek Venture uyumlu mobil kurallari:
- Sag tool rail mobilde ayri panel olarak birakilmaz; command bar ile birlesir
- Kisa stat kutulari mobilde mumkunse tek satir 3 kolon gibi derli toplu dizilir
- Mobil kart header'lari daha kisa olur; gereksiz aciklama metni kisaltilir
- Expand acilan detay panelleri `p-3 sm:p-4` bandinda tutulur
- Paket ve aksiyon butonlari mobilde tek kolon ya da rahat 2 kolon akar
- Pagination ve page size secimi tam genislikte rahat kullanilir

## 15. Icerik Dili

Kurallar:
- UI dili Turkce
- Turkce karakterler dogru yazilmali
- Metinler kisa olmali
- Kart aciklamalari tek cumleyi gecmemeli
- Teknik olmayan kullaniciya uygun okunurluk korunmali

## 16. Excel Export ve Teknik Baglayicilar

Bu dokuman tasarim odaklidir ancak yeni modulde export veya tablo varsa teknik kurallar da otomatik devreye girer:

- Yeni Blade view varsa Mobil Responsive Kurallari zorunludur
- Yeni tablo varsa Standart Tablo Sablonu zorunludur
- Yeni Excel export varsa Excel Export Kurallari zorunludur
- `setCellValue()` yerine `setCellValueExplicit()` kullanilir
- Tum string degerleri UTF-8 kontrolunden gecer
- XML kontrol karakterleri temizlenir
- Sheet isimleri 31 karakter sinirina ve yasak karakter kurallarina uyar

Referans servis:
- `App\\Services\\ExcelService`

## 17. Uygulama Kurali

Kullanici su ifadeyi kullanirsa:
- `Bu modulu ZOLM Kurumsal Acik Panel Sistemi ile yap`

Artik varsayilan yorum su olur:
1. ZOLM acik kurumsal panel mimarisini koru
2. Tasarima baslamadan once `20.3 [UI8] - Venture CRM.fig` dosyasini kontrol et
3. Venture uyumlu daha urunlesmis yerlesim ve command bar mantigi kullan
4. Radius, bosluk, tablo ve tool yerlesiminde bu dokumandaki standartlari uygula
5. Mobil kurallari eksiksiz uygula
6. Yeni tablo varsa Standart Tablo Sablonunu uygula
7. Yeni Excel export varsa Excel Export Kurallarini uygula

Yeni bir tema uretme.
ZOLM tasarim ailesinin Venture uyumlu varsayilanini devam ettir.
