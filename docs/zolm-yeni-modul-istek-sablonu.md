# ZOLM Yeni Modul Istek Sablonu

Asagidaki metni yeni bir modul isterken dogrudan kullanabilirsiniz.

## Tek Parca Ana Sablon

```text
Bu modulu ZOLM Kurumsal Acik Panel Sistemi ile yap.
Venture uyumlu ZOLM acik panel varyantini uygula.
Tasarıma baslamadan once 20.3 [UI8] - Venture CRM.fig dosyasina bak.

Zorunlu kurallar:
- Referans tasarim dili Uretim Ciro ve Siparisler modulu olsun
- Acik zeminli, kurumsal, sade ve premium ama gosterissiz tasarim kullan
- Beyaz ana kart, acik gri ic kart, ince slate border, keskinlesmis radius, dusuk shadow kullan
- Command bar, tool rail ve ledger iliskisini tek urun mantiginda kur
- Filtre ve tablo ayni ana section icinde olsun
- Guidance kutulari gerekiyorsa compact accordion olarak ayri dursun
- Mobil Responsive Tasarim Kurallarini eksiksiz uygula
- UI metinlerini Turkce ve dogru karakterlerle yaz
- Yeni tema uretme, mevcut Venture uyumlu ZOLM tasarim ailesini devam ettir

Eger tablolu bir ekran varsa:
- Standart Tablo Sablonunu uygula
- Kolon gorunurlugu, backend siralama, kolon resize ve mobil kart gorunumu ekle
- Kolonlar ve toplu islem kontrollerini verinin ustunde konumlandir

Eger Excel export varsa:
- Excel Export Kurallarini uygula
- App\Services\ExcelService mantigina uy
- setCellValueExplicit, UTF-8 temizlik, XML-safe string ve sheet name sanitization kullan
```

## Kisa Versiyon

```text
Bu modulu ZOLM Kurumsal Acik Panel Sistemi ile yap.
Venture uyumlu ZOLM varyantini kullan.
Tasarıma baslamadan once 20.3 [UI8] - Venture CRM.fig dosyasina bak.
Mobil kurallari uygula.
Tablo varsa Standart Tablo Sablonu kullan.
Export varsa Excel Export Kurallarini uygula.
```

## Kati Versiyon

```text
Bu modulde yeni bir tasarim dili istemiyorum.
ZOLM Kurumsal Acik Panel Sistemi disina cikma.
Venture uyumlu mevcut ZOLM panel yorumunu uygula.
Tasarıma baslamadan once 20.3 [UI8] - Venture CRM.fig dosyasina bak.
Mobil responsive kurallari zorunlu.
Tablo varsa Standart Tablo Sablonu zorunlu.
Export varsa Excel Export Kurallari zorunlu.
Tum UI metinleri duzgun Turkce karakterlerle yazilsin.
Koyu hero, gradient, cam efekti ve asiri oval kart kullanma.
```
