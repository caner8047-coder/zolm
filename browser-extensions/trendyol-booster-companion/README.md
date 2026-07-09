# ZOLM Trendyol Booster Companion

Bu Chrome eklentisi Trendyol ürün ve mağaza sayfalarındaki görünür veriyi sayfanın içinden okuyup ZOLM Trendyol Booster companion API'ına gönderir.

Ürün sayfasında:
- ZOLM ürün analizinde fiyat, görsel, değerlendirme, yorum, puan, favori ve son 10 yorumu okuma
- Ürün kararı için `Ön izle`
- ZOLM takibine kayıt için `Takibe al`
- Daha önce takibe alınmış üründe tahmini satış, stok, risk, güven ve favori değişimini gösterme
- Satıcı/stok sinyali için `Stok sorgula`
- Tedarikçi Radar için gerçek Trendyol satıcılarını ve Google Alışveriş'teki ürün kimliği güçlü eşleşen teklifleri toplama

Mağaza sayfasında:
- Rakip mağaza ürün kartlarını yakalamak için `Mağaza tara`

Seller Panel'de:
- Fiyatlandırma, flaş ve kampanya sayfalarında ZOLM maliyetleriyle karlılık kartları
- Kampanyalarda mevcut fiyat, girilen kampanya fiyatı ve maksimum fiyat senaryosu karşılaştırması
- Trendyol katkı oranını dikkate alan satıcı indirim payı hesabı
- Sipariş satırlarında ZOLM snapshot'ı varsa kesinleşmiş, yoksa görünür tutar ve ürün maliyetiyle anlık tahmini kârlılık
- Canlı tahminlerde sipariş başına ayarlanabilir, KDV dahil platform hizmet bedeli

## Kurulum

1. Chrome'da `chrome://extensions` adresini aç.
2. Sağ üstten `Geliştirici modu`nu aç.
3. `Paketlenmemiş öğe yükle` seçeneğiyle bu klasörü seç:
   `browser-extensions/trendyol-booster-companion`
4. Chrome kartında sürümün `0.14.1` göründüğünü kontrol et. Eski sürüm görünüyorsa doğru klasörü seçip `Yeniden yükle` düğmesine bas.
5. ZOLM panelinde oturum aç.
6. Eklenti popup'ında ZOLM adresini `http://localhost` olarak bırak veya kendi adresini gir.
7. ZOLM paneli açıkken popup içinden `Oturumu test et` düğmesine bas; panel oturumu doğrulanınca modüller eklenti köprüsünü kullanır.
8. ZOLM Stok Sorgulama ekranına Trendyol ürün linkini girip `Stok sorgula` düğmesine bas. Eklenti ürünü arka planda okuyup gerçek satıcı stok verisini ZOLM'e yazar.
9. Trendyol ürün sayfasında sağ alttaki ZOLM Booster panelinden de `Ön izle`, `Takibe al` veya `Stok sorgula` düğmesini kullanabilirsin.
10. Trendyol mağaza sayfasında aynı panelden `Mağaza tara` düğmesini kullan.
11. Daha önce yüklenmiş eklentide yeni izinleri almak için `chrome://extensions` ekranındaki `Yeniden yükle` düğmesine bir kez bas.

## Kontrol ve paketleme

Repo kökünden hızlı kontrol:

```bash
npm run extension:check
```

Paket klasörü ve zip arşivi üretmek için:

```bash
npm run extension:package
```

Çıktılar:

- Paketlenmemiş klasör: `build/trendyol-booster-companion`
- Zip arşivi: `build/trendyol-booster-companion.zip`

Chrome'da test ederken `build/trendyol-booster-companion` klasörünü `Paketlenmemiş öğe yükle` ile seçebilirsiniz. Bu klasörü kullanıyorsanız her değişiklikten sonra `npm run extension:package` çalıştırıp Chrome'da `Yeniden yükle` düğmesine basın.

## Panel davranışı

- Ürün sayfasında panel `Ürün` modunda açılır ve Trendyol'un gömülü ürün durumundan ürün ID, başlık, fiyat, barkod, satıcı, stok, değerlendirme ve favori sinyallerini okur.
- Ürün Booster Radar'da takipteyse panel bunu otomatik algılar; son taramadan gelen tahmini günlük satış, risk/güven, stok ve favori farkını sayfa üzerinde gösterir.
- ZOLM ürün analizi düğmesi yorum ve social-proof servislerini kullanıcının Trendyol tarayıcı oturumundan çağırır; son 10 yorumu, sepete eklenme ve son 24 saat görüntüleme dahil yayınlanan güncel metrikleri analiz geçmişine kaydeder.
- Trendyol bazı ürünlerde sepete eklenme veya görüntüleme metriğini yayınlamaz; bu durumda ZOLM değer uydurmaz ve alanı `Yayınlanmıyor` olarak gösterir.
- ZOLM stok ekranındaki sorgu düğmesi eklenti köprüsü hazırsa ürünü kullanıcı tarayıcısında arka planda açar, veriyi kaydeder ve geçici sekmeyi kapatır.
- Mağaza sayfasında panel `Mağaza` modunda açılır ve yakalanan ürün kartı ile fiyatlı kart sayısını gösterir.
- Yorum modülü doğrulanmış mağaza URL'si ve merchant ID ile çalışır; seçilen mağaza arka planda açılır, ürünleri ön izlenir ve farklı satıcılara ait yorumlar içeri alınmaz.
- ZOLM Tedarikçi Radar ekranı, ürün sayfasındaki kimliği doğrulanabilen ana satıcı ve diğer satıcıları ilk grup olarak kaydeder; ardından Google Alışveriş (`udm=28`) ve hedef pazaryeri `site:` aramalarını okur. Marka, model kodu ve ayırt edici teknik özellikler güçlü eşleşmiyorsa sonucu göndermez.
- Eksik veri varsa panel turuncu uyarı verir; Trendyol dinamik içerik yüklediyse sayfayı aşağı kaydırıp `Yenile` kullanılabilir.
- Popup ekranı aktif Trendyol sekmesinin ürün/mağaza olarak okunup okunmadığını ayrıca gösterir.
- Kampanya karlılığı yalnızca eşik, indirim ve Trendyol katkı kuralı güvenle okunabildiğinde gösterilir; kural okunamazsa tahmin üretilmez.
- Sipariş kartında `Anlık Tahmin` görüldüğünde gerçek pazaryeri kargo ve finans kesintileri henüz senkronize değildir; senkron sonrası kart ZOLM snapshot sonucuna geçer.
- Popup'taki `Platform hizmet bedeli` KDV dahil ve sipariş başınadır. Varsayılan 9,33 TL'dir; Trendyol tarifeniz değiştiğinde buradan güncelleyebilirsiniz.

## Notlar

- ZOLM oturumu açık değilse eklenti companion API'a yazamaz.
- Backend isteği Trendyol tarafından `403` ile engellense bile bu eklenti fiyatı, barkodu ve satıcı stok sinyallerini kullanıcının Trendyol oturumundan okur.
- İlk sürümde ZOLM ürün eşleştirmesi manuel panelde yapılır; eklenti yalnızca sayfa verisini taşır.
- Trendyol'un sayfada göstermediği kapalı stok bilgisi garanti edilemez; eklenti görünür DOM sinyallerini ve manuel toplam stok girişlerini ZOLM'e taşır.
