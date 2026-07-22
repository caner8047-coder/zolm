# Adobe Commerce / Magento REST Uygulama ve Kabul Notu

**Tarih:** 2026-07-22  
**Durum:** Hazır; kullanıcı Integration Access Token ve mağaza bilgilerini girerek kullanabilir  
**Kapsam:** Magento Open Source ile Adobe Commerce PaaS/on-prem  
**Kapsam dışı:** Adobe Commerce as a Cloud Service (SaaS)

## Amaç ve kullanıcı sonucu

ZOLM kullanıcısı Magento Open Source veya Adobe Commerce PaaS/on-prem mağazasının HTTPS adresini, Integration Access Token bilgisini, store view kodunu ve gerekiyorsa MSI stok kaynak kodunu girerek mağazayı doğrudan bağlayabilir. Sipariş, ürün, fatura özeti ve credit memo verileri ortak ZOLM modellerine alınır; veri değiştiren fiyat ile kaynak bazlı stok yazmaları kullanıcı açana kadar kapalı tutulur.

Adobe Commerce REST yolu PaaS/on-prem kurulumlarında `/rest/<store-view-code>/V1` biçimindedir. Adobe Commerce as a Cloud Service ise IMS server-to-server kimlik doğrulaması, tenant tabanlı farklı URL ve `Store` header'ı kullandığı için aynı token formuyla güvenli biçimde çalıştırılamaz. Gateway ve readiness kontrolü SaaS adreslerini açık hata ile reddeder.

Resmî kaynaklar: [REST API overview](https://developer.adobe.com/commerce/webapi/rest/), [PaaS/on-prem authentication](https://developer.adobe.com/commerce/webapi/get-started/authentication/), [SaaS server-to-server authentication](https://developer.adobe.com/commerce/webapi/rest/authentication/server-to-server/).

## Teknik yaklaşım

- Provider anahtarı `magento`, kullanıcı etiketi `Adobe Commerce / Magento` olarak tanımlandı.
- Integration Access Token şifreli bağlantı credential alanında saklanır ve `Authorization: Bearer` ile gönderilir.
- Varsayılan store view kodu `all`, varsayılan MSI source code `default` değeridir.
- Mağaza URL'si yalnız HTTPS kabul eder; kullanıcı bilgisi içeren, local/internal ve literal private/rezerve IP hedefleri reddedilir.
- REST sayfalaması `searchCriteria[pageSize]`, `searchCriteria[currentPage]` ve sıralama alanlarıyla yapılır.
- Connector geniş sağlayıcı cevabını `raw_payload` içinde korur; normalize edilebilen alanları ortak sipariş, ürün, finans ve claim modellerine taşır.
- Magento configurable ürün sipariş satırlarında parent parasal değerleri child SKU ile birleştirilir; çift satır üretimi engellenir.
- `complete` sipariş durumu teslim edildi sayılmaz; ZOLM'de `shipped` olarak normalize edilir. Yalnız açıkça teslim durumuna eşlenen özel durumlar `delivered` olur.

Arama ve sayfalama sözleşmesi: [Performing searches with REST APIs](https://developer.adobe.com/commerce/webapi/rest/use-rest/performing-searches/).

## Veri kapsamı

### Siparişler

`GET /V1/orders` üzerinden sipariş kimlikleri, durumlar, oluşturma/güncelleme tarihleri, para birimi, toplamlar, indirim, vergi, kargo, ödeme özeti, müşteri, fatura/teslimat adresleri ve sipariş satırları alınır. Sağlayıcının `extension_attributes` dahil tam cevabı `raw_payload` içinde tutulur.

### Ürünler

`GET /V1/products` üzerinden SKU, ad, tür, attribute set, fiyat, ağırlık, durum, görünürlük, custom attributes, medya ve varsa stok bilgisi alınır. Magento modüllerinin eklediği alanlar kayıpsız biçimde `raw_payload` içinde korunur.

### Finans

`GET /V1/invoices` kayıtları `magento_invoice_summary` olayı olarak normalize edilir. Bu veri Magento satış faturası özetidir; ödeme kuruluşu veya banka hakediş/mutabakat kaydı değildir. Finans okuması varsayılan açıktır ve bu ayrımla etiketlenir.

### İade / alacak kaydı

`GET /V1/creditmemos` kayıtları salt-okuma claim olarak normalize edilir. Credit memo desteği tam RMA yaşam döngüsü, kargo iade kabulü veya otomatik para iadesi anlamına gelmez.

### Fiyat ve stok yazma

- Fiyat: `POST /V1/products/base-prices`
- Stok: `POST /V1/inventory/source-items`

Stok yazması MSI source code gerektirir ve kaynak bazlıdır. Her iki yazma kabiliyeti de kodda mevcut olmakla birlikte sync profile varsayılanında kapalıdır. Resmî sözleşmeler: [catalog pricing](https://developer.adobe.com/commerce/webapi/rest/modules/catalog/catalog-pricing/), [manage source items](https://developer.adobe.com/commerce/webapi/rest/inventory/manage-source-items/).

## Veri modeli ve migration etkisi

- Yeni migration yoktur.
- Mevcut `MarketplaceStore`, şifreli bağlantı credential'ları, ortak sipariş/katalog/finans/claim modelleri ve `raw_payload` alanları kullanılır.
- Geriye dönük veri dönüşümü veya mevcut mağazalarda davranış değişikliği yoktur.
- Provider varsayılanları yalnız yeni Magento sync profillerinde uygulanır.

## Kurulum ve kullanım adımları

1. Magento/Adobe Commerce yönetiminde ZOLM için ayrı bir Integration oluşturun.
2. En az sipariş ve ürün okuma ACL izinlerini verin. Fatura ve credit memo senkronu kullanılacaksa ilgili Sales okuma izinlerini ayrıca açın.
3. Integration'ı etkinleştirip üretilen Access Token'ı alın; yönetici kullanıcı parolasını ZOLM'e girmeyin.
4. ZOLM'de `Adobe Commerce / Magento` kanalını seçin; mağaza HTTPS kök URL'sini ve Access Token'ı girin.
5. Store view için çoğu kurulumda `all`; yalnız belirli görünüm isteniyorsa gerçek store view kodunu girin.
6. MSI stok yazılacaksa kaynak kodunu girin; varsayılan kurulumda genellikle `default` kullanılır.
7. Bağlantıyı doğrulayın ve önce dar tarih aralığında salt-okuma sipariş/ürün senkronu çalıştırın.
8. Magento panel adet/tutarlarını ZOLM ile karşılaştırın. Ardından gerekliyse fatura ve credit memo okumasını tek mağazada açın.
9. Fiyat veya stok yazmasını yalnız tek SKU canary testi, veri otoritesi kararı ve geri alma planından sonra kademeli açın.

## Feature flag ve varsayılanlar

| Özellik | Varsayılan |
|---|---:|
| Sipariş okuma | Açık |
| Ürün okuma | Açık |
| Finans/fatura özeti | Açık |
| Credit memo claim okuma | Açık |
| Fiyat yazma | Kapalı |
| Stok yazma | Kapalı |
| Soru-cevap | Desteklenmiyor |
| Genel webhook | Desteklenmiyor |

## Test kapsamı

- Provider registry ve connector capability eşitliği
- Connector manager çözümleme ve güvenli varsayılanlar
- Bearer token, store view URL üretimi ve REST query sözleşmesi
- Sipariş, configurable satır, ürün, fatura ve credit memo normalizasyonu
- Bağlantı testi
- Base price ve MSI source item yazma payload'ları
- SaaS URL reddi
- Readiness: geçerli PaaS/on-prem bağlantısı, eksik token ve SaaS ayrımı
- Livewire yeni mağaza formu ve kurulum rehberi
- Ortak sipariş, katalog, claim, finans ve manuel yazma regresyonları
- Masaüstü ve 390 px mobil form taşma kontrolü

İlgili regresyon paketi 108 test ve 441 assertion ile geçmiştir. Gerçek Magento credential kullanılmadığı için canlı ACL, extension/modül farklılıkları ve gerçek mağaza verisiyle smoke test açık konudur.

## Bilinen sınırlamalar ve riskler

- Adobe Commerce as a Cloud Service desteklenmez; IMS için ayrı adapter gerekir.
- Kurulu extension'lar `extension_attributes`, alan adları veya iş kurallarını değiştirebilir. `raw_payload` kaybı önler ancak mağazaya özel kabul testini ortadan kaldırmaz.
- Invoice bir settlement/hakediş değildir; finansal mutabakat için ödeme kuruluşu veya ERP kaynağı gerekebilir.
- Credit memo desteği tam Adobe Commerce RMA modülünü kapsamaz.
- MSI kullanılmayan veya özel kaynak yöneten mağazalarda stok otoritesi ayrıca belirlenmelidir.
- Fiyat `store_id`, stok `source_code` bağlamına duyarlıdır. Yanlış değer farklı mağaza görünümünü veya kaynağı etkileyebilir.
- Yazma endpoint'leri ACL ve mağaza iş kurallarına göre reddedilebilir; önce tek SKU canary zorunludur.

## Canlı kabul kriterleri

- Ürün okuma ile bağlantı testi başarılı.
- Son 24 saat sipariş adedi, para birimi, toplam ve satır SKU/adetleri panelle eşleşiyor.
- Configurable ürünler çift satır veya hatalı SKU üretmiyor.
- Ürün/varyant sayısı ile status/visibility alanları panelle tutarlı.
- Etkinleştirilecekse invoice ve credit memo sayıları/tutarları panelle eşleşiyor.
- Tek SKU fiyat canary işleminde doğru store bağlamı güncelleniyor ve geri alınabiliyor.
- Tek SKU stok canary işleminde doğru source code güncelleniyor ve geri alınabiliyor.
- Hatalı token, yetersiz ACL ve rate/servis hataları kullanıcıya anlaşılır mesajla dönüyor.

## Geri alma planı

- Magento sync profile pasife alınarak tüm periyodik okumalar durdurulabilir.
- Finans, fiyat ve stok flag'leri mağaza bazında kapatılabilir.
- Provider registry ve connector manager eşlemesi birlikte geri alınabilir.
- Migration olmadığı için şema rollback gerekmez; alınmış `raw_payload` kayıtları denetim amacıyla zararsız biçimde kalabilir.
- Yazma canary işleminde önceki fiyat/stok değeri operasyon kaydından geri gönderilmelidir.

## Önerilen commit planı

1. `feat: add Magento REST marketplace pilot connector`
2. `test: cover Magento sync normalization and safe writes`
3. `docs: document Magento PaaS pilot acceptance workflow`

Commit oluşturulmadı; kullanıcıya ait worktree değişiklikleri stage edilmedi.

## Notion taslağı

**Başlık:** Adobe Commerce / Magento REST Pilot Entegrasyonu  
**Özet:** Magento Open Source ve Adobe Commerce PaaS/on-prem için sipariş, ürün, invoice özeti, credit memo ve kontrollü fiyat/stok entegrasyonu eklendi.  
**İş ihtiyacı ve kullanıcı etkisi:** Müşteri Integration Access Token ve mağaza bilgilerini girerek ZOLM'e mağaza bağlayabilir; desteklenmeyen SaaS bağlantısı yanlış hazır görünmez.  
**Teknik yaklaşım:** Bearer REST connector, store view bazlı URL, source-aware MSI stock write, base price write, ortak modeller ve raw payload.  
**Değiştirilen bileşenler:** Provider registry, connector manager, Magento gateway/connector, readiness, sync profile defaults, Livewire kurulum rehberi ve testler.  
**Veri modeli / migration:** Migration yok; mevcut şifreli credential ve ortak kanal modelleri kullanılıyor.  
**Kullanım:** Integration oluştur, ACL ver, token/URL/store view/source code kaydet, salt-okuma kabulü yap, sonra canary ile yazmaları aç.  
**Yetki / feature flag:** Sipariş ve ürün açık; finans ile fiyat/stok yazmaları kapalı. SaaS desteklenmiyor.  
**Test kapsamı:** Mock REST sözleşmeleri, normalizasyon, readiness, parity, senkron regresyonları ve responsive form kontrolü.  
**Bilinen sınırlamalar:** Canlı credential testi bekliyor; extension attributes mağazaya göre değişebilir; invoice settlement değildir; credit memo tam RMA değildir.  
**Geri alma:** Sync profile/flag kapatma veya provider/manager eşlemesini geri alma; migration rollback yok.  
**Commit / PR:** Henüz oluşturulmadı.  
**Yayın tarihi:** Belirlenecek  
**Sorumlu:** Belirlenecek

## Slack taslağı

🚀 Adobe Commerce / Magento REST pilotu tamamlandı

- Ne değişti: Magento Open Source ve Adobe Commerce PaaS/on-prem için sipariş, ürün, invoice özeti, credit memo ve kontrollü fiyat/stok connector'ı eklendi.
- Kullanıcıya etkisi: Mağaza URL'si, Integration Access Token, store view ve source code girilerek pilot bağlantı kurulabilir.
- Test durumu: Connector, readiness, capability parity ve ortak senkron testleri geçti; masaüstü/mobil form kontrol edildi. Gerçek mağaza smoke testi bekliyor.
- Yayın / feature flag durumu: Sipariş/ürün okuma açık; finans ve fiyat/stok yazmaları varsayılan kapalı.
- Dikkat edilmesi gerekenler: Adobe Commerce SaaS ayrı IMS adapter gerektirir; invoice settlement değildir; yazmalar tek SKU canary sonrası açılmalıdır.
- Dokümantasyon: `docs/integrations/marketplace/MAGENTO_REST_PILOT_IMPLEMENTATION.md`
- PR / commit: Henüz oluşturulmadı.
