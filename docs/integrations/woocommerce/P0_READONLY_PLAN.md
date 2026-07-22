# WooCommerce P0 Salt-Okuma Veri Bütünlüğü Planı

**Tarih:** 2026-07-21  
**Durum:** Devam ediyor — P0-A tamamlandı  
**Hedef kitle:** Backend, ürün, pazaryeri operasyonu ve muhasebe ekipleri

## 1. Amaç ve beklenen kullanıcı sonucu

WooCommerce'i yalnız sipariş/stok kanalı olmaktan çıkarıp ZOLM içinde güvenilir bir D2C veri kaynağına dönüştürmek. İlk kullanıcı sonucu; siparişin para birimi, ödeme/kupon/vergi bağlamı ile ürünün açıklama, görsel ve özelliklerinin veri kaybı olmadan ZOLM'e ulaşmasıdır.

## 2. Rapor kontrol sonucu

Hazırlanan raporun ana teşhisi repo ve güncel WooCommerce REST API v3 dokümantasyonuyla uyumludur:

- `wc/v3` güncel yönetim API'sidir.
- Mevcut connector sipariş, ürün, varyasyon, review, refund ve webhook çekirdeğine sahiptir.
- Dar `_fields` listeleri sipariş finans bağlamını ve ürün içeriğini API yanıtı aşamasında eliyordu.
- WooCommerce product review kayıtları standart bir “müşteri sorusu” değildir; ayrı review/rating yüzeyi gerektirir.
- Refund kaydı, bekleyen iade talebiyle aynı kavram değildir; otomatik `approved` eşlemesi operasyonel ayrımı kaybettirir.

İki önemli nüans:

- Ürün para birimi standart product kaydının alanı değildir. Ürün/listing para birimi mağaza ayarından veya mağaza bağlantısı bağlamından gelmelidir.
- Sipariş satırındaki `total_tax / total` hesabı yalnız etkin KDV oranını verir. Birden fazla veya compound vergi varsa bu değer tekil vergi sınıfı yerine birleşik oran olarak yorumlanmalıdır.

Resmî referanslar:

- https://developer.woocommerce.com/docs/apis/rest-api/v3/
- https://developer.woocommerce.com/docs/apis/rest-api/v3/orders/
- https://developer.woocommerce.com/docs/apis/rest-api/v3/products/

## 3. Uygulama dilimleri

### P0-A — Sipariş ve katalog veri bütünlüğü — tamamlandı

- Sipariş `_fields`: para birimi, toplamlar, vergi, ödeme, transaction, müşteri ID, kupon, fee, refund ve metadata alanları.
- Ürün `_fields`: açıklamalar, görseller, özellikler, etiketler, ölçüler, ağırlık, stok/backorder, kargo ve vergi sınıfları.
- Varyasyon `_fields`: açıklama, görsel, ölçü/ağırlık, stok/backorder, kargo/vergi ve attribute alanları.
- `ChannelProduct`: mevcut `description`, `images`, `attributes` alanlarına normalizasyon.
- `ChannelOrderItem`: satır toplamı ve vergi tutarından etkin `vat_rate` hesaplama.
- `ChannelOrder`: connector'dan gelen `currency` ve varsa `exchange_rate` değerini persistence zincirinde koruma.
- Migration etkisi: yok. Mevcut nullable katalog alanları ve sipariş para birimi alanları kullanıldı.

### P0-B — Kategori ve özellik sözlükleri

- `WooCommerceConnector`, `PullsReferenceCategories` sözleşmesini uygulayacak.
- `products/categories` verisi mevcut `mp_categories` tablosuna yazılacak.
- Global attributes ve terms, mevcut `mp_category_attributes` modelinin kategoriye bağlı tasarımıyla doğrudan eşleşmediği için önce veri otoritesi kararı verilecek.
- Tags ve shipping classes için mağazaya bağlı sözlük ihtiyacı değerlendirilecek; global tablolarla tenant verisi karıştırılmayacak.
- Senkronlar idempotent olacak; silme yerine önce `is_active=false` reconciliation tercih edilecek.

### P0-C — Müşteri ve kuponlar

- Yeni sözleşmeler: `PullsCustomers`, `PullsCoupons`.
- Önerilen yeni tablolar: `channel_customers`, `channel_coupons`, `channel_coupon_usages`.
- Tüm kayıtlar `store_id` ile tenant'a bağlanacak; e-posta/telefon ve adres alanları kişisel veri olarak loglardan çıkarılacak.
- Sipariş içindeki `customer_id` ve `coupon_lines`, bağımsız kayıtlarla dış ID üzerinden bağlanacak.
- `wa_coupons` WhatsApp kampanya alanına ait olduğu için ana WooCommerce kupon tablosu olarak yeniden kullanılmayacak.

### P0-D — Refund, vergi ve raporlar

- Global refund okuması ve sipariş bazlı refund detayı ayrı ele alınacak.
- `ChannelClaim` içinde `partial/full`, refund tutarı, ödeme sistemine gönderim sonucu ve refund satır tutarları için backward-compatible alanlar eklenecek.
- Refund kayıtları varsayılan olarak “approved return request” sayılmayacak; muhasebe refund olayı ile operasyonel iade talebi ayrılacak.
- Vergi oranı/sınıfı sözlüğü ve Reports API salt-okuma snapshot'ları ayrı senkron tipleri olacak.

## 4. Değişecek katmanlar

| Dilim | Connector/contract | Service | Model/migration | UI |
| --- | --- | --- | --- | --- |
| P0-A | WooCommerce alan ve normalizasyon kapsamı | Order persistence | Mevcut alanlar | Yok |
| P0-B | Reference contracts | MarketplaceReferenceSyncService | Mevcut kategori + karara bağlı sözlük tabloları | Entegrasyon senkron durumu |
| P0-C | Customer/coupon contracts | Yeni idempotent sync servisleri | Yeni nullable, store-scoped tablolar | Müşteri/kupon read-only görünümü |
| P0-D | Refund/tax/report reads | Claim ve snapshot servisleri | Backward-compatible kolon/tablo | Finans ve sağlık görünümü |

## 5. Geriye uyumluluk

- Mevcut fiyat/stok yazma flag'leri değiştirilmez; salt-okuma kapsamı push yetkisini açmaz.
- `finance=false` P0-A sırasında korunur; sipariş toplamlarının çekilmesi settlement/komisyon capability'sinin açıldığı anlamına gelmez.
- Mevcut sipariş tutar semantiği P0-A'da değiştirilmez. Vergi dahil/hariç gelir kararı canlı payload örnekleriyle doğrulanmadan `billable_amount` yeniden hesaplanmaz.
- Yeni migration'lar yalnız nullable kolonlar veya yeni tablolarla ilerler.
- Mevcut review kayıtları taşınmadan önce UI ve veri modeli için ayrı geçiş planı hazırlanır.

## 6. Test yaklaşımı

- Connector request contract testleri: `_fields`, auth, pagination ve tarih filtreleri.
- Normalizasyon testleri: TRY dışı para birimi, sıfır vergi, indirimli satır, compound vergi, boş görsel/attribute, varyasyon fallback.
- Persistence testleri: tenant izolasyonu, idempotent tekrar sync, yeni alanların eski kayıtları bozmaması.
- Hata testleri: 401/403, 404, 429, timeout, bozuk payload ve kısmi sayfa başarısızlığı.
- Canlı smoke test: read-only API key ile bir test mağazasında sipariş ve tam katalog örneği; loglarda secret/PII kontrolü.

## 7. Riskler ve kontroller

| Risk | Etki | Kontrol |
| --- | --- | --- |
| Geniş `_fields` payload boyutunu artırır | API ve DB yükü | Sayfa boyutu 25, max page 10 ve mevcut polling profili korunur; ölçüm sonrası ayarlanır |
| EUR/USD siparişte kur bilinmiyor | Yanlış TRY kâr hesabı | Currency korunur; exchange rate elde edilmeden finans capability açılmaz |
| Compound vergi etkin oran üretir | KDV sınıfı yanlış yorumlanabilir | Raw `tax_lines` korunur, değer “etkin oran” olarak dokümante edilir |
| HTML açıklama içerir | UI'da XSS riski | Veri ham saklanır; render katmanı sanitize etmeden HTML basmaz |
| Refund ile iade talebi karışır | Yanlış operasyon statüsü | P0-D'de kavramlar ayrılır; mevcut `approved` davranışı ayrıca migrate edilir |
| Referans sözlüklerinde tenant karışması | Veri izolasyonu | Mağazaya özgü kaynaklarda `store_id`; gerçekten global sözlüklerde marketplace scope |

## 8. Tamamlanma ölçütleri

- P0-A mock/regresyon testleri geçer.
- P0-B/C/D için migration ve sync sözleşmeleri ayrı küçük PR'lara bölünür.
- Her dilim tenant izolasyonu ve idempotency testi içerir.
- Canlı read-only smoke test ve payload boyutu ölçümü yapılır.
- Feature flag/sync profile üzerinden yeni senkron tipleri kademeli açılır.

