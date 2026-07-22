# Ticimax SOAP Web Servis Pilot Entegrasyonu

**Tarih:** 2026-07-22  
**Durum:** Kod, WSDL sözleşme testleri ve responsive UI kontrolü tamamlandı; gerçek mağaza kabul testi bekliyor

## Özet

ZOLM'e Ticimax mağazalarının kendi alan adı altındaki Ürün ve Sipariş SOAP servislerine bağlanan pilot connector eklendi. Connector sipariş, ürün/varyant ve ödeme verilerini çeker; resmî sipariş durumlarından iade/iptal taleplerini ortak claim akışına türetir; feature flag açıldığında varyant fiyatı ve stok miktarını günceller. Sağlayıcıya özgü geniş içerik `raw_payload` içinde kayıpsız korunur.

## İş ihtiyacı ve kullanıcı etkisi

Ticimax kullanan müşteri mağaza URL'si ile Ticimax'ın verdiği **Üye Kodu / Web Servis Şifresi** bilgisini ZOLM'e girerek kuruluma başlayabilir. ZOLM URL'den mağazaya özel Ürün ve Sipariş WSDL adreslerini üretir ve bağlantıyı `SelectUrunCount` ile doğrular. Ticimax web servis erişimi paket/lisans kapsamında ayrıca açılabildiği için gerçek hesapla kabul tamamlanana kadar kanal “Pilot” olarak gösterilir; finans ve fiyat/stok otomasyonları varsayılan kapalı kalır.

## Teknik yaklaşım

- Ürün servisi: `{mağaza}/Servis/UrunServis.svc?wsdl`
- Sipariş servisi: `{mağaza}/Servis/SiparisServis.svc?wsdl`
- Kimlik doğrulama: Her SOAP çağrısındaki `UyeKodu`
- Taşıma: PHP native `SoapClient`, TLS zorunlu URL, kontrollü bağlantı/okuma zaman aşımı
- Sayfalama: WSDL'deki `f` filtre ve `s` sayfalama parametreleri; en fazla 100 kayıt/sayfa
- Finans: Siparişteki `Odemeler`; gömülü ödeme yoksa `SelectSiparisOdeme`
- İade: Ticimax'ın 8–17 arası iptal/iade sipariş durumlarından salt-okuma claim türetme
- Yazma: `VaryasyonGuncelle` ve `StokAdediGuncelle`; varsayılan feature flag'ler kapalı

Resmî kaynaklar: [Ticimax Web Servis API](https://www.ticimax.com/web-servis-api/), [Ürün Servisi](https://static.ticimax.com/dokumanlar/UrunServis.pdf), [Sipariş Servisi](https://static.ticimax.com/dokumanlar/siparisservis.pdf).

## Çekilen veri kapsamı

### Sipariş

- Sipariş kimliği/kodu, durum kodu ve açıklaması, sipariş tarihi
- Toplam, ara toplam, indirim, vergi, kargo, kur ve para birimi bağlamı
- Müşteri, e-posta, telefon, fatura ve teslimat adresleri
- Kargo entegrasyon adı, takip numarası ve teslimat bilgileri
- Ürün satırı, ürün/varyant kimlikleri, SKU, barkod, adet, fiyat, vergi ve satır durumu
- Sağlayıcının tüm sipariş cevabı `raw_payload`

### Ürün ve varyant

- Ürün kartı ve varyant kimlikleri, ad, açıklama, marka, kategori ve görseller
- SKU, barkod, aktiflik, fiyat, piyasa fiyatı, stok, KDV ve para birimi
- Varyant özellikleri ve tahmini teslim süresi
- Ürün kartı ile varyantın kaynak içeriği `raw_payload`

### Finans ve iade

- Ödeme kimliği, sipariş ilişkisi, tutar, tarih, onay durumu, POS referansı ve taksit sayısı
- İptal/iade durumundaki siparişlerden claim türü, durum, neden/not ve ürün satırları
- İade onay/red aksiyonu bu pilotta yoktur; iade akışı salt-okumadır

## Değiştirilen bileşenler

- `app/Services/Marketplace/TicimaxSoapGateway.php`
- `app/Services/Marketplace/Connectors/TicimaxConnector.php`
- `app/Services/Marketplace/MarketplaceConnectorManager.php`
- `app/Services/Marketplace/MarketplaceProviderRegistry.php`
- `app/Services/Marketplace/MarketplaceConnectionReadinessService.php`
- `app/Models/IntegrationSyncProfile.php`
- `app/Livewire/MarketplaceIntegrations.php`
- `resources/views/livewire/marketplace-integrations.blade.php`
- `config/marketplace.php`
- `tests/Feature/TicimaxConnectorTest.php`
- `tests/Feature/MarketplaceConnectionReadinessServiceTest.php`
- `tests/Feature/Livewire/Marketplace/MarketplaceIntegrationsAuthorizationTest.php`

## Veri modeli ve migration

Migration yoktur. Mevcut `MarketplaceStore`, `IntegrationConnection`, `ChannelOrder`, `ChannelProduct`, `ChannelListing`, `OrderFinancialEvent` ve `ChannelClaim` modelleri kullanılır. Üye Kodu mevcut şifreli credential JSON alanında; sağlayıcıya özgü ayrıntılar mevcut `raw_payload` alanlarında tutulur.

## Yetki ve feature flag

- `orders_enabled=true` ve `products_enabled=true` varsayılandır.
- `finance_enabled=false`, `price_push_enabled=false` ve `stock_push_enabled=false` varsayılandır.
- Sipariş durumundan salt-okuma iade/claim desteği vardır; claim onay/red yoktur.
- Resmî dokümanda ortak webhook ve müşteri soru-cevap sözleşmesi doğrulanmadığı için `webhooks=false` ve `questions=false` ilan edilir.
- Mağaza URL'si HTTPS olmalı; Üye Kodu zorunludur.

## Test kapsamı

- Connector manager ve capability parity
- Üye Kodu ile ürün sayısı üzerinden bağlantı doğrulama
- Gerçek WSDL parametre adları: `f`, `s`, `siparisId`, `odemeId`, `urun`, `ayar`, `urunler`
- Sipariş, ürün/varyant, gömülü ödeme ve ödeme fallback normalizasyonu
- Resmî iade durum kodlarından claim türetme
- Fiyat ve stok SOAP operasyon payload'ları
- Readiness, tenant yetkilendirmesi ve entegrasyon formu rehberi
- Ortak sipariş, katalog, claim ve dispatch servislerinde regresyon
- 1280 px masaüstü ve 390 px mobil görünümde yatay taşma kontrolü

## Bilinen sınırlamalar

- Gerçek Ticimax web servis paketi ve müşteri Üye Kodu bulunmadığı için canlı SOAP çağrısı, servis kotası ve WCF hata gövdesi smoke testi yapılmadı.
- Ticimax sayfasına göre web servis detayları kullanılan pakete göre ayrıca sağlanabilir; müşteri hesabında web servis yetkisi açılmadan yalnız URL/Üye Kodu girmek yeterli olmayabilir.
- Finans akışı sipariş ödeme kayıtlarını taşır; Ticimax panel muhasebe/hakediş raporuyla mutabakat yapılmadan finans otomasyonu açılmamalıdır.
- İade kaydı ayrı bir iade servisi yerine resmî sipariş durum kodlarından türetilir; kısmi iade miktarı gerçek mağaza örnekleriyle doğrulanmalıdır.
- Resmî belgede doğrulanmış webhook ve müşteri soru-cevap sözleşmesi olmadığı için bu kabiliyetler kapalıdır.
- Fiyat/stok yazma gerçek mağazada tek varyant canary testi yapılmadan açılmamalıdır.

## Kabul adımları

1. Ticimax destek/hesap yöneticisinden Web Servis API erişimini açtırın ve Üye Kodu / Web Servis Şifresi bilgisini alın.
2. Ticimax mağaza kök URL'sini ve Üye Kodu'nu ZOLM'e kaydedin; “Bağlantıyı Doğrula” çalıştırın.
3. Son 24 saat siparişlerini ve dar bir ürün sayfasını çekip Ticimax panel adet/tutarlarıyla karşılaştırın.
4. Ödeme ve iade/iptal durumlarını paneldeki örnek siparişlerle karşılaştırın.
5. Salt-okuma kabulünden sonra tek test varyantıyla fiyat ve stok canary testi yapın.
6. Mutabakat ve veri otoritesi onayından sonra finans/fiyat/stok feature flag'lerini mağaza bazında kademeli açın.

## Geri alma planı

Ticimax provider registry kaydı, connector manager eşlemesi ve sync profile'ı birlikte kaldırılır veya pasife alınır. Migration olmadığı için veri şeması rollback gerektirmez. Daha önce çekilmiş `raw_payload` kayıtları denetim izi olarak kalabilir; yazma flag'leri kapatılarak dış sistem değişikliği anında durdurulur.

## Commit planı

1. `feat: add Ticimax SOAP marketplace pilot connector`
2. `test: lock Ticimax WSDL contract and sync normalization`
3. `docs: document Ticimax pilot acceptance workflow`

Commit oluşturulmadı.

## Notion taslağı

**Başlık:** Ticimax SOAP Web Servis Pilot Entegrasyonu  
**Özet:** ZOLM'e Ticimax Ürün/Sipariş SOAP servislerinden sipariş, ürün, ödeme ve durum-türevli iade okuma; kontrollü fiyat/stok yazma desteği eklendi.  
**İş ihtiyacı ve etki:** Ticimax müşterisi mağaza URL'si ve Üye Kodu ile kuruluma başlayabilir; veriler ortak ZOLM modellerine ve kayıpsız `raw_payload` alanlarına alınır.  
**Teknik yaklaşım:** Mağazaya özel WSDL, native `SoapClient`, resmî `f/s` sözleşmesi ve mevcut connector arayüzleri.  
**Veri modeli:** Migration yok; mevcut store/connection/channel modelleri kullanıldı.  
**Kullanım:** Web servis erişimini açtır, URL/Üye Kodu gir, bağlantıyı doğrula, dar aralıklı senkronları panelle karşılaştır.  
**Yetki/flag:** Finans ve fiyat/stok yazmaları varsayılan kapalı; webhook/soru ve claim aksiyonları desteklenmiyor.  
**Test:** 75 test / 277 assertion ve masaüstü/390 px responsive kontrol geçti.  
**Sınırlama:** Gerçek Üye Kodu, paket/lisans, kota, canlı finans mutabakatı ve canary yazma kabulü bekliyor.  
**Rollback:** Provider/manager eşlemesini kaldır veya profili kapat; migration yok.  
**PR/commit:** Henüz oluşturulmadı.  
**Yayın tarihi / sorumlu:** Belirlenecek.

## Slack taslağı

🚀 Ticimax SOAP pilot entegrasyonu hazır

- Ne değişti: Sipariş, ürün/varyant, ödeme ve durum-türevli iade okuma; WSDL doğrulama; kontrollü fiyat/stok yazma eklendi.
- Kullanıcıya etkisi: Ticimax mağazası URL ve Üye Kodu ile ZOLM entegrasyon ekranından pilot olarak kurulabilir.
- Test durumu: 75 test / 277 assertion ile connector ve ortak senkron regresyonları; 1280/390 px UI kontrolleri geçti.
- Yayın / feature flag durumu: Finans ve fiyat/stok yazmaları varsayılan kapalı.
- Dikkat edilmesi gerekenler: Ticimax web servis paketi, gerçek Üye Kodu, finans mutabakatı ve tek varyant canary kabulü gerekir.
- Dokümantasyon: `docs/integrations/marketplace/TICIMAX_PILOT_IMPLEMENTATION.md`
- PR / commit: Henüz oluşturulmadı.
