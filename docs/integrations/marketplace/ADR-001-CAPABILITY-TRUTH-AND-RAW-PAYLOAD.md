# ADR-001 — Pazaryeri Kabiliyet Doğruluğu ve Kayıpsız Raw Payload

- **Tarih:** 2026-07-22
- **Durum:** Kabul Edildi

## Bağlam

Provider registry, kullanıcı arayüzündeki kabiliyet ilanının kaynağıdır; senkron motoru ise connector interface ve `capabilities()` sonucunu kullanır. İki kaynak ayrıştığında kullanıcı “hazır” gördüğü özelliği çalıştıramaz veya scheduler görünmeyen bir metodu hiç çağırmaz. Ayrıca her kanalın veri şeması farklı olduğu için tüm alanları ilk günden ortak kolonlara dönüştürmek şema şişmesi ve yanlış anlam riski doğurur.

## Değerlendirilen seçenekler

1. Registry'yi belge olarak tutup connector farklarını manuel izlemek.
2. Registry'yi kaldırıp tüm UI durumunu yalnız connector'dan üretmek.
3. Registry'yi ürün/metaveri kaynağı, connector'ı çalışma zamanı kaynağı olarak korumak; boolean kabiliyet eşitliğini test etmek ve geniş sağlayıcı verisini normalize kolonlar + raw payload ile saklamak.

## Karar

Üçüncü yaklaşım seçildi.

- Registry ve connector boolean kabiliyetleri otomatik regresyon testiyle eşit tutulur.
- `excel_only` gibi API dışı destek kipleri boolean parity testinden ayrı değerlendirilir.
- Erişim veya sözleşme bekleyen provider canlı kabiliyet ilan edemez ve kullanıcıya nedenini gösterir.
- Ortak iş alanları normalize edilir; sağlayıcıya özel veya henüz modellenmemiş veri `raw_payload` içinde kayıpsız korunur.
- Riskli yazma akışları ve doğrulanmamış finans scheduler'ları güvenli varsayılan olarak kapalı kalır.

## Sonuçlar

### Olumlu

- UI ile çalışma zamanı arasındaki yanlış hazır algısı testte yakalanır.
- Yeni API alanları migration beklemeden korunabilir.
- Sağlayıcı sözleşmeleri gerçek credential olmadan “tamamlandı” ilan edilmez.
- Geri alma yüzeyi küçüktür.

### Olumsuz

- Bazı veriler normalize sorgulara açılana kadar yalnız JSON raw payload içinde kalır.
- Registry ve connector iki ayrı kaynak olmaya devam eder.
- Büyük payload'lar depolama ve senkron süresini artırabilir; canlı pilotta ölçülmelidir.

## Yeniden değerlendirme koşulları

- Capability metadata'sı tek bir typed manifestten üretilecek hale gelirse registry/connector ikiliği kaldırılabilir.
- Raw payload hacmi saklama sınırlarını aşarsa domain bazlı tablolar ve retention politikası tasarlanmalıdır.
- Herhangi bir yazma capability'si otomatik açılacaksa stok/fiyat veri otoritesi ve rollback sözleşmesi ayrıca karara bağlanmalıdır.
