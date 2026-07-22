# ADR-003 — MNG Kargo'nun Aktif Katalogdan Kaldırılması

- **Tarih:** 2026-07-21
- **Durum:** Kabul Edildi

## Bağlam

MNG Kargo, DHL Group bünyesine katıldığı için ZOLM'de yeni hesap kurulabilen bağımsız bir taşıyıcı olarak gösterilmeyecektir. Bununla birlikte eski siparişler, tazmin kayıtları, raporlar ve pazaryeri yanıtları `MNG Kargo` veya `mng` değerini içerebilir.

ZOLM'deki mevcut `dhl_express` sürücüsü MyDHL API kullanan DHL Express ürünüdür. DHL eCommerce Türkiye ile aynı servis ve hesap sözleşmesi değildir.

## Değerlendirilen seçenekler

1. MNG kaydını aktif katalogda bırakmak.
2. MNG kaydını silip geçmiş kayıtları `dhl_express` olarak topluca dönüştürmek.
3. MNG'yi aktif katalog ve yeni seçimlerden kaldırmak, geçmiş veriyi okuyabilen uyumluluk eşlemelerini korumak.

## Karar

Üçüncü seçenek seçildi:

- `mng` aktif taşıyıcı kataloğundan kaldırılır.
- Yeni sipariş ve tazmin kaydı seçimlerinde MNG gösterilmez.
- Eski kayıtların görüntülenmesi, takip bağlantısı ve dış pazaryeri verisinin okunması için tarihsel eşlemeler korunur.
- Eski MNG kayıtları `dhl_express` koduna otomatik dönüştürülmez.

## Gerekçe ve sonuçlar

Bu yaklaşım kullanıcıya artık kurulamayacak bir taşıyıcı sunmaz ve yanlış DHL Express API çağrılarını engeller. Karşılığında eski kayıtlarda MNG adı görünmeye devam edebilir; bu yalnızca tarihsel veriyi doğru temsil eder.

## Geri dönüş / yeniden değerlendirme

DHL eCommerce Türkiye için resmî ürün dokümanı, test erişimi ve müşteri hesabı sağlanırsa `dhl_ecommerce_tr` gibi ayrı bir connector kodu değerlendirilir. Geçmiş verinin taşınması ancak doğrulanmış bir kod eşleme planıyla ayrıca ele alınır.
