# ADR-006 — Magento PaaS REST ve Kaynak Duyarlı Yazmalar

**Tarih:** 2026-07-22  
**Durum:** Kabul Edildi

## Bağlam

“Adobe Commerce / Magento” adı iki farklı REST kimlik doğrulama ve adresleme modelini kapsayabilir. Magento Open Source ile Adobe Commerce PaaS/on-prem, mağaza hostu altındaki `/rest/<store-view-code>/V1` yüzeyini ve Integration Access Token kullanır. Adobe Commerce as a Cloud Service ise IMS server-to-server kimlik doğrulaması, tenant URL'si ve farklı header sözleşmesi kullanır.

Ayrıca Magento Multi-Source Inventory stokları source code bazında, katalog fiyatları ise store bağlamı dikkate alınarak yazılmalıdır. Tek bir genel “stok/fiyat güncelle” varsayımı yanlış mağaza görünümünü veya stok kaynağını etkileyebilir.

## Değerlendirilen seçenekler

1. **Tek form ve connector ile PaaS/on-prem ve SaaS'ı destekleniyor gibi göstermek.** Kullanıcı deneyimi basit görünür; ancak token, URL, header ve tenant sözleşmeleri farklı olduğu için sessiz yanlış yapılandırma ve güvenlik riski oluşturur.
2. **Her iki platform hazır olana kadar Magento desteğini bekletmek.** Yanlış vaat üretmez; fakat yaygın Magento Open Source/PaaS REST yüzeyi için kullanılabilir entegrasyonu gereksiz geciktirir.
3. **PaaS/on-prem pilotunu ayrı ve katı sınırlarla sunmak; SaaS'ı ayrı IMS adapterına bırakmak.** Çalışan kapsamı dürüstçe açar, desteklenmeyen URL'leri fail-closed reddeder ve ileride ayrı SaaS sağlayıcı akışına izin verir.

## Karar

Üçüncü seçenek seçildi.

- `magento` connector'ı yalnız Magento Open Source ve Adobe Commerce PaaS/on-prem REST sözleşmesini uygular.
- `api.commerce.adobe.com` hedefleri readiness ve gateway katmanlarında reddedilir; kullanıcıya IMS adapter gereksinimi açıklanır.
- Integration Access Token şifreli credential alanında tutulur; yönetici kullanıcı/parola akışı kullanılmaz.
- Store view kodu bağlantı bağlamının parçasıdır ve varsayılanı `all` değeridir.
- Stok yazması MSI `source_code` bilgisiyle `/V1/inventory/source-items` endpointine gider.
- Fiyat yazması `/V1/products/base-prices` endpointine gider ve `store_id` bağlamını destekler.
- Finans, fiyat ve stok yazmaları gerçek mağaza kabuline kadar varsayılan kapalıdır.
- Invoice kayıtları settlement değil fatura özeti; credit memo kayıtları da tam RMA değil salt-okuma claim olarak adlandırılır.

## Gerekçe

Bu yaklaşım capability ilanını gerçek teknik sözleşmeyle eşit tutar. PaaS/on-prem kullanıcıları beklemeden pilot bağlantı kurabilir; SaaS kullanıcısı ise çalışmayacak bir bearer token formuna yönlendirilmez. Source/store bağlamının açık tutulması, çok mağazalı ve MSI kurulumlarda yanlış veri yazma riskini azaltır.

## Sonuçlar

### Olumlu

- Desteklenen ve desteklenmeyen Adobe Commerce dağıtımları görünür biçimde ayrılır.
- SaaS bağlantısı yanlışlıkla PaaS gateway'ine gönderilmez.
- Stok ve fiyat yazmaları Magento'nun kaynak/store semantiğine uygun kalır.
- Yeni migration gerektirmeden mevcut connector mimarisi kullanılır.
- Gelecekte SaaS IMS adapterı ayrı provider/auth akışıyla eklenebilir.

### Olumsuz

- SaaS müşterisi bu pilotla bağlantı kuramaz.
- Kullanıcı store view ve MSI source code kavramlarını doğru yapılandırmalıdır.
- Extension/modül farklılıkları nedeniyle gerçek mağaza kabul testi zorunludur.
- Finance adı altında invoice özeti bulunması ayrıca kullanıcı eğitimi gerektirir.

## Geri dönüş ve yeniden değerlendirme koşulları

- Adobe Commerce SaaS için IMS client bilgileri ve test tenant sağlandığında ayrı adapter/provider tasarımı değerlendirilir.
- Canlı PaaS/on-prem mağazada source/store davranışı resmî sözleşmeden farklı gözlenirse write flag'leri kapalı tutularak connector revize edilir.
- Ortak finans modeli invoice ile settlement ayrımını ayrı tiplerle daha güçlü ifade edecek şekilde büyürse Magento event eşlemesi yeniden ele alınır.
- Adobe REST kimlik doğrulama veya endpoint sözleşmesi değişirse gateway sürümlendirilir; aynı form altında sessiz fallback eklenmez.
