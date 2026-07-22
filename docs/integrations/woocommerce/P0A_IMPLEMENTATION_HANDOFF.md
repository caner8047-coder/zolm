# WooCommerce P0-A Uygulama ve Ekip Teslim Notu

**Tarih:** 2026-07-21  
**Durum:** Kod ve mock/regresyon testleri tamamlandı; canlı smoke test bekliyor

## Tamamlananlar

- Sipariş finans/ödeme/kupon/vergi alanları WooCommerce `_fields` kapsamına alındı.
- Ürün ve varyasyonlarda açıklama, görsel, attribute, fiziksel, stok ve vergi bağlamı çekilir hale getirildi.
- Ürün açıklama/görsel/attribute verisi mevcut `ChannelProduct` alanlarına normalize edildi.
- Sipariş satırı için etkin KDV oranı hesaplandı; ham vergi payload'ı korundu.
- Sipariş para biriminin connector'dan `ChannelOrder` kaydına kadar taşınmaması düzeltildi.
- Yeni migration veya yazma capability değişikliği yapılmadı.

## Değiştirilen bileşenler

- `config/marketplace.php`: WooCommerce order/product/variation field sözleşmeleri.
- `app/Services/Marketplace/Connectors/WooCommerceConnector.php`: normalizasyon ve etkin KDV hesabı.
- `app/Services/Marketplace/MarketplaceOrderSyncService.php`: currency/exchange-rate persistence.
- `tests/Feature/WooCommerceConnectorTest.php`: request contract ve normalizasyon regresyonları.
- `tests/Feature/MarketplaceOrderSyncServiceTest.php`: sipariş para birimi/kur persistence testi.

## Doğrulama

```text
PASS Tests\Feature\WooCommerceConnectorTest
PASS Tests\Feature\MarketplaceOrderSyncServiceTest
PASS Tests\Feature\MarketplaceCatalogSyncServiceTest
PASS Tests\Feature\MarketplaceIntegrationsWooSafeProfileTest
19 test, 103 assertion
```

Beş değişen PHP dosyasında container içinden `php -l` başarılıdır. Canlı WooCommerce API çağrısı yapılmadı.

## Kullanım ve yayın

- Mevcut `orders` ve `products` sync akışları ek bir kullanıcı aksiyonu olmadan geniş alanları çeker.
- Yeni feature flag eklenmedi; değişiklik salt-okuma ve mevcut sync tiplerinin alan kapsamı değişikliğidir.
- Canlıya almadan önce read-only credential ile tek mağaza smoke testi ve payload boyutu/log süresi karşılaştırması yapılmalıdır.
- Geri alma: field listesi ve normalizasyon commit'i geri alınabilir; migration/veri silme gerektirmez. Daha önce yazılan raw payload ve katalog içeriği zararsız biçimde kalır.

## Bilinen sınırlamalar

- Müşteri, kupon, kategori/attribute sözlükleri ve global refund için bağımsız sync henüz yoktur.
- Döviz kuru WooCommerce siparişinden gelmez. Currency korunur; gerçek kur başka güvenilir kaynaktan sağlanana kadar yabancı para kâr hesabı açılmamalıdır.
- `vat_rate` birden fazla/compound vergi halinde etkin orandır.
- Review kayıtları hâlâ müşteri soruları modülündeki `review` tipi üzerinden tutulmaktadır.

## Önerilen commit planı

1. `feat: enrich WooCommerce read-only order and catalog payloads`
2. `fix: persist marketplace order currency and exchange rate`
3. `test: cover WooCommerce P0 data integrity mappings`
4. `docs: plan WooCommerce P0 read-only integration phases`

Commit oluşturulmadı; kullanıcıya ait ilgisiz worktree değişiklikleri stage edilmedi.

## Notion taslağı

**Title:** WooCommerce P0-A Salt-Okuma Veri Bütünlüğü  
**Type:** Reference  
**Category:** Engineering  
**Tags:** WooCommerce, Marketplace, Laravel, Integration, P0  
**Status:** Draft  
**Owner:** Belirlenecek  
**Last Reviewed:** 2026-07-21

### Özet

WooCommerce sipariş finans bağlamı ile ürün içerik alanlarının ZOLM'e veri kaybı olmadan ulaşmasını sağlayan ilk P0 dilimi tamamlandı. Değişiklik salt-okumadır; fiyat/stok push flag'lerini veya finance capability'sini açmaz.

### İş ihtiyacı ve kullanıcı etkisi

Dar API alan listesi nedeniyle para birimi, ödeme yöntemi, kupon, vergi, ürün açıklaması, görseller ve attributes kayboluyordu. Yeni kapsam sayesinde operasyon ve ilerideki muhasebe/raporlama yüzeyleri daha zengin ham veriye erişebilir.

### Teknik yaklaşım

Mevcut connector ve persistence zinciri genişletildi. Yeni domain tabloları açılmadan önce mevcut `ChannelOrder`, `ChannelOrderItem` ve `ChannelProduct` alanları kullanıldı; desteklenmeyen ayrıntılar raw payload içinde korundu.

### Veri modeli ve migration

Migration yoktur. Mevcut nullable katalog kolonları ve sipariş currency/exchange-rate kolonları kullanılmıştır.

### Yetki ve feature flag

Read-only veya read/write WooCommerce key mevcut sync davranışını kullanır. Yeni yazma yetkisi açılmadı. `price_push_enabled`, `stock_push_enabled` ve `finance_enabled` güvenli varsayılanları korunur.

### Test kapsamı

Connector field contract, ürün/sipariş normalizasyonu, etkin KDV, currency/exchange-rate persistence, katalog persistence ve güvenli WooCommerce profil regresyonu test edildi. 19 test ve 103 assertion geçti.

### Bilinen sınırlamalar

Bağımsız müşteri/kupon/reference/refund sync yoktur; canlı mağaza smoke testi yapılmamıştır; yabancı para kur kaynağı ayrıca tasarlanmalıdır.

### Geri alma planı

İlgili field/normalizasyon commit'leri geri alınır. Şema değişmediği için down migration veya veri silme gerekmez.

### İlgili commit/PR

Henüz oluşturulmadı.

### Yayın tarihi ve sorumlu

Belirlenecek.

## Decision log

### Karar: P0-A'da yeni tablolar yerine mevcut kanal modellerini ve raw payload'ı kullan

**Tarih:** 2026-07-21  
**Durum:** Kabul Edildi  
**Alan:** Architecture  
**Etki:** Orta

**Bağlam:** WooCommerce raporu geniş bir P0 kapsamı öneriyor. Müşteri, kupon ve reference sözlükleri yeni veri modeli gerektirirken sipariş finans bağlamı ve ürün içeriğinin bir bölümü mevcut tablolarda karşılanabiliyor.

**Değerlendirilen seçenekler:**

1. Tüm P0'u tek seferde yeni tablolarla uygulamak: kapsam ve canlı risk yüksek.
2. Yalnız `_fields` genişletip her şeyi raw payload'da bırakmak: hızlı fakat mevcut sorgulanabilir kolonlardan yararlanmıyor.
3. Mevcut güvenli kolonları doldurup yeni domainleri sonraki dilimlere ayırmak: seçilen yaklaşım.

**Gerekçe:** Migration gerektirmeden erken kullanıcı değeri sağlar, canlı connector davranışını küçük bir diff ile korur ve müşteri/kupon/refund veri otoritesi kararlarını aceleye getirmez.

**Olumlu sonuçlar:** Küçük geri alma yüzeyi, hızlı test, geriye uyumluluk, zengin raw payload.  
**Olumsuz sonuçlar:** Bağımsız domain sorguları henüz yok; payload boyutu artar; etkin KDV gerçek vergi sınıfının yerini tutmaz.  
**Yeniden değerlendirme koşulları:** P0-B/C migration tasarımı, canlı payload hacmi limitleri veya yabancı para finans capability'si açılması.

## Slack taslağı

🚀 WooCommerce P0-A veri bütünlüğü tamamlandı

- Ne değişti: Sipariş finans/ödeme/kupon/vergi alanları ile ürün açıklama, görsel ve özellik kapsamı genişletildi; sipariş para biriminin DB'ye taşınması düzeltildi.
- Kullanıcıya etkisi: WooCommerce verisi artık raporlama ve katalog ekranları için daha az alan kaybıyla geliyor.
- Test durumu: 19 test, 103 assertion geçti; PHP syntax kontrolleri başarılı.
- Yayın / feature flag durumu: Salt-okuma değişikliği; yeni flag yok, fiyat/stok ve finance güvenli varsayılanları değişmedi.
- Dikkat edilmesi gerekenler: Canlı read-only smoke test ve payload süre/boyut ölçümü gerekli. Yabancı para kuru henüz ayrı kaynaktan beslenmiyor.
- Dokümantasyon: `docs/integrations/woocommerce/`
- PR / commit: Henüz oluşturulmadı.
