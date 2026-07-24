# ZOLM Ürün Değişim Geçmişi — Profesyonel Excel Export

## Notion taslağı

### Başlık ve özet

Ürün değişim geçmişi Excel çıktısı; yönetici özeti, denetlenebilir kayıt defteri ve veri sözlüğü içeren kurumsal bir rapora dönüştürüldü.

### İş ihtiyacı ve kullanıcıya etkisi

Önceki çıktı temel kayıtları ve grafikleri içeriyordu; ancak ürün adı yanlış kaynaktan okunabiliyor, tarih/para/yüzde değerleri metinleşebiliyor ve ilk 1.000 kayıttan sonrası sessizce dışarıda kalıyordu.

Yeni çıktıda:

- Ürün adı `product_name` alanından, geriye uyumlu fallback ile alınır.
- Para, yüzde, adet, ondalık ve tarih alanları gerçek Excel veri tipleriyle saklanır.
- Barkod, kayıt ID ve diğer kimlikler metin olarak korunur.
- Eski/yeni görünen değerler ile hesaplanabilir sayısal değerler ayrı kolonlarda tutulur.
- Tüm geçmiş dışa aktarılır; 1.000 kayıt sınırı uygulanmaz.
- Kaynak kayıt sayısı, kontrol sonuçları ve SHA-256 bütünlük parmak izi rapora eklenir.
- Maliyet ve satış fiyatı aynı zaman ekseninde karşılaştırılır; grafiğin kaynak değerleri görünür bir tabloda sunulur.
- Ana ürün satış fiyatı geçmişi yoksa yalnızca en güncel tek mağaza/kanal serisi kullanılır; farklı mağazaların fiyatları birleştirilmez.

### Teknik yaklaşım

`MpProductChangeHistoryExportService`, PhpSpreadsheet ile üç sayfalı XLSX üretir:

1. `Analiz ve Grafik`: KPI kartları, alan bazlı değişim dağılımı, maliyet–satış fiyatı zaman serisi ve veri tutarlılığı sonuçları.
2. `Kayıtlar`: Filtrelenebilir, tiplenmiş ve teknik kimlikleri içeren kayıt defteri.
3. `Veri Sözlüğü`: Rapor metadatası, kolon tanımları ve veri tipi kuralları.

Tüm hücre yazımları `setCellValueExplicit()` ile yapılır. UTF-8 ve XML kontrol karakteri temizliği uygulanır.

### Değiştirilen bileşenler

- `app/Services/MpProductChangeHistoryExportService.php`
- `app/Livewire/MpProductsManager.php`
- `tests/Feature/MpProductChangeHistoryExportTest.php`

### Veri modeli veya migration değişiklikleri

Yok. Mevcut `mp_product_change_logs` alanları kullanılır.

### Kullanım adımları

1. Pazaryeri > Ürünler ekranında ürün detayını açın.
2. Değişim geçmişi bölümündeki `Dışarı aktar` düğmesine basın.
3. Dosya `Analiz ve Grafik` sayfasıyla açılır; kesin kayıtlar `Kayıtlar` sayfasındadır.

### Yetki ve feature flag

Mevcut kullanıcı/ürün sahipliği kontrolü korunur. Yeni feature flag eklenmedi; değişiklik mevcut yeni export davranışını güçlendirir.

### Test kapsamı

- Üç sayfalı XLSX açılabilirliği
- Ürün adı ve rapor başlıkları
- Para, yüzde, tarih ve kimlik hücre tipleri
- Metin alanlarında formül enjeksiyonu güvenliği
- 1.000 kaydı aşan geçmişin kesilmemesi
- Gerçek uygulama verisiyle uçtan uca indirme
- Formül hata taraması ve tüm sayfaların görsel kontrolü
- İki grafiğin XLSX içinde korunması ve maliyet–satış fiyatı kaynak tablosunun sayısal hücre tipleri
- Birden fazla mağaza fiyatının tek satış serisinde karıştırılmaması

### Bilinen sınırlamalar

- Çok büyük geçmişlerde tüm kayıtların tek istekte yüklenmesi bellek kullanımını artırabilir. Kayıt hacmi belirgin biçimde büyürse chunk tabanlı streaming export ayrıca değerlendirilmelidir.
- Grafik, en sık değişen ilk 12 alanı gösterir; kayıt defterinde tüm alanlar yer alır.
- Maliyet veya satış fiyatı geçmişi olmayan seriler, güncel ürün değeriyle sabit referans olarak gösterilir ve bu durum analiz sayfasında açıklanır.

### Geri alma planı

Export servisi ve Livewire sorgusundaki ilgili değişiklikler geri alınır. Veri modeli değişmediği için veri geri alma işlemi gerekmez.

### İlgili commit veya PR

Henüz oluşturulmadı.

### Yayın tarihi ve sorumlu

- Hazırlık tarihi: 24.07.2026
- Sorumlu: ZOLM geliştirme ekibi

## Decision log

### Tiplenmiş kayıt defteri ve bütünlük metadatası — 24.07.2026

- Durum: Kabul Edildi
- Bağlam: Okunabilir değerlerin tek başına metin olarak yazılması hesaplama, sıralama ve denetim güvenilirliğini azaltıyordu.
- Değerlendirilen seçenekler:
  - Yalnızca mevcut görünen değer kolonlarını biçimlendirmek
  - Görünen ve sayısal değerleri ayrı kolonlarda tutmak
  - Ham JSON snapshot alanlarını doğrudan Excel’e taşımak
- Seçilen yaklaşım: Görünen değerler ile Excel-native sayısal değerleri ayrı kolonlarda tutmak; kimlikleri metin, tarihleri Excel tarih değeri olarak saklamak ve rapora SHA-256 parmak izi eklemek.
- Gerekçe: Kullanıcı okunabilirliğini korurken filtreleme, hesaplama ve denetim izi sağlar.
- Olumlu sonuçlar: Veri tipi kaybı önlenir, kimlikler bozulmaz, rapor bağımsız olarak kontrol edilebilir.
- Olumsuz sonuçlar: Kayıtlar sayfası daha geniştir ve büyük geçmişlerde dosya boyutu/bellek kullanımı artar.
- Yeniden değerlendirme koşulu: Geçmiş hacmi tek istekte güvenli üretim sınırını aşarsa streaming/chunk export tasarlanır.

### Satış fiyatı grafiğinde tek kaynak serisi — 24.07.2026

- Durum: Kabul Edildi
- Bağlam: Bir ürün birden fazla pazaryeri mağazasında farklı satış fiyatlarına sahip olabilir. Bu değerleri tek çizgide birleştirmek yanıltıcı sıçramalar üretir.
- Değerlendirilen seçenekler:
  - Tüm mağazaların satış fiyatı kayıtlarını tek seride birleştirmek
  - Her mağaza için ayrı çizgi üretmek
  - Ana ürün fiyatını tercih etmek; yoksa en güncel tek mağaza/kanal serisini kullanmak
- Seçilen yaklaşım: Önce ana ürün `sale_price` geçmişi kullanılır. Bu geçmiş yoksa son satış fiyatı kaydının ait olduğu tek kanal kaydı veya mağaza seçilir.
- Gerekçe: Raporu okunabilir tutar ve farklı ticari bağlamlardaki fiyatların karışmasını engeller.
- Olumlu sonuçlar: Grafikteki fiyat hareketleri aynı fiyat kaynağını temsil eder; kaynak etiketi tabloda açıkça görünür.
- Olumsuz sonuçlar: Çok mağazalı karşılaştırma bu grafiğin kapsamı dışındadır.
- Yeniden değerlendirme koşulu: Kullanıcı mağaza karşılaştırması talep ederse seçilebilir veya çok serili ayrı bir analiz tasarlanır.

## Slack taslağı

```text
🚀 Ürün Değişim Geçmişi profesyonel Excel exportu tamamlandı

- Ne değişti: Çıktıya denetlenebilir kaynak tablosuyla maliyet ve satış fiyatı değişim grafiği eklendi.
- Kullanıcıya etkisi: Maliyet ile satış fiyatı aynı zaman ekseninde izlenebilir; farklı mağaza fiyatları yanlışlıkla tek çizgide birleştirilmez.
- Test durumu: XLSX içindeki iki grafik, sayısal hücre tipleri, gerçek çıktı ve görsel yerleşim doğrulandı.
- Yayın / feature flag durumu: Mevcut export aksiyonu güncellendi; yeni flag yok.
- Dikkat edilmesi gerekenler: Çok büyük geçmişlerde bellek kullanımı izlenmeli.
- Dokümantasyon: docs/marketplace-product-change-history-excel.md
- PR / commit: Henüz oluşturulmadı.
```
