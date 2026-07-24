# Notion taslağı — Kanonik pazaryeri kârlılık motoru

## Özet

Ürün, kampanya, Chrome eklentisi ve sipariş kârlılık hesapları ortak sürüm 2 sözleşmesinde birleştirildi. Ana operasyon metriği stopaj sonrası nakit net kâr; muhasebe kârı ve stopaj alacağı ayrıca gösterilir.

## İş ihtiyacı ve kullanıcı etkisi

Aynı ürünün farklı ekranlarda farklı kâr göstermesi fiyat kararlarını riske atıyordu. 839,90 TL satış örneğinde eski Ürünler ekranı 87,28 TL, kanonik hesap 70,31 TL gösteriyordu. Yeni yapı hizmet bedeli ve %1 e-ticaret stopajını dahil eder.

## Teknik yaklaşım ve değiştirilen bileşenler

- `MarketplacePricingSimulationService`: sürümlü kanonik sözleşme.
- `MarketplaceProfitCalculationService`: tüm veri kaynaklarının kullandığı tek tutar bazlı aritmetik çekirdeği.
- `MpProductsManager`: ürün/listing senaryoları ve sıralama metrikleri.
- `MarketplaceProfitSnapshotService`: tahmini hizmet bedeli, gerçek finans kaydı üstünlüğü.
- `MarketplaceOrders`: legacy kâr yerine kanonik snapshot; canlı maliyet değişiminde kesintileri koruyan gösterim.
- `UnitEconomicsService`, `MpOrder` ve `OrderDetailsService`: eski muhasebe yolu aynı kanonik çekirdeğe bağlandı.
- `MarketplaceProfitCenterQueryService`: snapshot kesintilerinin ürünlere ciro oranında dağıtılması.
- Chrome eklentisi: ortak `profit-calculator.js`.
- Kampanya simülatörü ve Booster satış kararı: doğru stopaj matrahı.

## Veri modeli ve migration

Şema değişikliği yoktur. Ayarlar JSON'unda `marketplace_products.profit.estimated_service_fee_fixed` ve `estimated_withholding_enabled` kullanılır.

## Kullanım ve yetki

Ek yetki gerektirmez. Tahmini Trendyol hizmet bedeli varsayılanı 9,33 TL'dir ve kesinleşmiş siparişte finans API verisiyle değiştirilir. Stopaj tahmini varsayılan olarak açıktır.

## Test kapsamı

Sunucu ve Chrome için aynı altın senaryo: 839,90 TL satış, %22 komisyon, 373,24 TL maliyet, 194,60 TL kargo, 9,33 TL hizmet bedeli, %10 KDV ve %1 stopaj → 70,31 TL nakit net kâr.

### 2026-07-24 fiyat güncelleme regresyonu

Ürünler tablosundaki `Ana` satış fiyatı değiştirildiğinde kâr senaryosunun eski kanal fiyatını kullanmaya devam etmesi düzeltildi. Ana fiyat artık hesaplama/teklif fiyatıdır; kanal kaydından komisyon oranı ve diğer pazaryeri kuralları alınmaya devam eder. Ana fiyat tanımlı değilse son senkronlanan kanal fiyatı yedek olarak kullanılır.

Regresyon senaryosu: 1.999 TL olan ana fiyat 5.999 TL'ye çıkarıldığında, %23 Trendyol komisyonu, 2.000 TL ürün maliyeti, 560 TL kargo, 9,33 TL hizmet bedeli ve %1 stopaj ile nakit net kâr aynı render içinde -1.048,27 TL'den +1.995,36 TL'ye güncellenir.

### 2026-07-24 sipariş kârlılığı regresyonu

Sipariş finans hareketlerinden herhangi biri geldiğinde, ayrı bir `PlatformServiceFee` hareketi henüz yoksa tahmini hizmet bedelinin yanlışlıkla sıfırlanması düzeltildi. Finans hareketi yalnızca ait olduğu kalemi kesinleştirir; eksik hizmet bedeli ve stopaj kalemleri kanonik tahminle devam eder.

Sipariş `10937279991` canlı veride yeniden hesaplandı:

- Ciro: 1.999,00 TL
- Komisyon: 459,77 TL
- Platform hizmet bedeli: 9,33 TL
- E-ticaret stopajı: 18,17 TL
- Ürün maliyeti: 3.000,00 TL
- Kendi kargo maliyeti: 560,00 TL
- Net alacak: 1.511,73 TL
- Nakit net kâr: -2.048,27 TL
- Maliyet getirisi: %-68,3

Tüm mevcut V2 sipariş snapshot'ları aynı motorla yeniden hesaplandı.

## Test kapsamı — 2026-07-24

- Fiyat simülatörü, sipariş snapshot'ı, Siparişler görünüm adaptörü, Siparişler UI regresyonları ve legacy muhasebe/birim ekonomi regresyonları: 78 test, 352 assertion başarılı.
- Chrome eklentisinin aynı sözleşmeyi kullanan hesaplayıcısı: 2 Node.js testi başarılı.
- Tam test koşusunda 2.642 test geçti. Kârlılık UI metnine bağlı tek beklenti güncellenip ilgili test tekrar başarılı çalıştırıldı; kalan 4 hata bu değişiklikten bağımsız mevcut controller bağımlılığı, şifreli test verisi ve fiyat aksiyonu feature-flag durumlarıdır.
- Gerçek tarayıcı kontrolü için uygulama içi tarayıcı oturumu sayfaya yönlendirilemedi; canlı değerler doğrudan veritabanı snapshot'ından ve servis testlerinden doğrulandı.

## Bilinen sınırlamalar

Tahmini hizmet bedeli sözleşme değişikliklerinde güncellenmelidir. Kesin sonuç için Trendyol finans kayıtlarının senkronize olması gerekir. KDV modu şirketin fatura/muhasebe politikasına göre açılmalıdır.

## Geri alma planı

Hesap sürümü 2 sonuçları sürüm alanıyla ayrılır. Uygulama geri alınırsa önceki kod kullanılabilir; veri şeması değişmediği için veri geri dönüşü gerekmez.

## Yayın

- Tarih: 2026-07-23
- Sorumlu: ZOLM geliştirme ekibi
- Commit/PR: Henüz oluşturulmadı

## Slack taslağı

🚀 Kanonik sipariş kârlılığı tamamlandı

- Ne değişti: Siparişler sayfası legacy kârı bırakıp kanonik snapshot'a geçti; eksik finans kalemlerinde hizmet bedeli ve stopaj tahmini korunuyor.
- Kullanıcıya etkisi: Ürünler, hesaplayıcı, siparişler ve eski muhasebe ekranı aynı nakit net kârı gösteriyor.
- Test durumu: 78 odaklı PHP testi / 352 assertion ve 2 Chrome eklentisi testi başarılı; 10 mevcut snapshot yeniden hesaplandı.
- Yayın / feature flag durumu: Mevcut sürüm 2 sözleşmesi içinde, ek feature flag yok.
- Dikkat edilmesi gerekenler: Gerçek `PlatformServiceFee` veya `Stoppage` hareketi geldiğinde yalnızca ilgili tahminin yerini alır.
- Dokümantasyon: ADR-009 ve bu yayın notu güncellendi.
- PR / commit: Henüz oluşturulmadı.
