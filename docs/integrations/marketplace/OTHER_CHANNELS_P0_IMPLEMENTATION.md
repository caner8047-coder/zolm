# Diğer Pazaryerleri ve E-Ticaret Altyapıları — P0 Durum ve Uygulama Notu

**Tarih:** 2026-07-22  
**Durum:** ikas, IdeaSoft, Ticimax, T-Soft ve Adobe Commerce / Magento connector'ları hazır kullanıma alındı; sağlayıcıya özel lisans/scope gereksinimleri devam ediyor

> **Hazır kullanım güncellemesi (2026-07-22):** Shopify, ikas, IdeaSoft, Ticimax, T-Soft ve Magento registry durumları `ready` yapıldı. Sipariş, ürün, desteklenen finans ve iade okumaları yeni bağlantılarda varsayılan açıktır; fiyat/stok yazmaları kullanıcı açana kadar kapalıdır. Ayrıntı: [`READY_COMMERCE_CONNECTORS_RELEASE.md`](./READY_COMMERCE_CONNECTORS_RELEASE.md), karar: [`ADR-007-READY-PROVIDERS-WITH-SAFE-WRITES.md`](./ADR-007-READY-PROVIDERS-WITH-SAFE-WRITES.md).

## Amaç ve kullanıcı sonucu

Trendyol, Hepsiburada ve WooCommerce dışında kalan kanalların ZOLM kayıt defterindeki ilan edilen kabiliyetlerini gerçek connector davranışıyla karşılaştırmak; erişilebilen sipariş, katalog, finans, soru, iade ve stok/fiyat verisini kayıpsız biçimde ortak modellere ve `raw_payload` alanlarına taşımaktır.

Bu dilimde yanlış “hazır” algısı düzeltilmiş, var olan fakat senkron sözleşmesine bağlı olmayan N11 finans okuyucusu bağlanmış, Shopify GraphQL okuma kapsamı genişletilmiş ve Shopify iadeleri ortak claim akışına eklenmiştir.

## Güncel ZOLM kapsam matrisi

| Kanal | Sipariş | Ürün | Finans | Soru | İade | Fiyat/Stok | Durum / sınır |
|---|---:|---:|---:|---:|---:|---:|---|
| N11 | API | API | SOAP okuyucu, varsayılan kapalı | API | API + karar aksiyonları | Yazma mevcut, varsayılan kapalı | Canlı finans kabul testi gerekli |
| Koçtaş / Mirakl | API | API | Excel | API | API + karar aksiyonları | Yazma mevcut, varsayılan kapalı | Mirakl sözleşmesi ve mağaza anahtarı gerekir |
| Pazarama | API | API | Excel | API | Sipariş payload'ından türetiliyor | Yok | Bağımsız iade ve stok/fiyat uç noktaları sonraki dilim |
| Çiçeksepeti | API | API | Excel | API | Sipariş payload'ından türetiliyor | Yok | API key ve Satıcı ID gerekir |
| Amazon | Yok | Yok | Yok | Yok | Yok | Yok | SP-API onboarding tamamlanmadan “hazır” değildir |
| Shopify | GraphQL | GraphQL | Sipariş transaction'ları | Yok | GraphQL Return | Yazma mevcut, varsayılan kapalı | `read_returns`; eski sipariş için `read_all_orders` gerekir |
| ikas | GraphQL | GraphQL | Sipariş transaction'ları | Yok | Sipariş iade durumundan türetiliyor | Yazma mevcut, kullanıcı açar | Özel uygulama Client ID/Secret ve scope gerekir |
| IdeaSoft | REST | REST | Ödeme kayıtları | Yok | İade talebi API | Ürün güncelleme ile mevcut, kullanıcı açar | OAuth mağaza onayı gerekir |
| Ticimax | SOAP | SOAP | Sipariş ödemeleri | Yok | Sipariş durumundan türetiliyor | SOAP yazma mevcut, kullanıcı açar | Web servis paketi, HTTPS mağaza URL'si ve Üye Kodu gerekir |
| T-Soft | REST1 | REST1 | Sipariş ödeme özeti | Yok | Sipariş durumundan türetiliyor | Ana ürün yazma mevcut; varyant yazma kapalı | REST1 lisansı, metot izinleri, HTTPS mağaza URL'si ve servis kullanıcısı gerekir |
| Adobe Commerce / Magento | REST | REST | Fatura özeti | Yok | Credit memo | Base price + MSI source item yazma mevcut, kullanıcı açar | Magento Open Source / PaaS-on-prem; SaaS için ayrı IMS adapter gerekir |

`Excel` işareti ilgili kanalın ZOLM API connector'ının finans verisi çektiği anlamına gelmez; finans import akışının dosya üzerinden yürüdüğünü ifade eder.

## Uygulanan değişiklikler

### N11

- Mevcut `pullFinancialEvents()` uygulaması `PullsFinancials` sözleşmesine bağlandı.
- Registry ve gerçek connector finans kabiliyeti eşitlendi.
- Settlement SOAP URL, sayfa boyutu ve maksimum sayfa limiti merkezi config'e alındı.
- Otomatik finans senkronu güvenli varsayılan olarak kapalı tutuldu.
- SOAP cevabındaki baştaki boşluk ve `dd/mm/YYYY` tarih formatı güvenli parse edilir hale getirildi.
- Mock SOAP sözleşme testi eklendi.

N11'in resmi sipariş, ürün, soru ve iade servisleri güncel yardım merkezinde yayınlanmaktadır: [sipariş paketleri](https://magazadestek.n11.com/satis-surecleri/restapi-siparis-listeleme-10413), [satıcı ürün sorgulama](https://magazadestek.n11.com/satis-surecleri/restapi-satici-urun-sorgulama-10493), [ürün soruları](https://magazadestek.n11.com/satis-surecleri/urun-sorularini-listeleme-getproductquestionlist-8393), [iade servisi](https://magazadestek.n11.com/satis-surecleri/soapapi-iade-talepleri-servisi-returnservice-10453). Settlement servisi gerçek mağaza bilgileriyle doğrulanmadan scheduler'da açılmamalıdır.

### Shopify

- Varsayılan Admin GraphQL sürümü resmî güncel kararlı sürüm olan `2026-07` yapıldı.
- Sipariş GraphQL seçim setine para birimleri, orijinal/güncel tutarlar, indirim, vergi, kargo, iade toplamı, ödeme kanalları, not, etiket ve ağırlık bağlamı eklendi.
- Ürün seçim setine açıklama, handle, etiket, SEO, seçenekler, toplam stok, stok takip durumu, hediye kartı durumu ve mağaza URL'si eklendi.
- `Order.returns` ve return line item verileri ortak `ChannelClaim` formatına normalize edildi.
- İade satırında SKU, barkod, adet, neden, müşteri notu ve kaynak payload korunuyor.
- Registry'de Shopify `claims` kabiliyeti gerçek connector ile eşitlendi.
- Kurulum rehberine `read_returns` ve 60 günden eski siparişler için `read_all_orders` yetki şartı eklendi.

Shopify resmî dokümanı `2026-07` sürümünü latest olarak gösterir. `Return` ve `ReturnLineItem` okumaları `read_returns` scope'u ister; sipariş sorguları varsayılan olarak son 60 günle sınırlıdır: [Return](https://shopify.dev/docs/api/admin-graphql/latest/objects/return), [ReturnLineItem](https://shopify.dev/docs/api/admin-graphql/latest/objects/returnlineitem), [Order](https://shopify.dev/docs/api/admin-graphql/latest/objects/order), [Product](https://shopify.dev/docs/api/admin-graphql/latest/objects/product).

### Amazon ve capability doğruluğu

- Amazon registry durumu `ready` yerine `access_required` oldu.
- Gerçek connector henüz skeleton olduğu için sipariş ve finans dahil tüm canlı kabiliyet ilanları kapatıldı.
- Mağaza seçiminde “Erişim gerekli” etiketi ve SP-API gereksinimi gösteriliyor.
- Registry boolean kabiliyetleri ile gerçek connector kabiliyetlerini karşılaştıran regresyon testi eklendi.

Amazon SP-API; LWA yetkilendirmesi, AWS rolü, bölge endpoint'i, uygulama kaydı ve yetki kapsamı gerektirir. Bunlar tamamlanmadan yalnız access key/secret alanı eklemek çalışan entegrasyon oluşturmaz: [Amazon SP-API onboarding](https://developer-docs.amazon.com/sp-api/docs/onboarding-overview), [Feeds API](https://developer-docs.amazon.com/sp-api/docs/feeds-api), [ürün listeleme rehberi](https://developer-docs.amazon.com/sp-api/lang-en_EN/docs/manage-product-listings-guide).

### ikas

- Özel uygulama `client_credentials` akışı, dört saatlik bearer token ve token önbelleği eklendi.
- Siparişlerde müşteri, adres, ödeme, vergi, kargo, fatura, kampanya düzeltmesi, paket, takip ve satır verileri çekiliyor.
- Ürünlerde varyant, fiyat listesi, stok lokasyonu, görsel, özellik, kategori, marka, çeviri, SEO metadata ve satış kanalı durumları korunuyor.
- Sipariş transaction'ları mevcut finans event modeline normalize ediliyor; iade durumları claim akışına türetiliyor.
- ikas webhook zarfının `data` alanı Client Secret tabanlı HMAC-SHA256 ile doğrulanıyor.
- Fiyat ve stok yazmaları resmî güncel mutation'ları kullanıyor; geçiş dönemindeki eski API adları kontrollü fallback olarak destekleniyor.
- Entegrasyon ekranında ikas hazır kanal olarak ve özel uygulama kurulum rehberiyle gösteriliyor.

Resmî kaynaklar: [özel uygulama kurulumu](https://builders.ikas.com/docs/app-development/private-app), [kimlik doğrulama](https://builders.ikas.com/docs/app-development/private-app/authentication), [Admin API](https://builders.ikas.com/docs/admin-api), [listOrder](https://builders.ikas.com/docs/admin-api/admin-apis/order/list-order), [stok güncelleme](https://builders.ikas.com/docs/admin-api/admin-apis/product/update-product-stock-count), [fiyat güncelleme](https://builders.ikas.com/docs/admin-api/admin-apis/product/update-variant-prices), [webhook](https://builders.ikas.com/docs/admin-api/admin-apis/webhook/save-webhook).

Detaylı uygulama ve kabul notu: [`IKAS_PILOT_IMPLEMENTATION.md`](./IKAS_PILOT_IMPLEMENTATION.md).

### IdeaSoft

- Mağaza URL'si üzerinden OAuth 2.0 `authorization_code` redirect/callback akışı eklendi.
- Access token süresi ve rotasyonlu refresh token şifreli mağaza bağlantısında tutuluyor; süresi dolan token otomatik yenileniyor.
- Sipariş, ürün, ödeme ve iade talebi kayıtları ortak ZOLM modellerine normalize ediliyor; geniş sağlayıcı içeriği `raw_payload` içinde korunuyor.
- `X-Ideashop-Hmac-Sha256` webhook imzası resmî Base64 HMAC-SHA256 sözleşmesiyle doğrulanıyor.
- Fiyat/stok yazması resmî ürün güncelleme endpointine bağlı; finans ve tüm yazmalar canlı kabul testine kadar varsayılan kapalı.
- Entegrasyon ekranında IdeaSoft hazır kanal olarak, Redirect URI ve mağaza yöneticisi yetkilendirme adımıyla gösteriliyor.

Resmî kaynaklar: [IdeaSoft Admin API](https://apidoc.ideasoft.dev/), [kimlik doğrulama](https://apidoc.ideasoft.dev/docs/admin-api/3x74avtrv8u23-authentication), [webhook](https://apidoc.ideasoft.dev/docs/webhooks/5cc9374300b99-webhooks).

Detaylı uygulama ve kabul notu: [`IDEASOFT_PILOT_IMPLEMENTATION.md`](./IDEASOFT_PILOT_IMPLEMENTATION.md).

### Ticimax

- Mağazaya özel Ürün ve Sipariş WSDL adreslerini kullanan native SOAP gateway eklendi.
- `UyeKodu` ile bağlantı doğrulama; sayfalı sipariş ve ürün/varyant çekme hazırlandı.
- Siparişe gömülü ödeme kayıtları ve `SelectSiparisOdeme` fallback'i ortak finans olayına normalize ediliyor.
- Resmî sipariş durum kodlarındaki iptal/iade kayıtları salt-okuma claim akışına türetiliyor.
- `VaryasyonGuncelle` ve `StokAdediGuncelle` ile fiyat/stok yazma mevcut; canlı canary kabuline kadar varsayılan kapalı.
- Gerçek WSDL'deki `f`, `s`, `siparisId`, `odemeId`, `urun`, `ayar` ve `urunler` parametreleri sözleşme testleriyle sabitlendi.
- Entegrasyon ekranında Ticimax hazır kanal olarak; mağaza URL'si ve Üye Kodu gereksinimiyle gösteriliyor.

Resmî kaynaklar: [Ticimax Web Servis API](https://www.ticimax.com/web-servis-api/), [Ürün Servisi](https://static.ticimax.com/dokumanlar/UrunServis.pdf), [Sipariş Servisi](https://static.ticimax.com/dokumanlar/siparisservis.pdf).

Detaylı uygulama ve kabul notu: [`TICIMAX_PILOT_IMPLEMENTATION.md`](./TICIMAX_PILOT_IMPLEMENTATION.md).

### T-Soft

- REST1 Web Servis kullanıcı adı/parolasıyla süreli token alan ve token'ı güvenli TTL ile önbelleğe alan gateway eklendi.
- `order/get`, `product/get` ve `subProduct/getSubProducts` üzerinden sipariş, ana ürün ve varyant okuma hazırlandı.
- Sipariş ödeme alanları ortak finans olayına; 9/10 durum kodları salt-okuma claim akışına normalize ediliyor.
- `product/updateProducts` ile ana ürün fiyat/stok yazma mevcut; varyant yazma doğrulanmış sözleşme bulunana kadar fail-closed.
- Finans ve tüm yazma flag'leri gerçek müşteri kabulüne kadar varsayılan kapalı; webhook ve müşteri soru-cevap kabiliyetleri ilan edilmiyor.
- Entegrasyon ekranında T-Soft hazır kanal olarak; mağaza URL'si, servis kullanıcısı, metot izinleri, lisans ve IP gereksinimleriyle gösteriliyor.

Resmî kaynaklar: [T-Soft Web Servis API kullanım rehberi](https://help.tsoft.com.tr/destek/t-soft-web-servis-api-kullanimi-ve-entegrasyon-rehberi), [Web Servis kullanıcı yönetimi ve yetkilendirme](https://help.tsoft.com.tr/destek/web-servis-kullanici-yonetimi-ve-api-yetkilendirme), [REST1 teknik dokümanı](https://www.tsoft.com.tr/img/T-Soft-Web-Servis-Dokumani-V00182.pdf).

Detaylı uygulama ve kabul notu: [`TSOFT_REST1_PILOT_IMPLEMENTATION.md`](./TSOFT_REST1_PILOT_IMPLEMENTATION.md).

### Adobe Commerce / Magento

- Magento Open Source ve Adobe Commerce PaaS/on-prem için Integration Access Token kullanan REST gateway eklendi.
- Store view bazlı `/rest/<code>/V1` adresleme; sayfalı sipariş, ürün, invoice ve credit memo okuma hazırlandı.
- Configurable ürün satırları parent parasal değerleri ve child SKU ile tekilleştiriliyor; geniş sağlayıcı cevabı `raw_payload` içinde korunuyor.
- Invoice kayıtları settlement olarak değil fatura özeti, credit memo kayıtları da tam RMA değil salt-okuma claim olarak normalize ediliyor.
- `products/base-prices` ile fiyat ve `inventory/source-items` ile MSI kaynak bazlı stok yazma mevcut; canlı canary kabuline kadar varsayılan kapalı.
- Adobe Commerce as a Cloud Service farklı IMS sözleşmesi nedeniyle aynı formda desteklenmiyor; readiness ve gateway SaaS URL'sini açık hata ile reddediyor.
- Entegrasyon ekranında mağaza URL'si, Integration Access Token, store view ve source code gereksinimleriyle hazır kanal olarak gösteriliyor.

Resmî kaynaklar: [REST API overview](https://developer.adobe.com/commerce/webapi/rest/), [authentication](https://developer.adobe.com/commerce/webapi/get-started/authentication/), [performing searches](https://developer.adobe.com/commerce/webapi/rest/use-rest/performing-searches/), [inventory source items](https://developer.adobe.com/commerce/webapi/rest/inventory/manage-source-items/), [catalog pricing](https://developer.adobe.com/commerce/webapi/rest/modules/catalog/catalog-pricing/), [SaaS server-to-server authentication](https://developer.adobe.com/commerce/webapi/rest/authentication/server-to-server/).

Detaylı uygulama ve kabul notu: [`MAGENTO_REST_PILOT_IMPLEMENTATION.md`](./MAGENTO_REST_PILOT_IMPLEMENTATION.md). Mimari karar: [`ADR-006-MAGENTO-PAAS-REST-AND-SOURCE-AWARE-WRITES.md`](./ADR-006-MAGENTO-PAAS-REST-AND-SOURCE-AWARE-WRITES.md).

## İncelenen diğer altyapılar ve sonraki bağlayıcı sırası

Adobe Commerce / Magento Open Source PaaS-on-prem pilotu tamamlandı. Adobe Commerce as a Cloud Service ise IMS server-to-server kimlik doğrulaması ve tenant tabanlı farklı REST sözleşmesi nedeniyle ayrı bir adapter dilimi olarak ele alınmalıdır. Bu dilim ancak test tenant, IMS client bilgileri ve gerçek scope sözleşmesi sağlandığında açılacaktır.

Bu kanallar için gerçek credential ve sandbox olmadan endpoint uydurulmayacak; her connector ayrı, küçük ve mock + sandbox kabul testli dilim olarak geliştirilecektir.

## Veri modeli ve migration etkisi

- Migration yoktur.
- Shopify geniş alanları mevcut normalize kolonlara uygun olan bölümüyle yazılır; geri kalan veri `raw_payload` içinde kayıpsız korunur.
- Shopify iadeleri mevcut `ChannelClaim` ve claim item modellerini kullanır.
- N11 finans olayları mevcut finans event modeline gider.
- ikas sipariş, katalog, finans ve claim verileri mevcut ortak modellere gider; sağlayıcıya özel geniş alanlar `raw_payload` içinde kalır.
- IdeaSoft OAuth token'ları mevcut şifreli bağlantı credential alanında; sipariş, katalog, ödeme ve iade verileri mevcut ortak modeller ve `raw_payload` alanlarında tutulur.
- Ticimax Üye Kodu mevcut şifreli bağlantı credential alanında; sipariş, varyant, ödeme ve durum-türevli claim verileri mevcut ortak modeller ve `raw_payload` alanlarında tutulur.
- T-Soft Web Servis kullanıcı adı/parolası mevcut şifreli bağlantı credential alanında; sipariş, ana ürün/varyant, ödeme özeti ve durum-türevli claim verileri mevcut ortak modeller ve `raw_payload` alanlarında tutulur.
- Magento Integration Access Token mevcut şifreli bağlantı credential alanında; sipariş, ürün, invoice özeti ve credit memo verileri mevcut ortak modeller ve `raw_payload` alanlarında tutulur.

## Geriye uyumluluk ve güvenlik

- Fiyat/stok yazma flag'leri açılmadı.
- N11 finans scheduler varsayılanı kapalıdır; manuel veya kontrollü pilotla açılmalıdır.
- Amazon için canlı capability açılmadı.
- Shopify API sürümü env ile override edilebilir. Mevcut production ortamında `SHOPIFY_API_VERSION` tanımlıysa otomatik değişmez.
- Yeni migration veya veri silme işlemi yoktur.
- ikas finans, fiyat ve stok yazma özellikleri canlı kabul testine kadar varsayılan kapalıdır.
- IdeaSoft `state` kaydı kullanıcı/mağaza bağlı, tek kullanımlık ve 10 dakika sürelidir; finans ve fiyat/stok yazmaları canlı kabule kadar varsayılan kapalıdır.
- Ticimax yalnız HTTPS mağaza URL'si kabul eder; finans ve fiyat/stok yazmaları canlı SOAP kabulüne kadar varsayılan kapalıdır.
- T-Soft yalnız HTTPS mağaza URL'si kabul eder; local/private hedefleri reddeder. Finans ve fiyat/stok yazmaları canlı REST1 kabulüne kadar varsayılan kapalı; varyant yazma fail-closed'dur.
- Magento yalnız HTTPS PaaS/on-prem mağaza URL'si kabul eder; local/private hedefleri ve SaaS API hostunu reddeder. Finans ve fiyat/stok yazmaları canlı kabule kadar varsayılan kapalıdır.

## Doğrulama

- `ShopifyConnectorTest`: sipariş, ürün, finans, iade, webhook, fiyat ve stok senaryoları.
- `N11ConnectorTest`: sipariş, ürün, bağlantı, fiyat/stok ve settlement SOAP finans senaryosu.
- `MarketplaceProviderCapabilityParityTest`: registry/connector kabiliyet eşitliği ve erişim bekleyen sağlayıcı güvenlik kuralı.
- `IkasConnectorTest`: OAuth, sipariş, ürün, finans, iade, webhook, fiyat ve stok senaryoları.
- İlgili PHP dosyaları Pint ile biçimlendirildi.
- Yerel demo tenant ile masaüstü ve 390 px mobil görünüm kontrol edildi; Amazon seçenek etiketi ve erişim notunda yatay taşma oluşmadı.
- ikas yeni mağaza formu 1280 px ve 390 px görünümde kontrol edildi; kanal etiketi ve merchant rehberinde yatay taşma oluşmadı.
- IdeaSoft connector, readiness, capability parity, OAuth ve ortak senkron regresyon testlerinde 79 test / 290 assertion geçti.
- IdeaSoft yeni mağaza formu 1280 px ve 390 px görünümde kontrol edildi; kanal etiketi ve form alanlarında yatay taşma oluşmadı.
- Ticimax connector, readiness, capability parity ve ortak senkron regresyon testlerinde 75 test / 277 assertion geçti.
- Ticimax yeni mağaza formu 1280 px ve 390 px görünümde kontrol edildi; kanal etiketi, kurulum açıklaması ve kaydetme kontrolünde yatay taşma oluşmadı.
- T-Soft connector, token gateway, readiness, sync defaults, capability parity ve ortak senkron regresyon testleri geçti.
- T-Soft yeni mağaza formu 1280 px ve 390 px görünümde kontrol edildi; kanal etiketi ve kaydetme kontrolünde yatay taşma oluşmadı.
- Magento connector, readiness, capability parity, defaults, Livewire ve ortak senkron/yazma regresyon paketinde 108 test / 441 assertion geçti.
- Hazır kanal seçimleri yeni mağaza formunda kontrol edildi; 390 px görünümde Pilot etiketi kalmadı ve yeni API bilgilerine geçiş butonu yatay taşma oluşturmadı.

Canlı N11/Shopify credential kullanılmadı. Shopify GraphQL seçim setinin gerçek mağaza scope'larıyla ve N11 settlement cevabının gerçek mağaza sözleşmesiyle smoke testi açık konudur.

## Kullanım adımları

### Shopify

1. Shopify özel uygulamasında gereken read scope'ları açın; iadeler için `read_returns`, 60 günden eski siparişler için `read_all_orders` ekleyin.
2. Yeni access token üretin ve ZOLM mağaza bağlantısına kaydedin.
3. Önce bağlantıyı doğrulayın; ardından ürün, sipariş, finans ve iade senkronlarını küçük tarih aralığıyla çalıştırın.
4. Fiyat/stok yazma özelliklerini ancak salt-okuma smoke testi ve stok otoritesi kararı sonrası açın.

### N11

1. API key/secret ile sipariş ve ürün bağlantısını doğrulayın.
2. Finans özelliğini tek mağazada ve dar tarih aralığında manuel çalıştırın.
3. Hakediş toplamını N11 panel raporuyla karşılaştırın.
4. Mutabakat geçmeden `finance_enabled` değerini scheduler için açmayın.

### ikas

1. ikas panelinde Uygulamalar > Uygulamalarım > Özel Uygulama bölümünden Standart Uygulama oluşturun.
2. En az Read Orders, Read Products, Read Customers ve Read Inventories kapsamlarını açın.
3. Client ID ve Client Secret değerlerini ZOLM ikas bağlantısına kaydedip “Bağlantıyı Doğrula” çalıştırın.
4. Önce dar tarih aralığında sipariş ve ürün senkronu çalıştırın; panel toplamlarıyla karşılaştırın.
5. Finans transaction akışını ayrıca doğrulayın. Write Products / Write Inventories ve ZOLM yazma flag'lerini ancak veri otoritesi onayından sonra açın.

### IdeaSoft

1. IdeaSoft panelinde API kaydı oluşturup ZOLM'deki Redirect URI'yi ve gerekli read scope'larını kaydedin.
2. Mağaza URL, Client ID ve Client Secret bilgilerini ZOLM'e kaydedin; “IdeaSoft'ta Yetkilendir” adımını mağaza yöneticisiyle tamamlayın.
3. Sipariş, ürün, ödeme ve iade senkronlarını dar tarih aralıklarıyla çalıştırıp panel toplamlarıyla karşılaştırın.
4. Webhook aboneliklerini oluşturup HMAC doğrulamasını canlı olaylarla kontrol edin.
5. `product_update` ve ZOLM fiyat/stok flag'lerini yalnız tek ürün canary testi ve veri otoritesi onayından sonra açın.

### Ticimax

1. Ticimax destek/hesap yöneticisinden Web Servis API paketini açtırıp Üye Kodu / Web Servis Şifresi bilgisini alın.
2. HTTPS mağaza kök URL'sini ve Üye Kodu'nu ZOLM'e kaydedip bağlantıyı doğrulayın.
3. Son 24 saat sipariş, dar ürün sayfası, ödeme ve iade/iptal durumlarını Ticimax paneliyle karşılaştırın.
4. Salt-okuma kabulünden sonra tek varyantla fiyat ve stok canary testi yapın.
5. Finans ve yazma flag'lerini yalnız mutabakat ve veri otoritesi onayından sonra kademeli açın.

### T-Soft

1. T-Soft destek/hesap yöneticisinden REST1 / Gelişmiş Web Servis lisansını doğrulatın.
2. ZOLM için ayrı Web Servis kullanıcısı oluşturup `auth`, sipariş, ürün ve alt ürün okuma metotlarını açın; gerekiyorsa sabit çıkış IP'sini tanımlayın.
3. HTTPS mağaza URL'si, kullanıcı adı ve parolayı ZOLM'e kaydedip bağlantıyı doğrulayın.
4. Son 24 saat sipariş ve dar ürün/varyant sayfasını T-Soft paneliyle karşılaştırın; ödeme ve iptal/iade kayıtlarını doğrulayın.
5. Salt-okuma kabulünden sonra yalnız ana üründe tek SKU canary testi yapın; varyant yazmayı açmayın.
6. Finans ve ana ürün yazma flag'lerini yalnız mutabakat ve veri otoritesi onayından sonra kademeli açın.

### Adobe Commerce / Magento

1. Magento yönetiminde ZOLM için ayrı Integration oluşturun; gereken Sales/Catalog read ACL izinlerini verip Access Token alın.
2. HTTPS mağaza URL'sini, Integration Access Token'ı, store view kodunu ve gerekiyorsa MSI source code'u ZOLM'e kaydedin.
3. Bağlantıyı doğrulayıp son 24 saat siparişleri ve dar ürün sayfasını Magento paneliyle karşılaştırın.
4. Kullanılacaksa invoice ile credit memo adet/tutarlarını ayrıca doğrulayın; invoice verisini settlement kabul etmeyin.
5. Salt-okuma kabulünden sonra doğru store/source bağlamında tek SKU fiyat ve stok canary testi yapın.
6. Finans ve yazma flag'lerini yalnız mutabakat, veri otoritesi ve geri alma onayından sonra kademeli açın.

## Geri alma planı

- Shopify sürüm ve seçim seti değişikliği geri alınabilir; şema değişikliği yoktur.
- Shopify `PullsClaims` sözleşmesi ve registry capability'si birlikte geri alınmalıdır.
- N11 `PullsFinancials` sözleşmesi ve capability'si birlikte geri alınmalıdır.
- Raw payload olarak yazılmış zengin veri zararsız biçimde kalabilir; veri silme gerekmez.
- ikas provider kaydı ve connector manager eşlemesi birlikte geri alınabilir; migration olmadığı için veri şeması rollback gerektirmez.
- IdeaSoft provider/manager eşlemesi ve OAuth route'ları birlikte geri alınabilir; migration olmadığı için veri şeması rollback gerektirmez.
- Ticimax provider/manager eşlemesi birlikte geri alınabilir veya sync profile pasife alınabilir; migration olmadığı için veri şeması rollback gerektirmez.
- T-Soft provider/manager eşlemesi birlikte geri alınabilir veya sync profile pasife alınabilir; migration olmadığı için veri şeması rollback gerektirmez.
- Magento provider/manager eşlemesi birlikte geri alınabilir veya sync profile pasife alınabilir; migration olmadığı için veri şeması rollback gerektirmez.

## Önerilen commit planı

1. `fix: align marketplace registry with connector capabilities`
2. `feat: wire N11 settlement reader into finance sync`
3. `feat: expand Shopify data and return ingestion`
4. `test: enforce marketplace capability parity`
5. `docs: document other channel integration coverage`
6. `feat: add ikas admin api pilot connector`
7. `test: cover ikas oauth data sync and webhooks`
8. `feat: add IdeaSoft OAuth admin api pilot connector`
9. `test: cover IdeaSoft sync token rotation and webhooks`
10. `docs: document IdeaSoft pilot acceptance workflow`
11. `feat: add Ticimax SOAP marketplace pilot connector`
12. `test: lock Ticimax WSDL contract and sync normalization`
13. `docs: document Ticimax pilot acceptance workflow`
14. `feat: add T-Soft REST1 marketplace pilot connector`
15. `test: cover T-Soft token data sync and safe writes`
16. `docs: document T-Soft REST1 pilot acceptance workflow`
17. `feat: add Magento REST marketplace pilot connector`
18. `test: cover Magento sync normalization and safe writes`
19. `docs: document Magento PaaS pilot acceptance workflow`

Commit oluşturulmadı; kullanıcıya ait mevcut worktree değişiklikleri stage edilmedi.

## Notion taslağı

**Başlık:** Diğer Pazaryerleri ve E-Ticaret Altyapıları P0 Kapsamı  
**Durum:** Draft  
**Sorumlu:** Belirlenecek  
**Yayın tarihi:** Belirlenecek

### Özet

ZOLM'ün N11, Koçtaş, Pazarama, Çiçeksepeti, Amazon ve Shopify kabiliyetleri gerçek connector davranışıyla karşılaştırıldı. Yanlış hazır ilanları düzeltildi; N11 finans sözleşmesi bağlandı; Shopify veri ve iade kapsamı genişletildi; ikas, IdeaSoft, Ticimax, T-Soft ve Adobe Commerce / Magento pilot connector'ları eklendi.

### İş ihtiyacı ve kullanıcı etkisi

Kullanıcı yalnız kartta “hazır” yazdığı için bir entegrasyonun çalıştığını varsaymamalıdır. Yeni yapı gerçek çalışan kabiliyetleri ilan eder, erişim gereksinimini görünür kılar ve API'nin verdiği geniş veriyi raw payload ile kayıpsız korur.

### Teknik yaklaşım

Mevcut connector sözleşmeleri ve ortak kanal modelleri korundu. Şema büyütmeden önce normalize edilebilen alanlar mevcut kolonlara, geri kalan alanlar raw payload'a alındı. Registry/connector drift'i test ile engellendi.

### Yetki ve feature flag

N11 finans scheduler varsayılanı kapalıdır. Shopify iadeleri `read_returns`, eski siparişler `read_all_orders` ister. ikas, IdeaSoft, Ticimax, T-Soft ve Magento finans/yazma flag'leri canlı kabule kadar kapalıdır. T-Soft varyant yazması fail-closed'dur. Magento SaaS farklı IMS adapterı olmadan reddedilir. Amazon canlı özellikleri SP-API onboarding tamamlanana kadar kapalıdır.

### Test kapsamı ve sınırlamalar

Mock connector testleri ve capability parity testi kapsamdadır. Canlı mağaza smoke testleri, N11 panel mutabakatı ve yeni altyapı connector'ları açık konudur.

### İlgili commit / PR

Henüz oluşturulmadı.

## Slack taslağı

🚀 Diğer kanal entegrasyonlarında P0 güvenilirlik ve beş pilot dilimi tamamlandı

- Ne değişti: N11 finans okuyucusu bağlandı; Shopify veri/iade kapsamı genişletildi; Amazon'ın yanlış hazır kabiliyetleri kapatıldı; ikas, IdeaSoft, Ticimax, T-Soft ve Magento PaaS/on-prem pilot connector'ları eklendi.
- Kullanıcıya etkisi: Entegrasyon ekranı yalnız gerçek çalışan kabiliyetleri gösteriyor; beş yeni altyapı kontrollü pilot olarak bağlanabiliyor ve Magento SaaS yanlış yapılandırması engelleniyor.
- Test durumu: N11, Shopify ve registry/connector parity testleri geçti; canlı credential smoke testleri bekliyor.
- Yayın / feature flag durumu: N11 finans ve tüm fiyat/stok yazmaları güvenli varsayılan olarak kapalı.
- Dikkat edilmesi gerekenler: Shopify için `read_returns` / `read_all_orders`; N11 için panel mutabakatı; Ticimax için web servis paketi ve canlı Üye Kodu; T-Soft için REST1 lisansı, metot/IP izinleri ve varyant yazma sınırı; Magento için PaaS/on-prem Access Token, doğru store/source bağlamı ve SaaS için ayrı IMS adapter; Amazon için SP-API onboarding gerekir.
- Dokümantasyon: `docs/integrations/marketplace/OTHER_CHANNELS_P0_IMPLEMENTATION.md`
- PR / commit: Henüz oluşturulmadı.
