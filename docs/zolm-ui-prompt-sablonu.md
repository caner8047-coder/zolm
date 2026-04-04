# ZOLM UI Prompt Sablonu

Yeni bir modul isterken asagidaki metinleri kullanin.

Bu sablonlar artik Venture uyumlu ZOLM acik panel yorumunu varsayilan kabul eder.
Tasarıma başlamadan once `20.3 [UI8] - Venture CRM.fig` dosyasina bakilmasi zorunludur.

## Kisa Komut

```text
Bu modulu ZOLM Kurumsal Acik Panel Sistemi ile yap.
Venture uyumlu ZOLM acik panel varyantini kullan.
Tasarıma baslamadan once 20.3 [UI8] - Venture CRM.fig dosyasina bak.
Yeni tema uretme.
```

Bu kisa komut sunlari da otomatik kapsar:
- Mobil Responsive Tasarim Kurallari
- Yeni tablo varsa Standart Tablo Sablonu
- Yeni Excel export varsa Excel Export Kurallari

## Standart Komut

```text
Bu modulu ZOLM Kurumsal Acik Panel Sistemi ile tasarla.

Kurallar:
- Tasarıma baslamadan once 20.3 [UI8] - Venture CRM.fig dosyasini incele
- Referans sayfalar Uretim Ciro ve Siparisler modulu olsun
- Acik zeminli, kurumsal, sade ve premium ama gosterissiz tasarim kullan
- Venture uyumlu urunlesmis yerlesim kullan ama koyu hero kullanma
- Beyaz ana kart, acik gri ic kart, ince slate border, keskinlesmis radius, dusuk shadow kullan
- Command bar, tool rail ve ledger iliskisini tek urun mantiginda kur
- Filtre ve tablo ayni ana section icinde kalsin
- Tablo araclari verinin ustunde konumlansin
- Ayni spacing sistemi, ayni buton dili, ayni badge dili, ayni tipografi hiyerarsisi kullan
- Mobil responsive kurallar mevcut standartla ayni olsun
- UI metinlerini Turkce ve dogru karakterlerle yaz
- Yeni bir tema olusturma, mevcut Venture uyumlu ZOLM ailesini devam ettir
- Yeni tablo varsa Standart Tablo Sablonunu uygula
- Yeni Excel export varsa Excel Export Kurallarini uygula
```

## Veri Yogun Moduller Icin Komut

```text
Bu modulu ZOLM Kurumsal Acik Panel Sistemi ile yap.
Venture uyumlu veri yogun ZOLM tablo dilini kullan.
Tasarıma baslamadan once 20.3 [UI8] - Venture CRM.fig dosyasina bak.

Beklentiler:
- Ustte workspace veya ozet karti
- Varsa guidance kutusu command bar'dan ayri ama kompakt olsun
- Altinda command bar ve tablo ayni ana yuzeyde toplansin
- Ledger ust bandi tabloda dogrudan verinin ustunde dursun
- Sag yardimci panel desktop'ta ayri, mobilde command bar ile birlesik davransin
- Butonlar koyu, kartlar beyaz, ic kutular acik gri olsun
- Tum radius, border, spacing ve tipografi ayni ailede olsun
```

## Kati Sapma Yasagi Olan Komut

```text
Bu modulde tasarim ozgurlugu istemiyorum.
ZOLM Kurumsal Acik Panel Sistemi disina cikma.
Venture uyumlu ZOLM panel yorumunu uygula.
Tasarıma baslamadan once 20.3 [UI8] - Venture CRM.fig dosyasina bak.
Koyu hero, gradient, glassmorphism, neon renkler, dev oval kartlar kullanma.
Var olan ZOLM acik kurumsal panel mimarisini devam ettir.
```

## Tablolu Moduller Icin Komut

```text
Bu modulu ZOLM Kurumsal Acik Panel Sistemi ile yap.
Referans Uretim Ciro ve Siparisler modulu olsun.
Tasarıma baslamadan once 20.3 [UI8] - Venture CRM.fig dosyasini incele.

Ek olarak:
- Yeni tablo varsa Standart Tablo Sablonunu eksiksiz uygula
- Kolon gorunurlugu, backend siralama, kolon resize ve mobil kart gorunumu olsun
- Command bar, aktif filtre ve tablo araclari tek urun yuzeyi gibi calissin
- Kolonlar ve toplu islem kontrolu verinin ustunde konumlansin
```

## Exportlu Moduller Icin Komut

```text
Bu modulu ZOLM Kurumsal Acik Panel Sistemi ile yap.
Venture uyumlu ZOLM acik panel varyantini kullan.
Tasarıma baslamadan once 20.3 [UI8] - Venture CRM.fig dosyasina bak.

Ek olarak:
- Excel export varsa App\Services\ExcelService mantigina uy
- setCellValue yerine setCellValueExplicit kullan
- UTF-8 ve XML temizligi zorunlu olsun
- Sheet ismi sanitization uygula
```
