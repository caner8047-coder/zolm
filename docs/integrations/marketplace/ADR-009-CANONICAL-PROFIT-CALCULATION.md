# ADR-009 — Kanonik pazaryeri kârlılık hesabı

- Tarih: 2026-07-23
- Durum: Kabul Edildi

## Bağlam

Ürünler ekranı, Chrome eklentisi, kampanya simülatörü ve sipariş snapshot'ları farklı kesinti kümeleri ve farklı marj tanımları kullanıyordu. Aynı ürün Ürünler ekranında 87,28 TL, hizmet bedeli ve stopajı kullanan eklentide 70,31 TL nakit net kâr gösteriyordu.

## Değerlendirilen seçenekler

1. Basit ürün formülünü korumak: Hizmet bedeli ve stopajı dışarıda bıraktığı için reddedildi.
2. Chrome JavaScript formülünü kaynak kabul etmek: Tarayıcı içinde çoğaltıldığı ve gerçek finans kayıtlarına erişemediği için reddedildi.
3. Sunucu tarafındaki fiyat simülasyon servisini kanonik sözleşme yapmak: Kabul edildi.
4. Tutar bazlı aritmetiği her veri kaynağında yeniden yazmak: Sipariş finansı geldikten sonra hizmet bedelinin kaybolmasına yol açtığı için reddedildi.

## Karar

`MarketplacePricingSimulationService` sürüm 2 kanonik tahmin sözleşmesidir. Tüm girdiler KDV dahil brüt tutardır. Stopaj KDV hariç satış bedelinden hesaplanır; komisyon, kargo ve hizmet bedeli stopaj matrahını azaltmaz.

`MarketplaceProfitCalculationService`, sürüm 2 sözleşmesinin tek tutar bazlı aritmetik çekirdeğidir. Fiyat simülatörü, V2 sipariş snapshot'ı ve legacy `UnitEconomicsService` kalemleri kendi veri kaynağından çözümler; net alacak, muhasebe kârı, nakit net kâr, satış marjı ve maliyet getirisini bu çekirdek üretir.

Motor iki sonucu birlikte üretir:

- `cash_profit`: stopaj sonrası operasyonel nakit net kâr; ürün ve fiyat kararlarının ana metriği.
- `accounting_profit`: mahsup edilebilir stopajı kalıcı gider saymayan muhasebe kârı.

`net_profit`, geriye uyumluluk için `cash_profit` ile aynı kalır. Satış marjı ve maliyet getirisi ayrı alanlardır. Kesinleşmiş siparişlerde finans hareketlerindeki gerçek komisyon, `PlatformServiceFee` ve `Stoppage` kayıtları tahminlerin üstündedir. Bir kesinti türünün finans hareketi henüz yoksa yalnızca o kalem için kanonik tahmin korunur; herhangi bir finans kaydının gelmesi diğer eksik tahminleri sıfırlamaz.

## Sonuçlar

- Ürünler ekranı ve Chrome eklentisi aynı altın senaryoda 70,31 TL sonuç üretir.
- Chrome içindeki üç ayrı formül tek `profit-calculator.js` modülünde birleşir.
- KDV dahil giderlerin indirilecek KDV'si iç yüzde yöntemiyle ayrıştırılır.
- Devreden KDV ürün kârını yapay biçimde artırmaz.
- Platform hizmet bedeli tahminde mağaza/pazaryeri ayarıdır; kesin sonuçta finans kaydıdır.
- Siparişler sayfası legacy `total_net_profit` değerini önceliklendirmez; kanonik order snapshot kullanır.
- Canlı ürün maliyeti değişirse sipariş görünümü snapshot'ın tüm kesintilerini koruyarak yalnızca maliyet farkını uygular.

## Geri dönüş ve yeniden değerlendirme

`calculation_version` alanı geriye dönüş ve mutabakat için saklanır. Trendyol'un stopaj veya hizmet bedeli veri sözleşmesi değişirse yeni bir hesap sürümü çıkarılır; sürüm 2 sessizce değiştirilmez.
