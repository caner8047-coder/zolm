# ikas Admin API Pilot Entegrasyonu

**Tarih:** 2026-07-22  
**Durum:** Kod ve mock sözleşme testleri tamamlandı; gerçek mağaza kabul testi bekliyor

## Özet

ZOLM'e ikas özel uygulama modeliyle çalışan ayrı bir GraphQL connector eklendi. Connector sipariş, ürün, ödeme transaction'ı ve iade durumlarını okur; webhook imzasını doğrular; feature flag açıldığında fiyat ve stok yazabilir. API'nin ZOLM ortak kolonlarına sığmayan alanları `raw_payload` içinde kayıpsız korur.

## İş ihtiyacı ve kullanıcı etkisi

ikas kullanan müşteri, Client ID ve Client Secret bilgilerini girip bağlantıyı doğrulayabilir; sipariş/paket/kargo, ürün/varyant/stok/fiyat ve ödeme verilerini ZOLM modüllerinde kullanabilir. Sistem gerçek mağaza ile kabul edilene kadar kanalı “Pilot” gösterir ve riskli yazmaları otomatik açmaz.

## Teknik yaklaşım

- OAuth endpoint: `https://api.myikas.com/api/admin/oauth/token`
- GraphQL endpoint: `https://api.myikas.com/api/v2/admin/graphql`
- Kimlik doğrulama: `client_credentials`; token süresinden güvenli pay bırakılarak cache edilir, 401'de bir kez yenilenir.
- Sayfalama: ikas üst sınırı olan 200 kayıt/sayfa korunur; senkron başına sayfa limiti config ile sınırlandırılır.
- Finans: `listOrderTransactions`; sipariş başına sorgu maliyetini sınırlamak için taranan sipariş sayısı config ile sınırlıdır.
- İade: ayrı claim listesi yerine `REFUND_REQUESTED`, `PARTIALLY_REFUNDED`, `REFUNDED`, `REFUND_REJECTED` sipariş durumlarından türetilir.
- Webhook: zarf içindeki string `data`, öncelikle özel uygulama Client Secret (yoksa açık Webhook Secret) ile HMAC-SHA256 doğrulanır; imza raw payload'a yazılmaz.
- Yazma: güncel `updateVariantPrices` ve `saveVariantStocks`; şema geçişindeki eski `saveVariantPrices` ve `saveProductStockLocations` yalnız bilinmeyen alan hatasında fallback olur.

## Çekilen veri kapsamı

### Sipariş

- Sipariş ve ödeme durumları, tarih, tutar, döviz ve kur
- Müşteri, fatura ve teslimat adresleri, vergi/kimlik bilgileri
- Ödeme yöntemi, vergi, kargo ücreti, kupon ve kampanya düzeltmeleri
- Fatura kayıtları ve PDF varlık bilgisi
- Paket, paket satırları, kargo firması, takip/barkod ve durum
- Ürün satırı, SKU, barkod, fiyat, indirim, KDV, kategori, marka ve varyant

### Ürün

- Ürün, varyant, SKU, barkod, açıklama ve kısa açıklama
- Fiyat listeleri, alış/satış/indirim fiyatı ve para birimi
- Lokasyon bazlı stok ve toplam stok
- Görsel, özellik, varyant değerleri, kategori, marka, etiket ve çeviri
- SEO metadata, satış kanalı durumu, ağırlık ve vergi

### Finans ve iade

- İşlem tutarı, para birimi, işlem tipi/durumu, gateway, banka/kart özeti, taksit, hata ve satır detayları
- İade sipariş durumu, neden, müşteri notu, ürün satırları ve kargo takibi

## Değiştirilen bileşenler

- `app/Services/Marketplace/Connectors/IkasConnector.php`
- `app/Services/Marketplace/MarketplaceConnectorManager.php`
- `app/Services/Marketplace/MarketplaceProviderRegistry.php`
- `app/Services/Marketplace/MarketplaceConnectionReadinessService.php`
- `app/Models/IntegrationSyncProfile.php`
- `app/Livewire/MarketplaceIntegrations.php`
- `config/marketplace.php`
- `tests/Feature/IkasConnectorTest.php`

## Veri modeli ve migration

Migration yoktur. Mevcut ChannelOrder, ChannelProduct, OrderFinancialEvent ve ChannelClaim modelleri kullanılır. Sağlayıcıya özgü geniş içerik mevcut JSON raw payload alanlarında saklanır.

## Yetki ve feature flag

- Okuma: Read Orders, Read Products, Read Customers, Read Inventories.
- Yazma: Write Products ve Write Inventories.
- `finance_enabled=false`, `price_push_enabled=false`, `stock_push_enabled=false` varsayılandır.
- Webhook açık gelir; endpoint kaydı ve Client Secret tabanlı imza canlı kabulte doğrulanmalıdır.

## Test kapsamı

- Connector manager ve capability parity
- Client credentials token ve merchant doğrulama
- Geniş sipariş/paket/satır normalizasyonu
- Ürün/varyant/fiyat/stok/görsel/metadata normalizasyonu
- Transaction finans olayı ve iade türetimi
- Güncel fiyat/stok mutation payload'ları
- Webhook HMAC ve imza temizleme
- Readiness ve UI masaüstü/mobil kontrolü

## Bilinen sınırlamalar

- Gerçek ikas mağazası ve sandbox credential bulunmadığı için canlı GraphQL/scope smoke testi yapılmadı.
- Finans akışı ödeme transaction'ıdır; ikas ödeme kuruluşu payout/hakediş mutabakatı değildir.
- İade, sipariş durumundan türetilir; bağımsız iade talebi nesnesi resmî şemada açılırsa ayrı connector akışına taşınmalıdır.
- Finans sorgusu sipariş başına transaction çağrısı yapar; canlı pilotta süre ve rate limit ölçülmelidir.

## Kabul adımları

1. Test veya düşük hacimli ikas mağazasında özel uygulama oluşturun.
2. Read scope'ları açıp Client ID/Secret ile bağlantıyı doğrulayın.
3. Son 24 saat sipariş ve ürün senkronunu çalıştırıp panelle karşılaştırın.
4. Transaction toplamını sipariş ödeme ekranıyla karşılaştırın.
5. Webhook endpointini kaydedip order/product/stock olaylarında HMAC doğrulamasını kontrol edin.
6. Salt-okuma kabulünden sonra tek SKU için fiyat/stok canary testi uygulayın.

## Geri alma planı

Provider registry kaydı ve manager eşlemesi birlikte kaldırılır; ikas sync profile'ları pasife alınır. Migration olmadığı için veri şeması geri alınmaz. Daha önce çekilmiş raw payload kayıtları denetim izi olarak kalabilir.

## Commit planı

1. `feat: add ikas admin api pilot connector`
2. `test: cover ikas oauth sync writes and webhooks`
3. `docs: document ikas pilot acceptance workflow`

Commit oluşturulmadı.

## Notion taslağı

**Başlık:** ikas Admin API Pilot Entegrasyonu  
**Özet:** ZOLM'e ikas özel uygulama OAuth, sipariş/ürün/transaction okuma, iade türetimi, webhook doğrulama ve kontrollü fiyat/stok yazma desteği eklendi.  
**Kullanım:** Özel uygulama oluştur, read scope'ları aç, Client ID/Secret gir, bağlantıyı doğrula, dar tarihli senkron çalıştır.  
**Yetki/flag:** Finans ve fiyat/stok yazmaları varsayılan kapalı.  
**Test:** Mock sözleşme, readiness, capability parity ve responsive UI testleri geçti.  
**Sınırlama:** Gerçek mağaza kabul testi ve rate-limit ölçümü bekliyor.  
**Rollback:** ikas provider/manager eşlemesini kaldır, profil flag'lerini kapat; migration yok.  
**PR/commit:** Henüz oluşturulmadı.  
**Yayın tarihi / sorumlu:** Belirlenecek.

## Slack taslağı

🚀 ikas Admin API pilot entegrasyonu hazır

- Ne değişti: Client credentials bağlantısı; sipariş, ürün, transaction ve iade okuma; webhook HMAC; kontrollü fiyat/stok yazma eklendi.
- Kullanıcıya etkisi: ikas mağazası ZOLM entegrasyon ekranından “Pilot” olarak bağlanabilir ve geniş veri seti çekilebilir.
- Test durumu: Mock connector, readiness, capability parity ile masaüstü/390 px UI kontrolleri geçti.
- Yayın / feature flag durumu: Finans ve fiyat/stok yazmaları varsayılan kapalı.
- Dikkat edilmesi gerekenler: Gerçek mağaza scope, webhook ve rate-limit kabul testi yapılmalı.
- Dokümantasyon: `docs/integrations/marketplace/IKAS_PILOT_IMPLEMENTATION.md`
- PR / commit: Henüz oluşturulmadı.
