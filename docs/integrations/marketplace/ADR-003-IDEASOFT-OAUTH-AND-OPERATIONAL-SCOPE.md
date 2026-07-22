# ADR-003 — IdeaSoft OAuth Token Yönetimi ve Operasyonel API Kapsamı

- **Tarih:** 2026-07-22
- **Durum:** Kabul Edildi

## Bağlam

IdeaSoft Admin API mağazaya özel alan adı üzerinden OAuth 2.0 `authorization_code` kullanır. Access token yaklaşık 24 saat, refresh token yaklaşık iki ay geçerlidir ve token yenileme cevabı yeni refresh token döndürebilir. Aynı API; sipariş/katalog/ödeme/iade gibi ZOLM operasyonlarına ek olarak tema, banner ve içerik gibi mağaza yönetimi yüzeyleri de sunar. Tüm yüzeyi tek pilotta açmak yetki ve kabul kapsamını gereksiz büyütür.

## Değerlendirilen seçenekler

1. Kullanıcıdan access token'ı elle alıp süresi doldukça yeniden girdirmek; erişilebilen tüm Admin API yüzeylerini connector'a eklemek.
2. OAuth callback'i ortak/global token olarak tutmak; yalnız sipariş okumayı açmak.
3. Mağaza ve kullanıcı bağlı, süreli `state` ile authorization code akışı kurmak; refresh token rotasyonunu şifreli mağaza bağlantısında saklamak ve ilk dilimi operasyonel API'lerle sınırlamak.

## Karar

Üçüncü yaklaşım seçildi.

- OAuth `state` değeri mağaza ID, kullanıcı ID ve oluşturulma zamanı ile session'da tutulur; tek kullanımlıdır ve 10 dakikada geçersiz olur.
- Redirect URI ZOLM route'undan sabit üretilir ve IdeaSoft API kaydına aynen girilir.
- Access/refresh token yalnız ilgili mağazanın şifreli `IntegrationConnection` credential alanında tutulur.
- Token süresi dolmadan refresh yapılır; dönen yeni refresh token atomik olarak eski değerin yerini alır.
- İlk connector kapsamı sipariş, ürün, ödeme, iade talebi, webhook ve feature flag kontrollü fiyat/stok güncellemedir.
- Tema, banner, sayfa, üye ve benzeri mağaza yönetim yüzeyleri bu kapsamın dışındadır.
- Gerçek mağaza kabulü tamamlanana kadar sağlayıcı durumu `pilot`, finans ve yazma flag'leri kapalıdır.

## Sonuçlar

### Olumlu

- Kullanıcı günlük access token yenilemek zorunda kalmaz.
- Token ve callback mağaza/tenant sınırında tutulur; çapraz mağaza riski azaltılır.
- Pilot kabul alanı ZOLM'in sipariş, katalog, finans ve iade işlevleriyle sınırlı ve ölçülebilir kalır.
- Refresh token rotasyonu nedeniyle uzun süre sonra sessiz bağlantı kopması önlenir.

### Olumsuz

- OAuth callback, state yaşam döngüsü ve token yenileme kodu bakım gerektirir.
- Gerçek mağaza izni olmadan scope/rate-limit davranışı yalnız mock sözleşme testleriyle kanıtlanabilir.
- Kapsam dışı IdeaSoft yönetim API'leri için ileride ayrı ürün kararı gerekir.

## Geri dönüş ve yeniden değerlendirme koşulları

- IdeaSoft OAuth endpoint, token ömrü veya refresh rotasyonu modelini değiştirirse servis ve readiness kuralları güncellenir.
- ZOLM merkezi token vault kullanmaya başlarsa şifreli credential içindeki token'lar bu servise taşınır.
- Gerçek mağaza kabulünde kısmi ürün güncellemesi güvenilir bulunmazsa fiyat/stok capability'leri kapatılır veya ayrı güvenli yazma adaptörü geliştirilir.
- Tema/içerik yönetimi için somut ürün ihtiyacı oluşursa operasyonel connector kapsamından bağımsız ADR hazırlanır.
