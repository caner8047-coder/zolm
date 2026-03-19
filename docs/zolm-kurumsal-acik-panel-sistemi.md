# ZOLM Kurumsal Acik Panel Sistemi

Bu dokuman ZOLM icindeki tum yeni modullerin ayni tasarim mimarisinde cikmasi icin referans tasarim sistemidir.

Referans sayfa:
- `resources/views/livewire/production-revenue.blade.php`

Amac:
- Acik zeminli
- Kurumsal
- Sade
- Veri yogunlugunu temiz gosteren
- Her modulu ayni ailede hissettiren bir UI dili kurmak

## 1. Tasarim Kimligi

Bu sistemin ana dili:
- `bg-slate-50` hissinde acik sayfa zemini
- `bg-white` ana section kartlari
- `bg-slate-50` ic kartlar
- `border-slate-200` ince ve yumusak cerceve
- `text-slate-900` ana metin
- `text-slate-500` yardimci metin
- `bg-slate-900 text-white` ana aksiyon

Kacinilacak yaklasimlar:
- Koyu hero alanlari
- Gradient agirlikli dashboard dili
- Glassmorphism
- Neon renkler
- Her modulde farkli theme denemesi
- Font ya da stil karakterini sayfadan sayfaya degistirmek

## 1.1 Zorunlu Eski Prompt Kurallari

Bu tasarim sistemi tek basina gorunumu tanimlar. Asagidaki eski proje kurallari da bununla birlikte zorunlu olarak uygulanir:

- Yeni Blade view varsa Mobil Responsive Kurallari zorunludur
- Yeni tablo varsa Standart Tablo Sablonu zorunludur
- Yeni Excel export varsa Excel Export Kurallari zorunludur
- UI dili Turkce ve dogru karakterlerle yazilmalidir

Yani yeni bir modul yapilirken sadece "guzel gorunsun" yeterli degildir. Tasarim, responsive, tablo ve export davranisi birlikte ele alinmalidir.

## 2. Yerlesim Sistemi

Her modulde ana omurga su mantikta kurulmalidir:

1. Ust ozet karti
2. Module ait ana kontrol/filtre/yukleme alani
3. Sol ana icerik + sag yardimci panel
4. Alt metrik veya gecmis kartlari

Standart grid yaklasimi:

```blade
<div class="w-full space-y-6">
    <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        ...
    </section>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 lg:gap-8">
        <section class="xl:col-span-2 rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
            ...
        </section>

        <aside class="space-y-4 lg:space-y-5">
            ...
        </aside>
    </div>
</div>
```

## 3. Kart Dili

### Ana section kart
- `rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm`

Kullanim amaci:
- Modulu sarmalayan ana bloklar
- Takvim, rapor, kontrol paneli, detay paneli

### Ic metrik kart
- `rounded-3xl border border-slate-200 bg-slate-50 p-4`

Kullanim amaci:
- KPI
- Ozet bilgiler
- Haftalik / aylik / durum kutulari

### Input ve secim alani
- `rounded-2xl border border-slate-200 bg-white`

### Upload alani
- `rounded-2xl border border-dashed border-slate-300 bg-slate-50`

## 4. Tipografi

### Kucuk ust etiket
```html
<p class="text-xs uppercase tracking-[0.2em] text-slate-500">...</p>
```

### Ana baslik
```html
<h1 class="text-xl lg:text-2xl font-bold text-slate-900">...</h1>
```

### Ic kart buyuk deger
```html
<p class="text-2xl lg:text-3xl font-bold text-slate-900">...</p>
```

### Aciklama
```html
<p class="text-sm text-slate-500">...</p>
```

Kurallar:
- Basliklari gereksiz buyutme
- Deger alaninda sadece ihtiyac kadar vurgu ver
- Yardimci metni her zaman bir ton geri cek

## 5. Renk ve Durum Sistemi

### Basari
- Yesil
- `bg-emerald-50 text-emerald-700 border-emerald-200`

### Uyari
- Amber
- `bg-amber-50 text-amber-700 border-amber-200`

### Hata
- Rose
- `bg-rose-50 text-rose-700 border-rose-200`

### Bilgi
- Sky
- `bg-sky-50 text-sky-700 border-sky-200`

## 6. Radius ve Golge

Standartlar:
- Ana section: `rounded-[28px]`
- Ic kart: `rounded-3xl`
- Input / select / upload: `rounded-2xl`
- Buton: `rounded-lg`
- Golge: `shadow-sm`

Kurallar:
- Agir shadow yok
- 3D hissi yok
- Sadece hafif derinlik

## 7. Buton Dili

### Primary button
```html
<button class="w-full sm:w-auto px-4 py-3 sm:py-2 text-base sm:text-sm font-medium bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors">
```

### Secondary button
```html
<button class="w-full sm:w-auto px-4 py-3 sm:py-2 text-base sm:text-sm font-medium border border-slate-200 bg-white text-slate-700 rounded-lg hover:bg-slate-50 transition-colors">
```

Kurallar:
- Koyu ana buton
- Fazla yuksek ya da fazla genis buton kullanma
- Desktopta gereksiz kaba gorunume izin verme

## 8. Badge Dili

Kullanim:
- Kisa metin
- `rounded-full`
- Kucuk padding
- Tasma riski varsa `whitespace-nowrap`

Ornek:
```html
<span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs text-emerald-700">Kayitli</span>
```

## 9. Bosluk Sistemi

Sayfa standardi:
- `space-y-6`

Section ic bosluk:
- `p-4 lg:p-6`

Kartlar arasi gap:
- `gap-3 lg:gap-4`

Buyuk layout gap:
- `gap-6 lg:gap-8`

## 10. Mobil Responsive Kurallari

Tum yeni modullerde zorunlu:
- `flex flex-col sm:flex-row`
- `grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3`
- `w-full sm:w-auto`
- `text-xl lg:text-2xl`
- `p-4 lg:p-6`
- `gap-3 lg:gap-4`

Input ve select:
- `text-base sm:text-sm`

Buton ve input:
- Mobilde minimum 44px hissi korunmali

Ek zorunlu kurallar:
- `grid-cols-1` ile basla
- `gap-3 lg:gap-4` veya `gap-4 lg:gap-6` ritmini koru
- `text-base sm:text-sm` ile iOS zoom sorununu engelle
- Mobilde tablo yerine kart veya horizontal scroll kullan
- Modal varsa mobilde `inset-0`, buyuk ekranda daralmis pencere mantigi kullan

## 11. Icerik Dili

Kurallar:
- UI dili Turkce
- Turkce karakterler dogru yazilmali
- Metinler kisa olmali
- Kart aciklamalari tek cumleyi gecmemeli
- Teknik olmayan kullaniciya uygun okunurluk korunmali

## 11.1 Excel Export Kurallari

Bu dokuman tasarim odaklidir ancak yeni modulde Excel export varsa asagidaki teknik kurallar zorunludur:

- `setCellValue()` kullanma, `setCellValueExplicit()` kullan
- Tum string degerleri UTF-8 kontrolunden gecir
- XML kontrol karakterlerini temizle
- Sheet isimlerini 31 karakter sinirina ve yasak karakter kurallarina uydur
- Referans servis: `App\\Services\\ExcelService`

Bu tasarim sistemi export davranisindan bagimsiz degildir. Yeni modulu tasarlarken export varsa bu kurallar da otomatik devreye girer.

## 11.2 Standart Tablo Kurallari

Yeni modulde tablo varsa asagidaki davranislar zorunludur:

- Kolon goster/gizle dropdown
- Backend tabanli siralama
- Kolon resize
- Desktop tablo + mobil kart gorunumu
- `table-layout: fixed`
- Hucrelerde truncation
- Kolon tercihlerini saklayan ayar mantigi

Referans:
- `resources/views/livewire/marketplace-accounting.blade.php`

## 12. Yeni Modullerde Uygulama Kurali

Bir modulu bu sisteme gore tasarlarken:
- Yeni theme uretme
- Referans tasarimdan sapma
- Farkli radius sistemi kurma
- Farkli renk mantigi deneme
- Bir sayfada acik, digerinde koyu tasarim kullanma
- Mobil kurallari atlama
- Yeni tabloda eski tablo standardini bozma
- Excel export kurallarini es gecme

Yeni modullerde ayni mimarinin devam ettirilmesi zorunludur.

## 13. Kontrol Listesi

Yeni bir modulu teslim etmeden once kontrol et:
- Ana section kartlari beyaz mi
- Ic kartlar acik gri mi
- Borderlar ince slate mi
- Radius sistemi tutarli mi
- Primary button koyu mu
- Baslik hiyerarsisi ayni mi
- Mobil stack kurallari uygulandi mi
- Turkce karakterler duzgun mu
- Yeni bir tema olusmadi mi
- Tablo varsa standart tablo davranislari uygulandi mi
- Export varsa ExcelService kurallari uygulandi mi

## 14. Komut Kisa Adi

Bu tasarim sisteminin kisa adi:

`ZOLM Kurumsal Acik Panel Sistemi`

Yeni bir modulde bu isim referans alindiginda yukaridaki tum kurallar uygulanmalidir.

## 15. Yorumlama Kurali

Bir kullanici su sekilde talep verdiginde:

- `Bu modulu ZOLM Kurumsal Acik Panel Sistemi ile yap`

su anlam cikarilmalidir:

1. Uretim Ciro moduluyle ayni UI ailesi kullan
2. Mobil responsive kurallari eksiksiz uygula
3. Tablolu bir ekran varsa Standart Tablo Sablonu uygula
4. Export varsa Excel Export Kurallari uygula
5. Turkce UI metinlerinde ASCII kisa yol kullanma, dogru karakter kullan
