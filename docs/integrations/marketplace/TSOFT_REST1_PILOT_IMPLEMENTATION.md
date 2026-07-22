# T-Soft REST1 Web Servis Pilot Entegrasyonu

**Tarih:** 2026-07-22  
**Durum:** Kod, sözleşme testleri ve responsive UI kontrolü tamamlandı; gerçek müşteri mağazası kabul testi bekliyor

## Özet

ZOLM'e T-Soft mağazalarının kendi alan adı altındaki REST1 Web Servis API'sine bağlanan pilot connector eklendi. Connector süreli token alır; sipariş, ürün ve alt ürün/varyant verilerini çeker; sipariş ödeme özetlerini ortak finans akışına, iptal/iade durumlarını ortak claim akışına normalize eder. Ana ürün fiyat ve stok güncellemesi desteklenir ancak finans ve tüm yazma otomasyonları canlı kabul tamamlanana kadar varsayılan kapalıdır. Sağlayıcının geniş cevabı `raw_payload` içinde kayıpsız korunur.

## İş ihtiyacı ve kullanıcı etkisi

T-Soft kullanan müşteri, mağaza URL'si ile T-Soft panelinde oluşturulan sınırlı Web Servis kullanıcı adı/parolasını ZOLM'e girerek kuruluma başlayabilir. Yalnız bu bilgileri girmek her zaman yeterli değildir: müşteri paketinde REST1 / Gelişmiş Web Servis lisansı etkin olmalı, kullanıcıya gereken metot izinleri verilmeli ve IP kısıtı kullanılıyorsa ZOLM sunucusunun sabit çıkış IP'si tanımlanmalıdır.

ZOLM bağlantıyı `/rest1/auth/login/{user}` üzerinden alınan süreli token ile kurar ve `product/get` okuma yetkisiyle doğrular. Gerçek müşteri mağazasıyla kabul yapılana kadar kanal “Pilot” olarak gösterilir.

## Teknik yaklaşım

- Kimlik doğrulama: `POST /rest1/auth/login/{user}`, form alanı `pass`
- Oturum: API cevabındaki `token` ve `expirationTime`; token mağaza/kullanıcı bazında önbelleğe alınır
- Sipariş: `POST /rest1/order/get`
- Ana ürün: `POST /rest1/product/get`
- Alt ürün/varyant: `POST /rest1/subProduct/getSubProducts`
- Fiyat/stok yazma: `POST /rest1/product/updateProducts`, JSON `data` form alanı
- Taşıma: Laravel HTTP client, form POST, TLS zorunlu mağaza URL'si, local/private hedef koruması
- Sayfalama: En fazla 500 kayıt/sayfa ve yapılandırılabilir maksimum sayfa sayısı
- Finans: Sipariş ödeme alanlarından türetilen ödeme özeti; bağımsız hakediş/settlement değildir
- İade: Resmî 9 (iptal) ve 10 (iade) sipariş durumlarından salt-okuma claim türetme

Resmî kaynaklar: [T-Soft Web Servis API kullanım rehberi](https://help.tsoft.com.tr/destek/t-soft-web-servis-api-kullanimi-ve-entegrasyon-rehberi), [Web Servis kullanıcı yönetimi ve yetkilendirme](https://help.tsoft.com.tr/destek/web-servis-kullanici-yonetimi-ve-api-yetkilendirme), [REST1 teknik dokümanı](https://www.tsoft.com.tr/img/T-Soft-Web-Servis-Dokumani-V00182.pdf).

## Çekilen veri kapsamı

### Sipariş

- Sipariş kimliği/kodu, tarih, durum kodu/açıklaması ve kaynak mağaza
- Müşteri kimliği, ad/soyad, e-posta ve telefon
- Fatura ve teslimat adresleri
- Para birimi, ara toplam, indirim, vergi, kargo, genel toplam ve ödeme tipi
- Kargo firması, takip numarası, paket/sevkiyat ayrıntıları
- Ürün satırı, ürün/alt ürün kimliği, SKU, barkod, adet, fiyat, vergi ve indirim
- Kampanya, paket içeriği, silinen kayıt ve kargo servis ayrıntıları dahil kaynak cevap `raw_payload`

### Ürün ve varyant

- Ana ürün kimliği/kodu, ad, açıklama, marka, kategori, aktiflik ve görseller
- Satış/liste fiyatı, stok, vergi ve para birimi
- Alt ürün kimliği, SKU, barkod, fiyat, stok ve varyant özellikleri
- Ana ürün ile alt ürünün kaynak içerikleri `raw_payload`

### Finans ve iade

- Sipariş ilişkisi, ödeme tipi, tahsil edilen toplam ve sipariş tarihi
- İptal/iade durumundaki siparişlerden claim türü, durum, neden/not ve ürün satırları
- İade onay/red aksiyonu yoktur; bu pilotta claim akışı salt-okumadır

## Değiştirilen bileşenler

- `app/Services/Marketplace/TSoftRestGateway.php`
- `app/Services/Marketplace/Connectors/TSoftConnector.php`
- `app/Services/Marketplace/MarketplaceConnectorManager.php`
- `app/Services/Marketplace/MarketplaceProviderRegistry.php`
- `app/Services/Marketplace/MarketplaceConnectionReadinessService.php`
- `app/Models/IntegrationSyncProfile.php`
- `app/Livewire/MarketplaceIntegrations.php`
- `resources/views/livewire/marketplace-integrations.blade.php`
- `config/marketplace.php`
- `tests/Feature/TSoftConnectorTest.php`
- `tests/Feature/MarketplaceConnectionReadinessServiceTest.php`
- `tests/Feature/IntegrationSyncProfileDefaultsTest.php`
- `tests/Feature/Livewire/Marketplace/MarketplaceIntegrationsAuthorizationTest.php`

## Veri modeli ve migration

Migration yoktur. Mevcut `MarketplaceStore`, `IntegrationConnection`, `ChannelOrder`, `ChannelProduct`, `ChannelListing`, `OrderFinancialEvent` ve `ChannelClaim` modelleri kullanılır. Web Servis kullanıcı adı/parolası mevcut şifreli credential JSON alanında; sağlayıcıya özgü ayrıntılar mevcut `raw_payload` alanlarında tutulur.

## Yetki ve feature flag

- `orders_enabled=true` ve `products_enabled=true` güvenli varsayılandır.
- `finance_enabled=false`, `price_push_enabled=false` ve `stock_push_enabled=false` varsayılandır.
- Claim okuma vardır; claim onay/red yoktur.
- Resmî, doğrulanmış ortak webhook ve müşteri soru-cevap sözleşmesi bulunmadığı için `webhooks=false` ve `questions=false` ilan edilir.
- Mağaza URL'si HTTPS olmalı; Web Servis kullanıcı adı ve parolası zorunludur.
- Alt ürün/varyant yazması doğrulanmış resmî payload sözleşmesi bulunana kadar kapalı ve fail-closed'dur.

## Test kapsamı

- Connector manager ve capability parity
- Login formu, süreli token önbelleği ve token'ın sonraki form çağrısına eklenmesi
- Gerçek REST1 yol ve parametreleri: `order/get`, `product/get`, `subProduct/getSubProducts`, `product/updateProducts`
- Sipariş, ana ürün, varyant, ödeme özeti ve durum-türevli claim normalizasyonu
- Ana ürün fiyat/stok yazma payload'ı ve varyant yazmada fail-closed davranışı
- Readiness, tenant yetkilendirmesi, sync profile ve entegrasyon formu rehberi
- Ortak sipariş, katalog, claim ve dispatch servislerinde regresyon
- 1280 px masaüstü ve 390 px mobil görünümde yatay taşma kontrolü

## Bilinen sınırlamalar

- T-Soft'un resmî demo Web Servis ortamında login, sipariş, ürün ve alt ürün yolları salt-okuma doğrulandı; gerçek müşteri paket/lisansı, kullanıcı metot izinleri, IP kısıtı ve mağaza kotası henüz doğrulanmadı.
- Finans akışı sipariş ödeme özetidir; bağımsız hakediş, komisyon veya pazaryeri settlement akışı değildir.
- İade kayıtları ayrı bir iade yaşam döngüsü yerine sipariş durumlarından türetilir; kısmi iade miktarı gerçek mağaza örnekleriyle doğrulanmalıdır.
- Alt ürün/varyant fiyat ve stok yazması resmî güncel payload sözleşmesi doğrulanana kadar reddedilir.
- Ana ürün yazması bile tek ürün canary kabulü yapılmadan açılmamalıdır.
- Webhook ve müşteri soru-cevap kabiliyetleri belgelenmiş, doğrulanmış sözleşme bulunana kadar kapalıdır.

## Kabul adımları

1. T-Soft destek/hesap yöneticisinden REST1 / Gelişmiş Web Servis lisansının müşteri paketinde etkin olduğunu doğrulayın.
2. T-Soft panelinde ZOLM için ayrı Web Servis kullanıcısı oluşturun; en az `auth`, `order/get`, `product/get` ve `subProduct/getSubProducts` okuma izinlerini verin.
3. IP kısıtı varsa ZOLM production sunucusunun sabit çıkış IP'sini tanımlayın.
4. HTTPS mağaza URL'si, kullanıcı adı ve parolayı ZOLM'e kaydedip “Bağlantıyı Doğrula” çalıştırın.
5. Son 24 saat siparişlerini ve dar bir ürün/varyant sayfasını çekip T-Soft panel adet/tutarlarıyla karşılaştırın.
6. İptal/iade durumlarını ve ödeme özetlerini örnek siparişlerle doğrulayın.
7. Salt-okuma kabulünden sonra yalnız ana üründe tek SKU fiyat/stok canary testi yapın; varyant yazmasını açmayın.
8. Mutabakat ve veri otoritesi onayından sonra finans/fiyat/stok flag'lerini mağaza bazında kademeli açın.

## Geri alma planı

T-Soft provider registry kaydı, connector manager eşlemesi ve sync profile'ı birlikte kaldırılır veya pasife alınır. Migration olmadığı için veri şeması rollback gerektirmez. Daha önce çekilmiş `raw_payload` kayıtları denetim izi olarak kalabilir; yazma flag'leri kapatılarak dış sistem değişikliği anında durdurulur.

## Commit planı

1. `feat: add T-Soft REST1 marketplace pilot connector`
2. `test: cover T-Soft token data sync and safe writes`
3. `docs: document T-Soft REST1 pilot acceptance workflow`

Commit oluşturulmadı.

## Notion taslağı

**Başlık:** T-Soft REST1 Web Servis Pilot Entegrasyonu  
**Özet:** ZOLM'e T-Soft REST1 üzerinden sipariş, ana ürün/varyant, ödeme özeti ve durum-türevli iade okuma; kontrollü ana ürün fiyat/stok yazma desteği eklendi.  
**İş ihtiyacı ve etki:** T-Soft müşterisi mağaza URL'si ve Web Servis kullanıcısıyla kuruluma başlayabilir; geniş veri ortak modellere ve kayıpsız `raw_payload` alanlarına alınır.  
**Teknik yaklaşım:** Mağazaya özel REST1 URL'leri, form login/token, kontrollü sayfalama ve mevcut connector arayüzleri.  
**Veri modeli:** Migration yok; mevcut store/connection/channel modelleri kullanıldı.  
**Kullanım:** Lisans ve metot izinlerini açtır, IP'yi tanımla, URL/kullanıcı/parola gir, bağlantıyı doğrula, dar aralıklı senkronları panelle karşılaştır.  
**Yetki/flag:** Finans ve fiyat/stok yazmaları varsayılan kapalı; varyant yazma fail-closed; webhook/soru ve claim aksiyonları desteklenmiyor.  
**Test:** Connector, readiness, capability, ortak senkron ve 1280/390 px responsive kontroller geçti.  
**Sınırlama:** Gerçek müşteri lisansı, kota, IP/metot izinleri, finans mutabakatı ve ana ürün canary kabulü bekliyor.  
**Rollback:** Provider/manager eşlemesini kaldır veya profili kapat; migration yok.  
**PR/commit:** Henüz oluşturulmadı.  
**Yayın tarihi / sorumlu:** Belirlenecek.

## Slack taslağı

🚀 T-Soft REST1 pilot entegrasyonu hazır

- Ne değişti: Sipariş, ana ürün/varyant, ödeme özeti ve durum-türevli iade okuma; token yönetimi; kontrollü ana ürün fiyat/stok yazma eklendi.
- Kullanıcıya etkisi: T-Soft mağazası URL ve Web Servis kullanıcısıyla ZOLM entegrasyon ekranından pilot olarak kurulabilir.
- Test durumu: Connector ve ortak senkron regresyonları ile 1280/390 px UI kontrolleri geçti.
- Yayın / feature flag durumu: Finans ve tüm fiyat/stok yazmaları varsayılan kapalı; varyant yazma fail-closed.
- Dikkat edilmesi gerekenler: REST1 lisansı, metot izinleri, sabit çıkış IP'si, panel mutabakatı ve tek ana ürün canary kabulü gerekir.
- Dokümantasyon: `docs/integrations/marketplace/TSOFT_REST1_PILOT_IMPLEMENTATION.md`
- PR / commit: Henüz oluşturulmadı.
