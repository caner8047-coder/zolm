# IdeaSoft Admin API Pilot Entegrasyonu

**Tarih:** 2026-07-22  
**Durum:** Kod, mock sözleşme testleri ve responsive UI kontrolü tamamlandı; gerçek mağaza kabul testi bekliyor

## Özet

ZOLM'e IdeaSoft mağazalarının kendi alan adı üzerinden çalışan OAuth 2.0 `authorization_code` akışı ve Admin API connector'ı eklendi. Connector sipariş, ürün, ödeme ve iade talebi verilerini okur; resmî webhook imzasını doğrular; feature flag açıldığında ürün fiyatı ve stok miktarını güncelleyebilir. Ortak modele doğrudan sığmayan sağlayıcı alanları `raw_payload` içinde kayıpsız korunur.

## İş ihtiyacı ve kullanıcı etkisi

IdeaSoft kullanan müşteri mağaza URL, Client ID ve Client Secret bilgilerini ZOLM'e kaydedip “IdeaSoft'ta Yetkilendir” adımıyla bağlantıyı tamamlayabilir. ZOLM erişim token'ını 24 saatlik ömrü dolmadan refresh token ile yeniler ve IdeaSoft'un rotasyonla döndürdüğü yeni refresh token'ı şifreli credential alanında saklar. Gerçek mağaza kabulü tamamlanana kadar kanal kullanıcıya “Pilot” olarak gösterilir ve finans/fiyat/stok otomasyonları varsayılan kapalı kalır.

## Teknik yaklaşım

- Yetkilendirme: `{mağaza}/panel/auth`
- Token alma ve yenileme: `{mağaza}/oauth/v2/token`
- Admin API: `{mağaza}/admin-api/*`
- OAuth güvenliği: kullanıcı/mağaza bağlı, tek kullanımlık ve 10 dakika süreli `state` kaydı
- Token yönetimi: `access_token`, rotasyonlu `refresh_token`, `token_expires_at` ve `oauth_scope` mevcut şifreli credential JSON'unda tutulur
- Sayfalama: IdeaSoft üst sınırı olan 100 kayıt/sayfa korunur; senkron başına maksimum sayfa sayısı config ile sınırlandırılır
- Webhook: `X-Ideashop-Hmac-Sha256` değeri ham istek gövdesinin Client Secret ile HMAC-SHA256 özeti alınarak Base64 formatında doğrulanır
- Yazma: fiyat ve stok aynı resmî ürün güncelleme yüzeyine, `PUT /admin-api/products/{id}` çağrısına indirgenir

Resmî kaynaklar: [IdeaSoft Admin API](https://apidoc.ideasoft.dev/), [kimlik doğrulama](https://apidoc.ideasoft.dev/docs/admin-api/3x74avtrv8u23-authentication), [webhook](https://apidoc.ideasoft.dev/docs/webhooks/5cc9374300b99-webhooks).

## Çekilen veri kapsamı

### Sipariş

- Sipariş kimliği/numarası, durum, tarih ve güncelleme zamanı
- Toplam, ara toplam, indirim, vergi, kargo tutarı ve para birimi
- Müşteri, fatura ve teslimat adresleri
- Kargo firması, takip kodu ve teslimat bağlamı
- Ürün satırları, ürün/varyant kimliği, SKU, barkod, miktar, fiyat, vergi ve indirim

### Ürün

- Ürün ve varyant kimlikleri, ad, SKU, barkod, durum ve açıklama
- Satış/indirim fiyatı, para birimi ve stok miktarı
- Marka, kategori, görsel ve sağlayıcıya özgü metadata

### Finans ve iade

- Ödeme kimliği, sipariş ilişkisi, tutar, para birimi, yöntem, durum ve işlem zamanı
- İade talebi kimliği, sipariş ilişkisi, durum, neden, müşteri notu ve satır detayları

## Değiştirilen bileşenler

- `app/Services/Marketplace/Connectors/IdeaSoftConnector.php`
- `app/Services/Marketplace/IdeaSoftOAuthService.php`
- `app/Http/Controllers/Marketplace/IdeaSoftOAuthController.php`
- `app/Services/Marketplace/MarketplaceConnectorManager.php`
- `app/Services/Marketplace/MarketplaceProviderRegistry.php`
- `app/Services/Marketplace/MarketplaceConnectionReadinessService.php`
- `app/Models/IntegrationSyncProfile.php`
- `app/Livewire/MarketplaceIntegrations.php`
- `resources/views/livewire/marketplace-integrations.blade.php`
- `config/marketplace.php`
- `routes/web.php`
- `tests/Feature/IdeaSoftConnectorTest.php`

## Veri modeli ve migration

Migration yoktur. Mevcut `MarketplaceStore`, `IntegrationConnection`, `ChannelOrder`, `ChannelProduct`, `OrderFinancialEvent` ve `ChannelClaim` modelleri kullanılır. OAuth token'ları mevcut şifreli credential JSON alanında; geniş sağlayıcı içeriği mevcut `raw_payload` alanlarında tutulur.

## Yetki ve feature flag

- Sipariş: `order_read`
- Katalog okuma: `product_read`
- Fiyat/stok yazma: `product_update`
- Finans: `payment_read`
- İade: `order_refund_request_read`
- `finance_enabled=false`, `price_push_enabled=false`, `stock_push_enabled=false` varsayılandır
- `webhook_enabled=true` varsayılandır; webhook aboneliği IdeaSoft paneli/API'sinde gerçek mağaza kabulü sırasında oluşturulmalıdır
- Soru-cevap için doğrulanmış ortak connector sözleşmesi olmadığı için `questions=false` ilan edilir

## Test kapsamı

- Connector manager ve capability parity
- Bearer token ile bağlantı doğrulama
- Sipariş, ürün, ödeme ve iade normalizasyonu
- Süresi dolan token'ın yenilenmesi ve refresh token rotasyonunun kalıcılaştırılması
- Fiyat/stok ürün güncelleme payload'ları
- Resmî Base64 HMAC-SHA256 webhook doğrulaması
- OAuth redirect, state ve callback code exchange
- Readiness, tenant yetkilendirmesi ve entegrasyon formu rehberi
- Ortak sipariş, katalog, claim, webhook ve dispatch servislerinde regresyon
- 1280 px masaüstü ve 390 px mobil görünümde yatay taşma kontrolü

## Bilinen sınırlamalar

- Gerçek IdeaSoft mağazası veya sandbox credential bulunmadığı için canlı OAuth izin ekranı, scope ve rate-limit smoke testi yapılmadı.
- Ödeme akışı ödeme kayıtlarını taşır; IdeaSoft panelindeki muhasebe/hakediş raporuyla canlı mutabakat yapılmadan tam finans otomasyonu açılmamalıdır.
- `PUT /admin-api/products/{id}` için kısmi fiyat/stok payload'ının gerçek mağaza sürümündeki davranışı canary ürünle doğrulanmalıdır.
- Webhook doğrulaması hazırdır; webhook aboneliğini otomatik oluşturan yönetim akışı bu pilot dilimde yoktur.
- Admin API'nin tema, banner, üye ve içerik gibi mağaza yönetimi yüzeyleri operasyonel ZOLM kapsamına alınmamıştır.

## Kabul adımları

1. IdeaSoft panelinde API kaydı oluşturup ZOLM ekranındaki Redirect URI'yi aynen kaydedin.
2. `order_read`, `product_read`, `payment_read` ve `order_refund_request_read` kapsamlarını açın; yazma pilotu yapılacaksa `product_update` ekleyin.
3. Mağaza URL, Client ID ve Client Secret değerlerini ZOLM'e kaydedin ve “IdeaSoft'ta Yetkilendir” adımını tamamlayın.
4. Son 24 saat sipariş, ürün, ödeme ve iade senkronlarını ayrı ayrı çalıştırıp IdeaSoft paneliyle karşılaştırın.
5. `order/create`, `order/update`, `product/update`, `payment/create` ve iade olaylarını webhook aboneliğine ekleyip HMAC doğrulamasını kontrol edin.
6. Salt-okuma kabulünden sonra tek test ürünüyle fiyat ve stok canary testi yapın; başarıdan sonra ilgili feature flag'leri kademeli açın.

## Geri alma planı

IdeaSoft provider registry kaydı, connector manager eşlemesi ve OAuth route'ları birlikte kaldırılır; IdeaSoft sync profile'ları pasife alınır. Migration olmadığı için veri şeması rollback gerektirmez. Daha önce alınmış `raw_payload` kayıtları denetim izi olarak kalabilir; token'lar ilgili bağlantı silinerek veya credential alanları temizlenerek iptal edilir.

## Commit planı

1. `feat: add IdeaSoft OAuth admin api pilot connector`
2. `test: cover IdeaSoft sync token rotation and webhooks`
3. `docs: document IdeaSoft pilot acceptance workflow`

Commit oluşturulmadı.

## Notion taslağı

**Başlık:** IdeaSoft Admin API Pilot Entegrasyonu  
**Özet:** ZOLM'e mağaza bazlı OAuth, sipariş/ürün/ödeme/iade okuma, webhook HMAC doğrulama ve kontrollü fiyat/stok güncelleme desteği eklendi.  
**İş ihtiyacı ve etki:** IdeaSoft müşterisi bilgilerini kaydedip mağaza yöneticisi onayıyla bağlantıyı kullanabilir; token yenileme ZOLM tarafından otomatik yapılır.  
**Teknik yaklaşım:** Authorization code + refresh token rotasyonu, ortak connector sözleşmeleri ve kayıpsız `raw_payload`.  
**Veri modeli:** Migration yok; mevcut store/connection/channel modelleri kullanıldı.  
**Kullanım:** API kaydı oluştur, Redirect URI ve scope'ları tanımla, bilgileri kaydet, yetkilendir, dar aralıklı senkronları karşılaştır.  
**Yetki/flag:** Finans ve fiyat/stok yazmaları varsayılan kapalı; soru akışı desteklenmiyor.  
**Test:** 79 test / 290 assertion ve responsive UI kontrolü geçti.  
**Sınırlama:** Gerçek mağaza OAuth, rate-limit, webhook aboneliği ve canary yazma kabulü bekliyor.  
**Rollback:** Provider/manager/OAuth route eşlemesini kaldır, sync profile'ını kapat; migration yok.  
**PR/commit:** Henüz oluşturulmadı.  
**Yayın tarihi / sorumlu:** Belirlenecek.

## Slack taslağı

🚀 IdeaSoft Admin API pilot entegrasyonu hazır

- Ne değişti: Mağaza bazlı OAuth; sipariş, ürün, ödeme ve iade okuma; token rotasyonu; webhook HMAC ve kontrollü fiyat/stok yazma eklendi.
- Kullanıcıya etkisi: IdeaSoft mağazası ZOLM entegrasyon ekranından “Pilot” olarak bağlanabilir; erişim token'ı otomatik yenilenir.
- Test durumu: 79 test / 290 assertion ile ortak senkron regresyonları ve masaüstü/390 px UI kontrolleri geçti.
- Yayın / feature flag durumu: Finans ve fiyat/stok yazmaları varsayılan kapalı.
- Dikkat edilmesi gerekenler: Gerçek mağaza scope, webhook aboneliği, rate-limit ve tek ürün canary yazma kabulü yapılmalı.
- Dokümantasyon: `docs/integrations/marketplace/IDEASOFT_PILOT_IMPLEMENTATION.md`
- PR / commit: Henüz oluşturulmadı.
