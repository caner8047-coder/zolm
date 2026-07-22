# Chrome Web Store — Türkçe Mağaza Metni

## Ürün adı

ZOLM Trendyol Booster — Kâr ve Ürün Kararı

## Kısa açıklama

Trendyol ürün, fiyat, stok, kampanya ve sipariş verisini ZOLM kârlılık ve karar motoruyla birleştirir.

## Ayrıntılı açıklama

ZOLM Trendyol Booster, ürün araştırmasından kesinleşmiş sipariş kârına uzanan karar akışını Trendyol ekranlarına taşır.

- Popup’tan barkod, ürün kodu, ürün adı veya Trendyol bağlantısıyla hızlı keşif başlatın; kamera izni gerekmez.
- Arama, kategori ve Çok Satanlar sayfalarında görünür ürün sayısı, fiyat aralığı, ortalama puan ve marka çeşitliliğini özetleyin.
- Görünür bir ürün kartını tek tıkla doğrulayın; canlı analizi kaydedip ZOLM Sat/Satma karar merkezini doğru ürün ve maliyet önerileriyle açın.
- İlk 40 görünür ürünü fırsat tarayıcıyla sıralayın; skorun fiyat, puan, yorum rekabeti, marka yoğunluğu ve veri tamlığından nasıl oluştuğunu görün.
- En fazla 40 ürünü dayanıklı karar kuyruğunda analiz edin; ilerlemeyi izleyin, geçici hataları otomatik yeniden deneyin ve kuyruk kaydını istediğiniz zaman temizleyin.
- Görünür listeyi tek tıkla zaman damgalı ZOLM pazar ölçümü olarak kaydedin ve ilgili Çok Satanlar raporunda derin analize devam edin.
- Listeden 2–4 ürünü seçin; ürün linklerini yeniden kopyalamadan ZOLM canlı karşılaştırma sonucunu otomatik başlatın.
- Seçilen 1–4 ürünü detay sayfasından doğrulatarak topluca Booster Radar takibine alın; bir ürün okunamazsa diğer ürünlerin işlemi devam etsin.
- Ürün sayfasında fiyat, görünür stok, satıcı, yorum, favori ve talep sinyallerini okuyun.
- Ürün medya merkezinde görselleri seçerek indirin, bağlantıları kopyalayın ve yayınlanan video bağlantılarını açın; genel indirme geçmişi izni gerekmez.
- Ürünü ön izleyin veya Booster Radar takibine alın.
- Gözlenen veri ile ZOLM satış tahminini ayrı görün; güven ve veri kalitesi skorunu kontrol edin.
- Seller Panel fiyatlandırma ekranında ürün bazlı tahmini net kârı görün.
- Seller Panel ürün listesi, indirim/kupon ve reklam yüzeylerinde tahmini birim ekonomiyi; reklamlarda maksimum sipariş başı reklam maliyeti ve başa baş ROAS'ı görün.
- Kampanyalarda mevcut fiyat, kampanya fiyatı ve maksimum fiyat senaryosunu karşılaştırın.
- Sipariş ekranında tahmini kârı, finans ve gerçek kargo kesintileri senkronize olduğunda kesinleşmiş kârdan ayırın.
- Rakip mağaza, stok, anahtar kelime, çok satanlar ve tedarikçi araştırmalarını ZOLM paneline taşıyın.

ZOLM kesin olmayan veriyi kesinmiş gibi göstermez. Satış hızı, stok günü ve fırsat/risk skorları tahmini olarak etiketlenir; finans snapshot’ı bulunan siparişler “Kesinleşmiş Kâr” etiketiyle gösterilir.

## Tek amaç beyanı

Trendyol’da görünen ürün ve satıcı verilerini, kullanıcının ZOLM hesabındaki ürün araştırması ve satıcı kârlılığı iş akışlarıyla birleştirmek.

## İzin gerekçeleri

- `storage`: ZOLM adresi, kullanıcının kârlılık tercihleri ve kullanıcı tarafından temizlenebilen son beş keşif aramasını saklamak.
- `activeTab`: Kullanıcının açık Trendyol sayfasını yalnız başlatılan işlem kapsamında okumak.
- `tabs`: Liste/ürün/mağaza araştırması için geçici sekme açmak, ZOLM panel sekmesini bulmak ve işlem sonunda geçici sekmeyi kapatmak.
- `scripting`: ZOLM panel köprüsünü gerektiğinde doğrulamak.
- `alarms`: Takip ve arka plan işlerini kontrollü zamanlamak.
- Trendyol alanları: Liste, ürün, mağaza ve Seller Panel’deki görünür veriyi okumak.
- Trendyol medya CDN alanları: Yalnız kullanıcının seçtiği ürün görsellerini yerel dosya olarak hazırlamak.
- ZOLM üretim alanı: Yalnız `https://m.zolm.com.tr/*` üzerindeki oturumlu panel köprüsünü çalıştırmak. Yerel geliştirme alanları mağaza ZIP'ine girmez.
- Google alanları: Yalnız kullanıcı tarafından başlatılan Tedarikçi Radar eşleşmelerini okumak.

## Mağaza varlık kontrol listesi

- 128×128 mağaza ikonu: `icons/icon-128.png`
- 16/32/48 px çalışma ikonları: `icons/`
- En az 1280×800 veya 640×400 ürün ekranı görüntüsü
- Ürün analizi ve Karar Kanıtı ekran görüntüsü
- Seller Panel kampanya kârlılığı ekran görüntüsü
- Sipariş “Tahmini / Kesinleşmiş Kâr” ekran görüntüsü
- Üretim alan adındaki `/privacy/trendyol-booster-companion` gizlilik URL’si

Üç görünümün 1280×800 yeniden üretilebilir kaynağı `store-assets/showcase.html` dosyasındadır. Görseller yalnız temsili ürün verisi kullanır ve bunun finansal karar olmadığını ekranda belirtir.
