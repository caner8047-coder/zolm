# ZOLM UI Prompt Sablonu

Yeni bir modul isterken asagidaki metni kullanin.

## Kisa Komut

```text
Bu modulu ZOLM Kurumsal Acik Panel Sistemi ile yap.
Referans tasarim dili Uretim Ciro modulu olsun.
Yeni tasarim dili uretme.
```

Bu kisa komutun acilimi artik sunlari da otomatik kapsar:
- Mobil Responsive Tasarim Kurallari
- Yeni tablo varsa Standart Tablo Sablonu
- Yeni Excel export varsa Excel Export Kurallari

## Standart Komut

```text
Bu modulu ZOLM Kurumsal Acik Panel Sistemi ile tasarla.

Kurallar:
- Referans sayfa Uretim Ciro modulu olsun
- Acik zeminli, kurumsal, sade ve premium ama gosterissiz tasarim kullan
- Beyaz ana kart, acik gri ic kart, ince slate border, buyuk radius, dusuk shadow kullan
- Ayni spacing sistemi, ayni buton dili, ayni badge dili, ayni tipografi hiyerarsisi kullan
- Koyu hero, gradient, glassmorphism, neon renkler kullanma
- Mobil responsive kurallar mevcut standartla ayni olsun
- UI metinlerini Turkce ve dogru karakterlerle yaz
- Yeni bir tema olusturma, mevcut tasarim ailesini devam ettir
- Yeni tablo varsa Standart Tablo Sablonunu uygula
- Yeni Excel export varsa Excel Export Kurallarini uygula
```

## Veri Yogun Moduller Icin Komut

```text
Bu modulu ZOLM Kurumsal Acik Panel Sistemi ile yap.
Uretim Ciro modulu tasarim ailesini birebir devam ettir.

Beklentiler:
- Ustte beyaz bir ana kontrol karti
- Iceride acik gri metrik kartlari
- Altta sol ana icerik ve sag yardimci panel
- Butonlar koyu, kartlar beyaz, ic kutular acik gri
- Tum radius, border, spacing ve tipografi ayni ailede olsun
- Sayfa baska bir urune aitmis gibi gorunmesin, ZOLM ailesinde kalsin
```

## Kesin Sapma Yasagi Olan Komut

```text
Bu modulde tasarim ozgurlugu istemiyorum.
ZOLM Kurumsal Acik Panel Sistemi disina cikma.
Referans Uretim Ciro modulu.
Koyu premium hero, farkli renk temasi, farkli kart dili, farkli button dili kullanma.
Var olan ZOLM acik kurumsal panel mimarisini devam ettir.
```

## Tablolu Moduller Icin Komut

```text
Bu modulu ZOLM Kurumsal Acik Panel Sistemi ile yap.
Referans Uretim Ciro modulu olsun.

Ek olarak:
- Yeni tablo varsa Standart Tablo Sablonunu eksiksiz uygula
- Kolon gorunurlugu, backend siralama, kolon resize ve mobil kart gorunumu olsun
- Tablo davranisi Siparisler tabina yakin olsun
```

## Exportlu Moduller Icin Komut

```text
Bu modulu ZOLM Kurumsal Acik Panel Sistemi ile yap.
Referans Uretim Ciro modulu olsun.

Ek olarak:
- Excel export varsa App\Services\ExcelService mantigina uy
- setCellValue yerine setCellValueExplicit kullan
- UTF-8 ve XML temizligi zorunlu olsun
- Sheet ismi sanitization uygula
```
