# Trendyol Booster Geliştirme Handoff — 29.06.2026

Bu dosya, yeni bir Codex sohbetinde Trendyol Booster geliştirmesine kaldığımız yerden devam edebilmek için hazırlandı. Yeni sohbette bu dosyayı paylaşmak veya “`docs/trendyol-booster-handoff-2026-06-29.md` dosyasını oku ve kaldığımız yerden devam et” demek yeterli.

## Kısa amaç

Trendyol Booster, ZOLM içinde Pazaryeri modülünden ayrı düşünülen ama pazaryeri akışına bağlı çalışan büyük bir ürün araştırma / takip modülü olarak kurgulandı.

Ana hedef:

- Trendyol ürünlerini analiz etmek.
- Ürünleri karşılaştırmak.
- Ürün alım kararı vermeye yardımcı olmak.
- Pazar karşılaştırması yapmak.
- Ürünleri Booster Radar’a alıp zaman içinde otomatik tarama ile değerli metrikler üretmek.
- Chrome eklentisiyle Trendyol sayfasında gezerken ürünü hızlıca takibe almak ve mevcut takip sinyallerini ürün sayfasında göstermek.

## Tasarım dili

Projede zorunlu görsel dil:

- ZOLM Kurumsal Açık Panel Sistemi.
- Açık zemin, beyaz ana kartlar, açık gri iç kartlar.
- Ana kart: `rounded-[10px] border border-slate-200 bg-white shadow-sm`
- İç kart: `rounded-[8px] border border-slate-200 bg-slate-50/70`
- Primary button: `bg-slate-900 text-white`
- UI dili Türkçe.
- Mobile responsive kuralları korunmalı.

Bu modülde koyu hero, gradient ağırlıklı dashboard, glassmorphism veya neon tasarım tercih edilmemeli.

Tasarım işlerine başlamadan önce proje kuralı gereği şu referans kontrol edildi:

- `20.3 [UI8] - Venture CRM.fig`

Bu dosya Venture hissini birebir kopyalamak için değil, ZOLM açık panel sistemine daha ürünleşmiş command bar / ledger / tool surface hiyerarşisi vermek için referans alındı.

## Konuşmada netleşen ürün mimarisi

Kullanıcı Trendyol Booster’ın ayrı bir ana modül gibi planlanmasını istedi. Çünkü modül sayısı arttıkça klasik tek sayfa menü çok uzuyor.

Son kabul edilen yapı:

```text
Pazaryeri

Trendyol Booster
├── Ürün Stalk
│   ├── Ürün Analizi
│   ├── Ürün Karşılaştırma
│   ├── Ürün Alım Kararı
│   ├── Pazar Karşılaştırması
│   ├── Booster Radar
│   ├── Stok Sorgulama
│   └── Rakip Takibi
├── Hesaplama
│   ├── Sat veya Satma (AI)       Yakında
│   ├── Kâr-Zarar Hesaplama       Yakında
│   ├── Brüt Kâr-Zarar Hesaplama  Yakında
│   ├── Net Kâr-Zarar Hesaplama   Yakında
│   ├── Hedef Planlayıcı          Yakında
│   └── Komisyon Oranları
├── Pazar Araçları
│   ├── Çok Satanlar
│   ├── Kategori Analizi          Yakında
│   ├── Mağaza Analizi            Yakında
│   ├── Marka Analizi             Yakında
│   ├── Yükselen Ürünler          Yakında
│   ├── Anahtar Kelime Takibi     Yakında
│   ├── Anahtar Kelime Araştırma
│   ├── Trend Kelimeler
│   ├── Tedarikçi Bul             Yakında
│   ├── Pazar Boşluğu             Yakında
│   └── Niche Bulucu              Yakında
└── Takip Araçları
    ├── Favorilerim
    ├── Fiyat Takibi
    ├── Analiz Geçmişi
    └── Bildirimler
```

## Menü / navigasyon kararları

### Sidebar

Kullanıcı uzun menüden rahatsız oldu. Bu yüzden Trendyol Booster altında şu davranış istendi:

- `Ürün Stalk`, `Hesaplama`, `Pazar Araçları`, `Takip Araçları` ana accordion grup olsun.
- Sadece aktif modülün ait olduğu grup açık kalsın.
- Diğer gruplar kapalı dursun.
- Menüde seçili görünen modül ile içerikte açık olan modül eş zamanlı gitsin.
- Örneğin içerik `Stok Sorgulama` ise sidebar’da `Stok Sorgulama` seçili olmalı; `Ürün Analizi` seçili kalmamalı.

Bu sorun için `resources/views/layouts/partials/trendyol-booster-menu.blade.php` partial’ı oluşturuldu/güncellendi.

Önemli davranış:

- Sidebar layout Blade içinde statik render edildiği için Livewire `wire:click` ile içerik değişince sidebar otomatik rerender olmuyordu.
- Bu nedenle Livewire tarafında `booster-module-changed` browser event’i dispatch ediliyor.
- Sidebar Alpine ile bu event’i dinliyor ve kendi aktif item / açık grup state’ini güncelliyor.

### Üst kısa yol barı

Kullanıcı ekranın içindeki üst modül barının da sidebar gibi çalışmasını istedi.

Son durum:

- Üstteki kısa yol barında Trendyol Booster içindeki tüm modüller listeleniyor.
- “Yakında” olanlar pasif buton + `Yakında` rozetiyle görünüyor.
- Aktif modül siyah/dolu buton olarak görünüyor.
- `Favorilerim` kısa yolu doğrudan `Booster Radar + favori filtresi` açıyor.
- Bar yatay scroll çalışıyor.
- Mouse ile sağa/sola çekerek bar içinde gezilebiliyor.
- Sürükleme yapılırsa yanlışlıkla buton click’i çalışmasın diye click suppression eklendi.

Arama alanı:

- Üst barın soluna `Modül ara...` input’u eklendi.
- Kullanıcı yazarak modül filtreleyebiliyor.
- Türkçe locale ile küçük harfe çevirme kullanılıyor: `toLocaleLowerCase('tr-TR')`.
- `Esc` ile arama temizleniyor.
- Yazı yazınca sağda küçük `x` temizleme butonu görünüyor.

Son düzeltme:

- İlk modern arama tasarımında ikon kutusu ve `ARA` rozeti absolute konumlu olduğu için kaymış görünüyordu.
- Bu kaldırıldı.
- Şu an arama alanı tek parça `label` yüzeyi:
  - Sol ikon sabit flex alanında.
  - Input ortada.
  - Temizleme butonu sağda.

## Önemli dosyalar

### 1. Livewire component

Dosya:

- `app/Livewire/TrendyolBooster.php`

Öne çıkan metotlar:

- `setActiveModule(string $module)`
  - Aktif modülü değiştirir.
  - `favoritesOnly` değerini kapatır.
  - Sidebar/üst bar senkronu için `dispatchBoosterModuleChanged($module)` çağırır.

- `openFavorites()`
  - `activeModule = tracking`
  - `favoritesOnly = true`
  - Event item olarak `favorites` dispatch eder.
  - Üst kısa yoldaki `Favorilerim` butonu için eklendi.

- `toggleFavoritesOnly()`
  - Booster Radar içindeki favori filtresini aç/kapatır.
  - Aktif item `favorites` veya mevcut module olarak dispatch edilir.

- `boosterModuleGroups()`
  - Sidebar ve üst kısa yol barının aynı modül ailesinden beslenmesi için eklendi.
  - Gruplar:
    - `product`
    - `calculation`
    - `market`
    - `tracking`

- `boosterModules()`
  - Çalışan modülleri üretir.
  - `soon` olanları dışarıda bırakır.
  - `query` bazlı özel kısayolları, örneğin `Favorilerim`, normal modül listesine tekrar sokmaz.

- `dispatchBoosterModuleChanged(string $item)`
  - Browser event:
    - `module`
    - `item`
    - `group`
  - Sidebar’ın static layout olmasına rağmen eş zamanlı kalması için kritik.

- `boosterModuleGroup(string $item)`
  - `bestseller`, `keyword`, `trends` -> `market`
  - `commissions` -> `calculation`
  - `favorites`, `price`, `history`, `notifications` -> `tracking`
  - default -> `product`

### 2. Ana Trendyol Booster Blade

Dosya:

- `resources/views/livewire/trendyol-booster.blade.php`

Öne çıkan alan:

- Üst workspace kartı.
- Üst modül kısa yol barı.
- Arama input’u.
- Drag-scroll Alpine state’i.

Kısa yol barında Alpine state:

- `moduleSearch`
- `shortcutDragging`
- `shortcutMoved`
- `suppressShortcutClick`
- `shortcutStartX`
- `shortcutScrollLeft`

Önemli Alpine davranışları:

- `moduleMatches(value)`
  - Modül aramasını client-side filtreler.

- `startShortcutDrag(event)`
  - Mouse drag başlangıcını kaydeder.

- `moveShortcutDrag(event)`
  - Yatay scroll’u mouse movement ile değiştirir.

- `cancelShortcutClick(event)`
  - Sürükleme sonrası butona yanlışlıkla click gitmesini engeller.

### 3. Sidebar partial

Dosya:

- `resources/views/layouts/partials/trendyol-booster-menu.blade.php`

Öne çıkan davranış:

- Sidebar Trendyol Booster menüsü kendi Alpine state’iyle çalışır.
- `booster-module-changed.window` event’ini dinler.
- URL query’den de state okuyabilir:
  - `?booster=stock`
  - `?booster=tracking&favorites=1`

Önemli state’ler:

- `boosterOpen`
- `activeItem`
- `openGroup`
- `itemGroups`

### 4. Ana layout

Dosya:

- `resources/views/layouts/app.blade.php`

Önemli değişiklik:

- `Pazaryeri` menüsünün aktifliği Trendyol Booster route’unda pasif bırakıldı.
- Trendyol Booster ayrı ana menü gibi eklendi.
- `boosterMenuGroups` burada da tanımlı.

Not:

- Şu an `boosterModuleGroups()` Livewire tarafında ve `boosterMenuGroups` layout tarafında benzer veri tekrarına sahip.
- Gelecek refactor için bu yapı tek config/helper kaynağına alınabilir.

### 5. Testler

Dosya:

- `tests/Feature/TrendyolBoosterTest.php`

Güncellenen/eklenen beklentiler:

- Üst kısa yol barında `data-testid="booster-module-search"` görünüyor.
- Üst kısa yol barında `data-testid="booster-module-tabs"` görünüyor.
- Tüm ana modüller görünüyor.
- Yakında modüller görünüyor.
- `openFavorites()`:
  - `activeModule = tracking`
  - `favoritesOnly = true`
  - `booster-module-changed` event item: `favorites`, group: `tracking`
- `setActiveModule('stock')` favori filtresini temizliyor.
- Sidebar grupları hazır:
  - `booster-group-product`
  - `booster-group-calculation`
  - `booster-group-market`
  - `booster-group-tracking`

## Bu sohbet içinde yapılan son somut UI değişiklikleri

### Üst modül barı tüm modülleri gösteriyor

Önceden sadece birkaç modül vardı:

- Ürün Analizi
- Ürün Karşılaştırma
- Ürün Alım Kararı
- Pazar Karşılaştırması
- Booster Radar
- Stok Sorgulama
- Rakip Takibi

Şimdi tüm modül ailesi var:

- Çalışan modüller tek tıkla açılır.
- Planlanan modüller `Yakında` olarak pasif görünür.

### Üst modül barı mouse ile sürüklenebilir

Kullanıcı “mouse ile sağa sola çekilerek menüde gezilebilinsin” dedi.

Eklendi:

- `@pointerdown`
- `@pointermove.window`
- `@pointerup.window`
- `@pointercancel.window`
- `cursor-grab`
- `cursor-grabbing`

Kritik detay:

- Drag mesafesi 4px üstüne çıkarsa click bastırılıyor.
- Böylece kullanıcı scroll yapmak isterken modül açılmaz.

### Arama input’u düzeltildi

Önce daha dekoratif bir arama tasarımı yapıldı ama kullanıcı screenshot ile kayma olduğunu gösterdi.

Sorun:

- Search icon ve `ARA` rozeti input’tan kopuk duruyordu.
- Absolute positioning ve küçük rozet input yüksekliğiyle iyi hizalanmamıştı.

Düzeltme:

- Tek satır `label` yüzeyi kullanıldı.
- İkon, input ve clear button flex içinde hizalandı.
- `ARA` rozeti kaldırıldı.
- Daha sade, stabil, ZOLM açık panel sistemine uyumlu hale geldi.

## Veri / ürün analizi hedefleri

Kullanıcı ürün analizinde urunanalizi.com benzeri şu verileri istedi:

- Ürün ID
- Başlık
- Marka
- Kategori
- Görsel
- Fiyat
- Değerlendirme sayısı
- Yorum sayısı
- Puan
- Favori sayısı
- Sepete eklenme
- Son 24 saat görüntüleme
- Önceki / güncel / değişim karşılaştırması
- Son yorumlar
- Favoriye al/çıkar
- Analiz edilmiş ürün listesi
- Detayda eski veri ve tek tıkla anlık veri kıyaslama

Önemli not:

- Trendyol bazı metrikleri her zaman yayınlamıyor.
- `Sepete eklenme` ve `Son 24 saatte görüntüleme` bazı ürünlerde boş gelirse bunun nedeni veri kaynağının yayınlamaması olabilir.
- Uygulamada bu durum `Yayınlanmıyor` gibi açık etiketle gösterildi/gösterilmeli; kesin olmayan veri sıfır gibi yazılmamalı.

## Booster Radar hedef algoritması

Kullanıcının “Trendyol Booster’ın kalbi” dediği 5. bölüm Booster Radar.

Amaç:

- Bir ürün analiz edildiyse,
- karşılaştırıldıysa,
- alım kararına sokulduysa,
- pazar karşılaştırmasına dahil edildiyse,
- veya Chrome eklentisinden “ürünü takibe al” yapıldıysa,

ürün Booster Radar’a kayıtlanacak.

Sonra otomatik tarama ile zaman içinde şu metrikler üretilecek:

- Saatlik/günlük stok değişimi
- Tahmini satış hızı
- Tahmini stok bitiş süresi
- Fiyat geçmişi
- Minimum / maksimum / ortalama fiyat
- Favori artış hızı
- Değerlendirme artış hızı
- Yorum artış hızı
- Soru artış hızı
- Kategori sıralaması değişimi
- Satıcı puanı değişimi
- Kampanya öncesi / sonrası performans
- Yorum duygu analizi
- En sık geçen olumlu / olumsuz konular
- Rakiplere göre fiyat ve ilgi skoru
- Fırsat / rekabet / risk puanı

Kesin alınamayan veya Trendyol’un yayınlamadığı metrikler:

- Kesin günlük satış adedi
- Kesin ürün cirosu
- Dönüşüm oranı
- İade oranı
- Reklam harcaması ve ROAS
- Bazı ürünlerde sepet ve 24 saat görüntüleme

Bu metrikler için yaklaşım:

- Eğer Trendyol yayınlıyorsa al.
- Yayınlamıyorsa “tahmini” olarak hesapla.
- Tahmini ve kesin değerler UI’da ayrılmalı.
- Veri güven puanı gösterilmeli.

## Daha önce konuşulan / yapılmış diğer UI kararları

### Analiz edilen ürün kartları

Kullanıcı analiz edilen ürünlerin alt listede çok büyük kartlar olduğunu söyledi.

İstenen yön:

- Kartlar daha minimal olsun.
- Tıklanınca detay açılan yapı olsun.
- Aksiyonlar tek tek açık durmak yerine aksiyon ikonu/popup/popover içinde olsun.
- Sil, sırala, kategoriye göre filtrele, favoriye ekle/çıkar gibi aksiyonlar kompakt kullanılmalı.

### Menü eş zamanlılığı

Önemli bug:

- İçerik `Stok Sorgulama` iken sidebar’da `Ürün Analizi` aktif kalıyordu.

Çözüm:

- Livewire event + Alpine sidebar sync.

## Komutlar / doğrulamalar

Son doğrulamalarda kullanılan komutlar:

```bash
./vendor/bin/sail php -l app/Livewire/TrendyolBooster.php
./vendor/bin/sail artisan view:clear && ./vendor/bin/sail artisan view:cache
./vendor/bin/sail artisan test tests/Feature/TrendyolBoosterTest.php
git diff --check -- resources/views/livewire/trendyol-booster.blade.php app/Livewire/TrendyolBooster.php resources/views/layouts/app.blade.php tests/Feature/TrendyolBoosterTest.php
```

Son görülen sonuç:

```text
Tests: 51 passed (344 assertions)
```

## Tarayıcı doğrulama notu

In-app browser ile local sayfaya gidilmeye çalışıldı:

```text
http://localhost/marketplace-trendyol-booster?booster=tracking
```

Ancak sayfa local auth nedeniyle `/login` adresine yönlendi. Bu yüzden görsel doğrulama otomatik tamamlanamadı.

Yeni sohbette görsel kontrol istenirse:

1. Kullanıcı local app’te giriş yapmalı.
2. Sonra Codex’e “şimdi kontrol et” demeli.
3. Browser skill ile localhost üzerinde görsel kontrol yapılmalı.

## Dikkat edilmesi gereken git durumu

Repo genel olarak dirty.

Önemli:

- Mevcut değişikliklerin çoğu kullanıcı/önceki çalışma kaynaklı olabilir.
- `git reset --hard`, `git checkout --` gibi destructive komutlar kullanılmamalı.
- Staging/commit kullanıcı istemeden yapılmamalı.
- Trendyol Booster ile ilgili birçok dosya `??` untracked görünebilir; bu normal mevcut çalışma durumunun parçası.

## Yeni sohbette devam etmek için önerilen başlangıç prompt’u

Yeni sohbet açınca şunu yazabilirsin:

```text
Bu projede Trendyol Booster geliştirmesine kaldığımız yerden devam edeceğiz.
Önce /Volumes/TWINMOS/zolm/docs/trendyol-booster-handoff-2026-06-29.md dosyasını oku.
Sonra mevcut dosyaları incele:
- app/Livewire/TrendyolBooster.php
- resources/views/livewire/trendyol-booster.blade.php
- resources/views/layouts/partials/trendyol-booster-menu.blade.php
- resources/views/layouts/app.blade.php
- tests/Feature/TrendyolBoosterTest.php

ZOLM Kurumsal Açık Panel Sistemi kurallarına bağlı kal.
Mevcut dirty worktree’i koru, destructive git komutu kullanma.
Önce son durumu bana özetle, sonra istediğim geliştirmeyi uygula.
```

## Yakın sonraki iş önerileri

1. Sidebar ve Livewire modül listesi tek kaynağa alınabilir.
   - Şu an layout ve Livewire tarafında benzer grup listesi var.
   - Bunu config/helper sınıfına taşımak iyi olur.

2. Üst kısa yol barına hafif sağ/sol fade eklenebilir.
   - Kullanıcı yatayda daha fazla modül olduğunu anlar.

3. Kısa yol barında grup ayrımı düşünülebilir.
   - Örneğin hover’da küçük grup adı tooltip.
   - Veya aktif grup için minik label.

4. Booster Radar gerçek zamanlı otomatik tarama ekranı iyileştirilebilir.
   - Son tarama
   - Sıradaki tarama
   - Veri güven puanı
   - 24s stok düşüşü
   - Stok bitiş tahmini

5. Analiz edilen ürünler listesi daha kompakt hale getirilebilir.
   - Aksiyonlar popover içine alınabilir.
   - Sil / favori / anlık kıyasla / takip planı / detay aynı menüye toplanabilir.

6. Chrome eklentisiyle “Ürünü Takibe Al” akışı görsel olarak tekrar test edilmeli.

## Kaldığımız son nokta

Son kullanıcı geri bildirimi:

- Üst modül arama input’u kaymış görünüyordu.

Son yapılan düzeltme:

- Arama input’u tek parça flex yüzeye çevrildi.
- `ARA` rozeti kaldırıldı.
- İkon/input/clear button hizası düzeltildi.
- Blade cache başarılı.
- Trendyol Booster testleri başarılı.

