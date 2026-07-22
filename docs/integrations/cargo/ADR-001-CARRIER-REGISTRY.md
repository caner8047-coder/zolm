# ADR-001 — Taşıyıcı Registry ve Connector Manager

- **Tarih:** 2026-07-21
- **Durum:** Kabul Edildi
- **Alan:** Mimari
- **Etki:** Yüksek

## Bağlam

Kargo tabloları `carrier_code` alanıyla çoklu taşıyıcıya uygun olmasına rağmen servis, takip komutu, olay kayıtları ve pazaryeri aksiyonları doğrudan Sürat connector'ına bağlıydı. Yurtiçi, Aras, HepsiJet ve DHL'in kimlik bilgileri, protokolleri ve durum kodları birbirinden farklıdır. Resmi doküman olmadan ortak bir HTTP/SOAP payload uydurmak canlı sipariş ve müşteri verisi için risklidir.

## Değerlendirilen seçenekler

1. **Her servis içinde taşıyıcıya göre `match` kullanmak.** Hızlı başlar ancak iş mantığını çoğaltır ve yeni firma ekledikçe bütün ZOLM katmanlarında değişiklik gerektirir.
2. **Tek bir genel HTTP/SOAP connector kullanmak.** Endpoint yapılandırması kolay görünür; farklı kimlik doğrulama, payload, etiket ve hata sözleşmelerini güvenli biçimde temsil edemez.
3. **Merkezi registry + taşıyıcı başına connector + ortak manager.** Metadata merkezi kalır; firma protokolleri ayrı sürücülerde sertifikalanır; orkestrasyon değişmez.

## Seçilen yaklaşım ve gerekçe

Üçüncü seçenek kabul edildi. `CargoCarrierRegistry` katalog, alias, durum ve yetenek bilgisini; `CargoCarrierManager` ise yalnızca açıkça tanımlanmış connector sınıflarını çözer. Taşıyıcı sürücüleri ortak `CargoCarrierConnector` sözleşmesini uygular.

Bu yaklaşım mevcut Sürat akışını korur; Yurtiçi, Aras, PTT, HepsiJet ve DHL Express sürücülerinin kendi resmi protokollerini ortak orkestrasyona bağlar ve resmi API sözleşmesi olmayan bir firma için yanlışlıkla sahte ağ çağrısı yapılmasını engeller.

## Sonuçlar

### Olumlu

- Gönderi orkestrasyonu taşıyıcıdan bağımsızdır.
- Yeni sürücü eklemek için çekirdek servisin değiştirilmesi gerekmez.
- Kullanıcı hesabının kurulum ve bağlantı durumu UI'da görünürdür.
- Kimlik bilgileri genel ve şifrelenmiş alanda saklanabilir.
- Fatura ve olay kayıtlarında taşıyıcı izolasyonu güçlenir.

### Olumsuz

- Her firma için ayrı connector ve contract test gerekir.
- Firma başına credential şeması ve pilot sertifikasyon süreci gerekir.
- Gerçek erişim bilgileri olmadan canlı kabul testi yapılamaz.

## Geri dönüş / yeniden değerlendirme koşulları

- Bir taşıyıcı resmi olarak ortak bir entegratör protokolü sağlarsa ilgili sürücüler ortak bir base connector paylaşabilir.
- Trendyol Express bağımsız kurumsal taşıyıcı API'si sunarsa `marketplace_managed` kararı yeniden değerlendirilir.
- DHL eCommerce Türkiye kapsamı istenirse `dhl_express` sürücüsüne eklenmez; ayrı ürün/connector kararı açılır.
