# ADR-005 — T-Soft REST1 Token ve Kabiliyet Sınırı

- **Tarih:** 2026-07-22
- **Durum:** Kabul Edildi

## Bağlam

T-Soft'un resmî yardım merkezi ve teknik dokümanı, mağazaya özel REST1 Web Servis yüzeyinde kullanıcı/parola ile süreli token alınmasını ve her metodun ayrıca yetkilendirilebilmesini tanımlar. T-Soft'un yeni geliştirici portalında OAuth tabanlı daha yeni bir API yüzeyi örneklenmekle birlikte ayrıntılı uygulama dokümanı henüz genel kullanıma açık değildir. Sipariş ödeme alanları okunabilir; ancak bağımsız settlement, ortak webhook, müşteri soru-cevap ve güvenilir alt ürün yazma sözleşmeleri bu pilot için doğrulanamamıştır.

## Değerlendirilen seçenekler

1. Henüz ayrıntılı sözleşmesi yayımlanmayan yeni API/OAuth yüzeyini varsayımlarla uygulamak ve geniş kabiliyet ilan etmek.
2. T-Soft'u yalnız “erişim gerekli” göstermek ve mevcut ayrıntılı REST1 sözleşmesini kullanmamak.
3. Resmî ve çalışan REST1 sözleşmesini token gateway ile uygulamak; okuma yüzeylerini tam normalize etmek; ödeme/iade verisini açıkça türetilmiş olarak işaretlemek; doğrulanmayan webhook, soru-cevap ve varyant yazma kabiliyetlerini kapalı tutmak.

## Karar

Üçüncü yaklaşım seçildi.

- Mağaza HTTPS kök URL'si üzerinden `/rest1` yolları üretilir; local/private hedefler reddedilir.
- Login token'ı mağaza ve kullanıcı kimliğine göre önbelleğe alınır; `expirationTime` değeri güvenli pay bırakılarak TTL'e çevrilir.
- API'nin `success/data/message/summary` zarfı tek gateway'de doğrulanır; süresi dolmuş token hatasında bir kez yeniden login denenir.
- Sipariş, ana ürün ve alt ürün okuma ayrı REST1 çağrılarıyla yapılır; geniş kaynak cevaplar `raw_payload` içinde korunur.
- Sipariş ödeme alanları `order_payment_summary` olarak finans akışına alınır; settlement/hakediş olarak sunulmaz.
- 9 ve 10 durum kodları salt-okuma claim üretir; claim onay/red kabiliyeti ilan edilmez.
- `product/updateProducts` yalnız ana ürün `ProductCode` fiyat/stok yazmasında kullanılır. Alt ürün/varyant yazma, resmî güncel sözleşme doğrulanana kadar açık hata ile reddedilir.
- Webhook ve müşteri soru-cevap kabiliyetleri kapalıdır.
- Gerçek müşteri kabulü tamamlanana kadar provider `pilot`; finans ve yazma feature flag'leri kapalıdır.

## Sonuçlar

### Olumlu

- Ayrıntılı ve resmî olarak doğrulanabilen API yüzeyiyle çalışan bir pilot elde edilir.
- Token yenileme, zarf hata kontrolü, URL güvenliği ve metot izin hataları merkezî davranır.
- Sipariş, ürün ve varyant verisi ortak ZOLM modellerine alınırken kaynak veri kaybolmaz.
- Kullanıcıya settlement, webhook, soru-cevap veya varyant yazma konusunda gerçeğe aykırı “hazır” ilanı yapılmaz.
- Yeni migration gerektirmez ve özellikler mağaza bazlı flag'lerle geri alınabilir.

### Olumsuz

- REST1 lisansı, kullanıcı metot izinleri ve IP kısıtı müşteri hesabında ayrıca yönetilmelidir.
- Yeni API/OAuth yüzeyi genel kullanıma açıldığında ikinci bir sürüm adaptörü gerekebilir.
- Durum-türevli claim bağımsız iade yaşam döngüsü kadar ayrıntılı değildir.
- Ana ürün yazması varyant seviyesinde senkron ihtiyacını karşılamaz.
- Gerçek müşteri credential'ı olmadan kota, tüm hata kodları ve yazma davranışı yalnız resmî demo + mock testlerle kanıtlanabilir.

## Geri dönüş ve yeniden değerlendirme koşulları

- T-Soft yeni OAuth API'sinin sürümlü, ayrıntılı ve production sözleşmesini yayımlarsa REST1 ile birlikte versioned adapter yaklaşımı değerlendirilir.
- Canlı müşteri mağazasında REST1 yol veya alanları farklıysa capability kapatılır; mağaza/sürüm varyantı config ile ayrıştırılmadan sessiz fallback eklenmez.
- Bağımsız resmî iade/settlement API'si doğrulanırsa türetilmiş finans ve claim kayıtları gerçek kaynak kimlikleriyle değiştirilir.
- Resmî webhook veya soru-cevap sözleşmesi doğrulanırsa ayrı imza, replay ve kabul testleri sonrası capability açılır.
- Alt ürün güncelleme payload'ı resmî olarak doğrulanıp tek varyant canary testi geçerse fail-closed sınırı yeniden değerlendirilir.
