# ADR-002 — PTT Barkod Aralığının Atomik Yönetimi

- **Tarih:** 2026-07-21
- **Durum:** Kabul Edildi
- **Alan:** Kargo / Veri Tutarlılığı
- **Etki:** Yüksek

## Bağlam

PTT, anlaşmalı müşteriye 12 haneli bir barkod başlangıç ve bitiş aralığı verir. Her gönderide bu aralıktan benzersiz bir değer kullanılmalı ve PTT dokümanındaki algoritmayla 13. kontrol hanesi eklenmelidir. Aynı hesaptan eşzamanlı gönderi oluşturulması aynı barkodun iki kez ayrılması riskini doğurur.

## Değerlendirilen seçenekler

1. Son gönderinin barkodunu bulup bir artırmak: ek tablo gerektirmez, fakat eşzamanlı isteklerde yarış durumuna açıktır.
2. Barkodu istek anında rastgele seçmek: çakışma ve aralık tüketimini izleyememe riski vardır.
3. Hesap satırını veritabanı kilidiyle güncelleyip sıradaki değeri şifrelenmiş hesap ayarında tutmak: atomik tahsis ve kolay aralık yenileme sağlar.

## Seçilen yaklaşım

Üçüncü seçenek kabul edildi. `PttCargoConnector`, `cargo_carrier_accounts` satırını `lockForUpdate()` ile kilitler; `next_barcode` değerini ayırıp bir artırır. Ardından 12 haneyi sırayla 1 ve 3 ile çarparak PTT kontrol hanesini hesaplar. Uzak servis çağrısı başarısız olsa bile ayrılan değer yeniden kullanılmaz; bu küçük aralık kaybı, mükerrer barkod riskinden daha güvenlidir.

## Sonuçlar

### Olumlu

- Eşzamanlı gönderiler aynı barkodu kullanamaz.
- Barkod aralığı tükenmesi kontrollü hata üretir.
- Aralık değiştirildiğinde sayaç yeni başlangıç değerine döner.
- Kontrol hanesi PTT dokümanıyla aynı algoritmadan üretilir.

### Olumsuz

- Başarısız uzak servis isteği bir barkod değerini tüketebilir.
- Yeni aralık PTT’den alınıp hesap ekranında manuel güncellenmelidir.

## Geri dönüş / yeniden değerlendirme koşulları

- PTT barkodu servis tarafında üretmeye başlarsa yerel aralık yönetimi kaldırılır.
- Bir hesap için birden fazla aktif aralık gerekirse ayrı barkod aralığı tablosu ve kullanım geçmişi eklenir.
