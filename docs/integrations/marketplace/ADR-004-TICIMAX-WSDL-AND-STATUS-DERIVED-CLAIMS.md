# ADR-004 — Ticimax WSDL Sözleşmesi ve Durum Türevli İade Modeli

- **Tarih:** 2026-07-22
- **Durum:** Kabul Edildi

## Bağlam

Ticimax, mağazaya özel Ürün ve Sipariş SOAP servislerini WSDL üzerinden yayınlar ve her operasyonda tek `UyeKodu` ile kimlik doğrular. Resmî Sipariş Servisi; ödeme kayıtlarını ve ayrıntılı iptal/iade durumlarını sipariş yüzeyinde verir, ancak bu pilotta doğrulanmış bağımsız bir iade servisi, ortak webhook veya müşteri soru-cevap sözleşmesi yoktur. El yazımı SOAP XML'i ve varsayılan endpoint'leri kullanmak WCF ad alanı/parametre uyumsuzluğu riski taşır.

## Değerlendirilen seçenekler

1. SOAP XML zarflarını elle üretmek, iade/webhook/soru yüzeylerini varsayımla ilan etmek.
2. Yalnız sipariş ve ürün okuması yapmak; ödeme, iade ve yazma yüzeylerini tamamen kapsam dışı bırakmak.
3. Mağaza WSDL'ini native `SoapClient` ile tüketmek; gerçek operasyon parametrelerini sözleşme testleriyle sabitlemek; ödemeyi siparişten/fallback servisten okumak; iadeyi resmî sipariş durumlarından salt-okuma türetmek; belgelenmeyen kabiliyetleri kapalı ilan etmek.

## Karar

Üçüncü yaklaşım seçildi.

- WSDL adresleri HTTPS mağaza kök URL'sinden üretilir; local/geçersiz URL reddedilir.
- Native `SoapClient` WSDL tiplerini ve SOAP action'larını kullanır; timeout ve test ortamı WSDL cache davranışı merkezî gateway'de tutulur.
- `SelectUrun`, `SelectSiparis`, `SelectSiparisOdeme`, `VaryasyonGuncelle` ve `StokAdediGuncelle` parametre adları gerçek WSDL ile doğrulanıp testte kilitlenir.
- 8–17 durum kodlarındaki iptal/iade kayıtları ortak `ChannelClaim` modeline salt-okuma türetilir; onay/red aksiyon kabiliyeti ilan edilmez.
- Webhook ve soru-cevap kabiliyetleri doğrulanmış resmî sözleşme bulunana kadar kapalıdır.
- Gerçek mağaza kabulü tamamlanana kadar provider `pilot`, finans ve yazma flag'leri kapalıdır.

## Sonuçlar

### Olumlu

- WCF namespace, SOAP action ve büyük/küçük harf duyarlı parametre hataları azaltılır.
- Ticimax'ın sağladığı sipariş, ürün, ödeme ve iade durumu verisi ortak ZOLM akışlarına alınır.
- Karttaki capability ilanı gerçek connector davranışıyla aynı kalır; kullanıcıya sahte webhook/soru desteği gösterilmez.
- Yeni şema gerektirmeden geniş sağlayıcı içeriği `raw_payload` ile korunur.

### Olumsuz

- PHP SOAP eklentisi çalışma ortamında zorunludur.
- WSDL'in canlı mağaza sürümüne göre değişmesi runtime bağlantı hatası doğurabilir.
- Durum-türevli claim, bağımsız iade servisi kadar ayrıntılı miktar/karar yaşam döngüsü garanti etmez.
- Gerçek web servis paketi ve Üye Kodu olmadan kota, fault ve yazma davranışı yalnız mock sözleşme testleriyle kanıtlanabilir.

## Geri dönüş ve yeniden değerlendirme koşulları

- Ticimax REST/GraphQL veya sürümlü yeni SOAP sözleşmesi yayınlarsa gateway ve connector yeniden değerlendirilir.
- Canlı mağaza WSDL'i doğrulanan parametrelerden farklıysa önce mağaza/sürüm varyantı config ile ayrıştırılır; capability'ler doğrulama tamamlanana kadar kapatılır.
- Bağımsız resmî iade servisi erişime açılırsa durum-türevli claim yerine gerçek iade kimliği ve satır yaşam döngüsü kullanılır.
- Resmî webhook veya soru-cevap sözleşmesi doğrulanırsa ayrı güvenlik/kabul testi sonrası capability açılır.
- Canary fiyat/stok güncellemesinde kısmi payload güvenilir bulunmazsa yazma capability'leri kapatılır veya tam varyant adaptörü uygulanır.
