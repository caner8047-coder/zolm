# ADR-007 — Hazır Provider ve Güvenli Yazma Varsayılanları

**Tarih:** 2026-07-22  
**Durum:** Kabul Edildi

## Bağlam

Shopify, ikas, IdeaSoft, Ticimax, T-Soft ve Magento connector'larında bağlantı, sipariş, ürün, finans/ödeme özeti ve iade okuma sözleşmeleri uygulanmıştı. Ancak provider registry durumu `pilot` kaldığı için kullanıcı, API bilgilerini girebildiği ve senkron çalıştırabildiği halde kanalın kullanılamaz olduğunu düşünüyordu.

Hazır bağlantı durumu ile fiyat/stok gibi veri değiştiren otomasyonların güvenlik durumu birbirinden farklı kavramlardır.

## Değerlendirilen seçenekler

1. `pilot` etiketini gerçek müşteri kabulü tamamlanana kadar korumak.
2. Provider'ları `ready` yapmak ve bütün okuma/yazma özelliklerini otomatik açmak.
3. Provider'ları `ready` yapmak, desteklenen salt-okuma akışlarını açmak ve fiyat/stok yazmalarını bilinçli kullanıcı onayına bırakmak.

## Karar

Üçüncü seçenek seçildi.

- Altı provider registry'de `ready` yayınlanır.
- API bilgilerinin yapısal readiness kontrolü başarılıysa bağlantı `configured` olur ve scheduler tarafından kullanılabilir.
- Sipariş, ürün, finans ve iade/claim okuma yeni profillerde açık gelir.
- Fiyat ve stok yazmaları varsayılan kapalı kalır.
- İlk başarılı canlı senkron `last_verified_at` kaydını günceller.
- IdeaSoft OAuth onayı ve sağlayıcıların lisans/scope şartları readiness sınırı olarak korunur.

Bu karar ADR-002, ADR-003, ADR-004, ADR-005 ve ADR-006 içindeki yalnızca “provider pilot kalır” yayın durumu maddelerini değiştirir. Connector protokolü, platform kapsamı ve güvenli yazma kararları geçerliliğini korur.

## Sonuçlar

### Olumlu

- Kullanıcı API bilgilerini girip desteklenen read-only akışları ek bir ZOLM pilot onayı olmadan kullanabilir.
- UI gerçek connector kabiliyetiyle tutarlı olur.
- Tüm erişilebilir okuma verileri varsayılan profilde scheduler kapsamına girer.
- Veri değiştiren işlemler yanlışlıkla otomatik başlamaz.

### Olumsuz

- İlk bağlantıda yanlış sağlayıcı scope'ları ancak canlı istekle kesinleşebilir.
- Finans okumaları bazı provider'larda ek API çağrısı ve rate-limit yükü oluşturabilir.
- “Hazır” ifadesi sağlayıcının sözleşme/lisans gereksinimlerini ortadan kaldırmaz.

## Yeniden değerlendirme koşulları

- Bir provider'ın resmî API sözleşmesi değişir veya connector capability parity testi bozulursa ilgili provider ayrı olarak kısıtlanır.
- Finans okuması rate-limit sorununa yol açarsa provider bazında poll aralığı artırılır; tüm provider'lar tekrar pilot yapılmaz.
- Güvenli yazma guard'ları kaldırılacaksa ayrı ADR ve canary kanıtı gerekir.
