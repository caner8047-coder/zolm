# Çoklu Pazaryeri Ürünler Entegrasyon Planı

Bu plan, rakip ürün ayarları ekranındaki güçlü noktaları ZOLM'ün çoklu pazaryeri yapısına uyarlamak için hazırlanmıştır. Kapsam Trendyol, Hepsiburada, N11, Pazarama, Çiçeksepeti, Koçtaş ve WooCommerce kanallarını tek ürün merkezi altında yönetmektir.

## Amaç

- Ürün kartını yalnızca listeleme ekranı olmaktan çıkarıp fiyat, maliyet, stok, iade ve kanal eşleşme kararlarının yönetildiği operasyon yüzeyi haline getirmek.
- Pazaryeri bazlı fiyat, stok, komisyon ve eşleşme farklarını ZOLM ürün kartında görünür yapmak.
- Excel ile çalışan kullanıcıların maliyet ve operasyon verisini toplu yönetmesini kolaylaştırmak.
- Kâr hesabını gerçek operasyon maliyetlerine yaklaştırmak.

## Faz 1: Ürün Kontrol Alanları

Durum: Uygulandı.

- Ürün modeline ek gider, ek gider yüzdesi, maliyet KDV oranı, iade oranı ve teslimat tipi alanları eklendi.
- Ürün toplam maliyeti, sabit ek gider ve satış fiyatına bağlı yüzdesel ek gideri dikkate alacak şekilde güncellendi.
- Ürünler sayfasında yeni kolonlar eklendi: Ek Gider, Maliyet KDV, Desi, İade, Teslimat.
- Satır içi hızlı düzenleme ile satış fiyatı, COGS, ambalaj, kargo, desi, ek gider, maliyet KDV, iade oranı ve teslimat tipi düzenlenebilir hale getirildi.
- İade oranı sipariş geçmişinden hesaplanabilir hale getirildi. Seçili ürünler varsa seçili ürünler, yoksa mevcut sayfadaki ürünler hesaplanır.
- Excel manuel import/export akışına yeni alanlar eklendi.

## Faz 2: Çoklu Pazaryeri Eşleştirme

Durum: Uygulandı.

- Ürün satır işlem menüsüne hızlı eşleştirme aksiyonu eklendi.
- Ürün içinden bekleyen kanal kayıtları görüntülenebilir ve seçili ZOLM ürününe bağlanabilir.
- Hızlı eşleştirme; aday ürün id'si, stok kodu ve kanal ürün bilgisi üzerinden çalışır.
- Detaylı veya toplu karar gerektiren kayıtlar Eşleştirme Merkezi'ne yönlendirilir.

## Faz 3: Profesyonel Ürün Yönetişimi

Önerilen sonraki kapsam:

- Varyant ailesi aksiyonları: aynı model kodu, renk/beden varyantları veya aynı barkoda bağlı kanal kayıtlarına maliyet ve teslimat bilgisini toplu uygula.
- Kanal bazlı fiyat politikası: Trendyol, Hepsiburada, N11, Pazarama, Çiçeksepeti, Koçtaş ve WooCommerce için ayrı kâr hedefi, minimum fiyat ve kampanya tamponu.
- Kanal uygunluk matrisi: her ürün için hangi pazaryerinde eksik görsel, eksik kategori, eksik barkod, hatalı stok veya eksik komisyon olduğu tek satırda gösterilir.
- İade zekası: iade oranı yüksek ürünlerde kanal, kategori, desi, kargo firması ve teslimat tipi bazında risk nedeni önerisi.
- XML/feed bağlantısı: WooCommerce ve diğer dış katalog kaynaklarından ürün güncelleme bağlantısı.
- Toplu referans eşleştirme: aynı stok kodu/barkod/model kodu taşıyan kanal kayıtlarını ürün ailesi bazında otomatik öner.

## UI İlkeleri

- ZOLM Kurumsal Açık Panel Sistemi korunur.
- Üstte özet ve aksiyonlar, altında filtre/command bar ve tablo aynı ana ürün yüzeyinde kalır.
- Mobilde tablo yerine kart görünümü korunur; yeni maliyet ve iade alanları kart metriklerine dahil edilir.
- Kritik uyarılar command bar içine sıkıştırılmaz; gerekiyorsa ayrı kompakt guidance yüzeyinde gösterilir.

## Kabul Kriterleri

- Ürün sayfası tüm desteklenen pazaryerleri için aynı ana ürün kaydını merkez kabul eder.
- Kâr hesabı COGS, ambalaj, kargo, sabit ek gider, yüzdesel ek gider ve komisyonu birlikte dikkate alır.
- Excel export yeni alanları `setCellValueExplicit()` ile yazar.
- Import edilen metin alanları UTF-8 ve XML kontrol karakteri temizliğinden geçer.
- Mobilde ürün kartı satış, kâr, kanal, stok, maliyet, lojistik, ek gider ve iade bilgisini taşır.
- Hızlı eşleştirme yanlış kullanıcıya ait ürünü bağlamaz ve mevcut manuel eşleştirme servisini kullanır.
