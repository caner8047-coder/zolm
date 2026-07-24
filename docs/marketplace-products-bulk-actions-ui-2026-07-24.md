# Notion taslağı — Pazaryeri Ürünleri toplu işlem yüzeyi

## Başlık ve özet

Pazaryeri Ürünleri ekranındaki toplu işlemler, seçili ürün kapsamını görünür tutan ve işlemleri amacına göre gruplandıran sekmeli bir kontrol yüzeyine dönüştürüldü.

## İş ihtiyacı ve kullanıcıya etkisi

Önceki menü masaüstünde iki kez görünüyordu ve bütün işlemleri dar, uzun bir listede sunuyordu. Yeni yüzey:

- masaüstünde tek tetikleyici gösterir;
- mobilde dokunmatik kullanıma uygun alt panel açar;
- işlemleri `Hızlı`, `Fiyat & kâr`, `Maliyet & lojistik` ve `Stok` gruplarında sunar;
- seçili ürün sayısını ve işlem kapsamını panel başlığında gösterir;
- silme işlemini diğer aksiyonlardan ayırır ve seçili ürün sayısıyla onaylatır;
- mevcut toplu COGS metodunu kullanıcı arayüzüne açar.

## Teknik yaklaşım

Mevcut Livewire aksiyonları ve veri akışı korunmuştur. Değişiklik Blade/Alpine katmanında yapılmış, sekme durumu yerel Alpine state ile yönetilmiştir. Masaüstü ve mobil tetikleyicileri responsive sınıflarla birbirinden ayrılmıştır.

## Değiştirilen bileşenler

- `resources/views/livewire/mp-products-manager.blade.php`
- `tests/Feature/MpProductsManagerActionsTest.php`

## Veri modeli veya migration değişiklikleri

Yok.

## Kullanım adımları

1. Ürün listesinden bir veya daha fazla ürün seçin.
2. Mobilde `Seçili ürün işlemleri`, masaüstünde `İşlemler` düğmesini açın.
3. İlgili işlem grubunu seçin.
4. Gerekli hedef ve değerleri girip işlemi uygulayın.
5. Silme işleminde seçili ürün sayısını içeren onayı kontrol edin.

## Yetki ve feature flag bilgileri

Mevcut Pazaryeri Ürünleri erişim yetkileri geçerlidir. Yeni feature flag eklenmemiştir.

## Test kapsamı

- Toplu işlem yüzeyinin sekmeleri ve responsive panel kimlikleri
- COGS aksiyonunun arayüz bağlantısı
- Seçili ürünlerde COGS güncelleme ve tenant sınırı
- Mevcut durum, komisyon, ambalaj, lojistik, stok, fiyat ve kârlılık toplu aksiyon regresyonları

## Bilinen sınırlamalar

Panel açıkken bir Livewire aksiyonu seçimi temizlerse panel ilgili tetikleyiciyle birlikte kapanır. Bu davranış mevcut seçim temizleme akışıyla uyumludur.

## Geri alma planı

Blade toplu işlem blokları önceki tek liste düzenine döndürülür ve COGS kontrolü menüden kaldırılır. Veri modeli değişmediği için migration geri alma adımı yoktur.

## İlgili commit veya PR bağlantıları

Henüz oluşturulmadı.

## Yayın tarihi ve sorumlu kişi

- Tarih: 24.07.2026
- Sorumlu: Atanacak

---

# Decision log

## Toplu işlemleri görev tabanlı sekmelerde gruplama — 24.07.2026

- Durum: Kabul Edildi
- Bağlam: Tek ve dar açılır menü, işlem türlerini ayırt etmeyi zorlaştırıyor ve masaüstünde yineleniyordu.
- Değerlendirilen seçenekler:
  - mevcut uzun açılır menüyü yalnızca genişletmek;
  - ayrı bir tam sayfa toplu işlem akışı oluşturmak;
  - aynı command surface içinde görev tabanlı sekmeler kullanmak.
- Seçilen yaklaşım ve gerekçe: Görev tabanlı sekmeler seçildi. Kullanıcı bağlamını ürün listesinden koparmadan bilişsel yükü azaltır ve mevcut Livewire aksiyonlarını korur.
- Olumlu sonuçlar: Daha kısa tarama süresi, daha net işlem kapsamı, mobil ve masaüstünde tutarlı bilgi mimarisi.
- Olumsuz sonuçlar: Aynı Blade görünümünde responsive mobil ve masaüstü panel işaretlemesi ayrı tutulur.
- Yeniden değerlendirme koşulları: Yeni toplu işlem grupları dört sekmeye sığmazsa veya işlem ön izleme/onay gereksinimi büyürse bağımsız bir side sheet bileşenine çıkarılmalıdır.

---

# Slack taslağı

🚀 Pazaryeri Ürünleri toplu işlem menüsü yenilendi

- Ne değişti: İşlemler hızlı, fiyat & kâr, maliyet & lojistik ve stok gruplarına ayrıldı; masaüstündeki çift menü kaldırıldı; mobil alt panel eklendi.
- Kullanıcıya etkisi: Seçili ürün kapsamı daha net, sık kullanılan aksiyonlar daha hızlı ve toplu COGS güncellemesi artık erişilebilir.
- Test durumu: İlgili 10 Livewire testi geçti, 115 assertion doğrulandı.
- Yayın / feature flag durumu: Feature flag yok; henüz commit veya yayın yapılmadı.
- Dikkat edilmesi gerekenler: Canlı yayın öncesi gerçek ürün verisiyle kısa masaüstü ve mobil görsel kontrol önerilir.
- Dokümantasyon: `docs/marketplace-products-bulk-actions-ui-2026-07-24.md`
- PR / commit: Henüz yok.
