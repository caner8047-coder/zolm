# ZOLM Yeni Modul Istek Sablonu

Asagidaki metni yeni bir modul isterken dogrudan kullanabilirsiniz.

## Tek Parca Ana Sablon

```text
Bu modulu ZOLM Kurumsal Acik Panel Sistemi ile yap.

Zorunlu kurallar:
- Referans tasarim dili Uretim Ciro modulu olsun
- Acik zeminli, kurumsal, sade ve premium ama gosterissiz tasarim kullan
- Beyaz ana kart, acik gri ic kart, ince slate border, buyuk radius, dusuk shadow kullan
- Ayni spacing sistemi, ayni button dili, ayni badge dili, ayni tipografi hiyerarsisi kullan
- Mobil Responsive Tasarim Kurallarini eksiksiz uygula
- UI metinlerini Turkce ve dogru karakterlerle yaz
- Yeni tema uretme, mevcut ZOLM tasarim ailesini devam ettir

Eger tablolu bir ekran varsa:
- Standart Tablo Sablonunu uygula
- Kolon gorunurlugu, backend siralama, kolon resize ve mobil kart gorunumu ekle

Eger Excel export varsa:
- Excel Export Kurallarini uygula
- App\Services\ExcelService mantigina uy
- setCellValueExplicit, UTF-8 temizlik, XML-safe string ve sheet name sanitization kullan
```

## Kisa Versiyon

```text
Bu modulu ZOLM Kurumsal Acik Panel Sistemi ile yap.
Mobil kurallari uygula.
Tablo varsa Standart Tablo Sablonu kullan.
Export varsa Excel Export Kurallarini uygula.
Referans sayfa Uretim Ciro modulu olsun.
```

## Katı Versiyon

```text
Bu modulde yeni bir tasarim dili istemiyorum.
ZOLM Kurumsal Acik Panel Sistemi disina cikma.
Referans Uretim Ciro modulu.
Mobil responsive kurallari zorunlu.
Tablo varsa Standart Tablo Sablonu zorunlu.
Export varsa Excel Export Kurallari zorunlu.
Tum UI metinleri duzgun Turkce karakterlerle yazilsin.
```
