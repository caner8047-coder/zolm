# Siparişler Ürünleşmiş UI — 23 Temmuz 2026

## Notion taslağı

### Başlık ve özet

Siparişler sayfası, e-ticaret satıcısının günlük operasyon akışını öne çıkarırken Ürün Yönetimi modülüyle aynı ürün ailesi hissini verecek şekilde yeniden düzenlendi. Tekrarlayan özetler kaldırıldı; gerçek aksiyonlar, gün içindeki ürün satış ritmi, filtreler ve sipariş listesi ortak bir görsel hiyerarşiye alındı.

### İş ihtiyacı ve kullanıcıya etkisi

Önceki tasarım, aynı sipariş ve finans verisini birden fazla kartta gösterdiği için sipariş listesini ilk ekranın altına itiyordu. Yeni tasarımda kullanıcı:

- Son senkron zamanını ve mağaza bağlantısının canlı durumunu görür.
- Sipariş senkronunu üst çalışma alanındaki birincil aksiyondan başlatır.
- İçe aktarım, eşleştirme, finans, dışa aktarım, entegrasyon ve çıktı ayarlarına tek aksiyon şeridinden ulaşır.
- Toplam sipariş adedinin yanında gün içinde satılan ürünlerin saatlik ritmini görür.
- Grafiğin üzerine gelerek veya mobilde dokunarak ilgili saatteki ürün ve sipariş adetlerini inceler.
- Sipariş, müşteri veya kargo koduyla tek alandan arama yapar.
- İkincil filtreleri ve Excel içe aktarım araçlarını yalnızca ihtiyaç duyduğunda açar.
- Gerektiğinde çalışma alanı üst yüzeyini daraltarak listeye odaklanır.

### Teknik yaklaşım

- Blade bilgi mimarisi Ürün Yönetimi modülünün görsel ritmine uyarlandı.
- Çalışma alanı etiketi, canlılık/son senkron bilgisi, aksiyon şeridi, günlük satış grafiği ve daraltma kontrolü eklendi.
- Livewire katmanında kullanıcının bugünkü siparişleri, sipariş satırı adetleri üzerinden 24 saatlik ürün satış serisine dönüştürüldü.
- Grafik seçili mağaza ve pazaryeri kapsamına göre yeniden hesaplanır; iptal, iade, ret ve refund durumları satış toplamına dahil edilmez.
- Diagnostik notlardan üretilen uyarı accordion'u ve legacy guidance kartı, gerçek sipariş satırı durumuyla çelişebildiği için Siparişler görünümünden kaldırıldı.
- `Sipariş senkronunu başlat` aksiyonu üst komut alanına taşındı. Seçili mağaza/pazaryeri varsa bu kapsamı, seçim yoksa kullanıcının aktif ve bağlantısı tamamlanmış mağazalarını senkronlar.
- Finans kapsamı ve eşleşme riski KPI kartları odak dağıttıkları için kaldırıldı.
- Tekrarlayan hero/KPI, sağ araç rayı ve mobilde karşılığı olmayan seçim kontrolü kaldırıldı.
- Birincil filtreler ile ikincil filtreler progressive disclosure yaklaşımıyla ayrıldı.
- Mevcut tablo kolon özelleştirme, sıralama, toplu işlem ve detay paneli davranışları korundu.

### Değiştirilen bileşenler

- `app/Livewire/MarketplaceOrders.php`
- `resources/views/livewire/marketplace-orders.blade.php`
- `tests/Feature/MarketplaceOrdersGuidanceTest.php`

### Veri modeli veya migration değişiklikleri

Yok.

### Kullanım adımları

1. Güncel siparişleri almak için üstteki `Sipariş senkronunu başlat` aksiyonunu kullanın.
2. Üst çalışma alanında bugünkü ürün satış ritmini inceleyin.
3. Saatlik ürün ve sipariş adedi için grafiğin üzerine gelin veya mobilde grafiğe dokunun.
4. Ana arama alanından sipariş no, müşteri veya kargo kodu arayın.
5. Günlük listeyi durum filtresiyle daraltın.
6. Pazaryeri, mağaza, tarih, ürün, müşteri, finans ve kâr seçenekleri için `Filtreler` panelini açın.
7. Excel içe aktarımı için `İçe Aktar` aksiyonunu kullanın.

### Yetki ve feature flag bilgileri

Yeni yetki veya feature flag eklenmedi. Mevcut sipariş ve toplu işlem feature flag'leri korunur.

### Test kapsamı

- Sipariş görünümü, renk etiketi ve aksiyon davranışları
- Diagnostik ve legacy uyarı yüzeylerinin görünümden kaldırılması
- Üst çalışma alanından sipariş senkronunun kuyruğa alınması
- Sipariş durum sunumu
- Arama, sıralama ve tarih filtreleri
- Toplu paket aksiyonu
- Masaüstü ve mobil gerçek tarayıcı render kontrolü
- Saatlik ürün adedi hesaplaması ve iptal siparişlerinin satış toplamından çıkarılması
- Grafik hover/tap detayı, filtre temizleme, içe aktarım ve çalışma alanı daraltma etkileşimleri

### Bilinen sınırlamalar

- Genel `php artisan view:cache`, projede mevcut olmayan HR view dizini nedeniyle çalışmıyor. İlgili Blade dosyası hedefli olarak başarıyla derlendi ve gerçek HTTP render ile doğrulandı.
- Mobilde kolon özelleştirme gösterilmez; mobil kart görünümü kullanılır.

### Geri alma planı

Blade ve ilgili görünüm testindeki bu değişiklikler geri alınabilir. Veri modeli değişmediği için veri geri alma işlemi gerekmez.

### İlgili commit veya PR bağlantıları

Henüz commit veya PR oluşturulmadı.

### Yayın tarihi ve sorumlu kişi

- Planlanan tarih: 23 Temmuz 2026
- Sorumlu: ZOLM geliştirme ekibi

## Decision log

### Siparişler sayfasında ürün ailesi uyumu ve progressive disclosure — 23 Temmuz 2026

- Durum: Değiştirildi
- Bağlam: Günlük sipariş operasyonu, tekrarlayan özetler ve sürekli açık ikincil araçlar nedeniyle ilk ekranın altında kalıyordu.
- Değerlendirilen seçenekler:
  - Tüm mevcut kartları görsel olarak küçültmek
  - KPI alanını tamamen kaldırmak
  - Sadece iki aksiyon ve dört sayaçtan oluşan minimal utility panel kullanmak
  - Ürün Yönetimi ile aynı görsel ritmi, siparişe özel aksiyon ve KPI'larla uygulamak
- Önceki yaklaşım: Ürün Yönetimi ailesindeki başlık, aksiyon şeridi ve üçlü KPI düzeni kullanıldı; ikincil filtreler ve legacy aktarım araçları ihtiyaç halinde açılan panellerde tutuldu.
- Gerekçe: Kullanıcının günlük kararlarını desteklerken ZOLM modülleri arasında tutarlı, ürünleşmiş bir deneyim ve mevcut geriye uyumluluğu birlikte korur.
- Olumlu sonuçlar: Daha güçlü modül kimliği, net aksiyon hiyerarşisi, daha az tekrar, mobilde kontrollü yoğunluk.
- Olumsuz sonuçlar: İleri seviye filtrelere ve legacy içe aktarım araçlarına erişmek için bir ek tıklama gerekir.
- Değişiklik: Üçlü KPI alanı, aşağıdaki günlük ürün satış ritmi kararıyla tek grafik kartına dönüştürüldü. Başlık, aksiyon şeridi ve progressive disclosure yaklaşımı korunur.

### Tekrarlayan KPI yerine günlük ürün satış ritmi — 23 Temmuz 2026

- Durum: Kabul Edildi
- Bağlam: Finans kapsamı ve eşleşme riski sipariş listesinden önce görsel odak oluşturuyor; sipariş adedinin altında toplam ciroyu göstermek ise günlük operasyon ritmi hakkında yeterli bilgi vermiyordu.
- Değerlendirilen seçenekler:
  - Üç KPI kartını korumak
  - KPI alanını tamamen kaldırmak
  - Ciro grafiği göstermek
  - Gerçek sipariş satırlarından saatlik ürün adedi grafiği üretmek
- Seçilen yaklaşım: Tek, tam genişlikte Siparişler kartı içinde günün 24 saatini gösteren ürün adedi çizgisi ve saat/ürün/sipariş detaylı hover-tap kartı kullanıldı.
- Gerekçe: Satıcıya yalnızca toplam sonucu değil, siparişlerin gün içindeki yoğunluk zamanlarını gösterir ve ekranı ek KPI kartlarıyla bölmez.
- Olumlu sonuçlar: Daha az görsel gürültü, gerçek operasyon ritmi, mobilde korunabilen tek odak, sahte veri gerektirmeyen dinamik görünüm.
- Olumsuz sonuçlar: Gün içinde satış yoksa grafik düz çizgi ve boş durum mesajı gösterir.
- Yeniden değerlendirme koşulu: Sipariş hacmi büyüdüğünde saatlik seri için sorgu süresi performans bütçesini aşarsa özet tablo veya cache yaklaşımı değerlendirilir.

### Diagnostik uyarıyı kaldırıp senkronu birincil aksiyon yapmak — 24 Temmuz 2026

- Durum: Kabul Edildi
- Bağlam: Senkron diagnostik notlarından gelen “Ürün eşleşme alanları eksik” uyarısı, tabloda tüm satırlar eşleşmiş olsa bile görünerek kullanıcıda yanlış problem algısı oluşturuyordu.
- Değerlendirilen seçenekler:
  - Uyarının veri kuralını değiştirmek
  - Uyarıyı daha küçük göstermek
  - Uyarı yüzeyini kaldırıp yalnızca çalışan senkron aksiyonunu üst çalışma alanına taşımak
- Seçilen yaklaşım: Diagnostik ve legacy guidance yüzeyleri Siparişler Blade görünümünden kaldırıldı; senkron aksiyonu üst komut alanında tam genişlikte birincil buton oldu.
- Gerekçe: Siparişler sayfası gerçek sipariş kayıtlarına ve günlük operasyona odaklanır; diagnostik motor verileri başka sağlık/entegrasyon ekranlarında değerlendirilebilir.
- Olumlu sonuçlar: Yanlış uyarı algısı ortadan kalkar, liste daha yukarı taşınır, en değerli aksiyon her ekran boyutunda görünür kalır.
- Olumsuz sonuçlar: Legacy yansıtma guidance kartına Siparişler üzerinden doğrudan erişim kaldırılmıştır; ilgili araçlar mevcut içe aktarım panelinde kalır.
- Yeniden değerlendirme koşulu: Sipariş satırı verisiyle birebir doğrulanan yeni bir operasyonel uyarı ihtiyacı oluşursa ayrı ve güvenilir bir kural olarak eklenir.

## Slack taslağı

```text
🚀 Siparişler komut alanı sadeleştirildi

- Ne değişti: Yanıltıcı diagnostik/legacy uyarı yüzeyi kaldırıldı; Sipariş senkronunu başlat aksiyonu üst çalışma alanına taşındı.
- Kullanıcıya etkisi: Satıcı gerçek sipariş durumuyla çelişen uyarıları görmez ve senkronu ilk ekrandan başlatabilir.
- Test durumu: 32 test / 144 assertion başarılı; kuyruklama, masaüstü ve mobil yerleşim doğrulandı.
- Yayın / feature flag durumu: Yeni feature flag yok; mevcut sipariş aksiyon flag'leri korunuyor.
- Dikkat edilmesi gerekenler: Genel view cache komutunda repodaki eksik HR view dizini kaynaklı mevcut hata devam ediyor.
- Dokümantasyon: docs/marketplace-orders-productized-ui-2026-07-23.md
- PR / commit: Henüz oluşturulmadı.
```
