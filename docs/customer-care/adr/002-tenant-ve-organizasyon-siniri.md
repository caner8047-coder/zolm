# ADR 002: Tenant ve Organizasyon Sınırı

## Durum
Proposed

## Bağlam
ZOLM mimarisinde bugünkü en dar sahiplik ilişkisi çoğunlukla `User` ve bu kullanıcıya bağlı `LegalEntity` ile `MarketplaceStore` seviyesindedir. Mevcut veritabanı şemalarında sorguların ayrımı genellikle `store_id` alanı üzerinden yapılmaktadır. Ancak, bu yapı çok kullanıcılı bir organizasyon hiyerarşisini (Örn: aynı şirkette çalışan birden fazla temsilcinin/acentenin belirli yetkilerle aynı mağazaları yönetebilmesi) tam olarak desteklememektedir. Ayrıca, support sorgularında otomatik query scope veya global tenant filtrelemesi olmaması, cross-tenant veri sızıntılarına yol açabilecek bir açık oluşturmaktadır.

## Karar
- Mevcut `User → LegalEntity → MarketplaceStore` zinciri veri sahipliği için başlangıç noktası olarak kabul edilir.
- `store_id` otomatik olarak nihai tenant kimliği kabul edilmeyecektir; store, gerçek organizasyon/tenant sınırları içindeki bir kaynak sınırından ibaret olabilir.
- Eloquent global scope'ların veritabanı seviyesinde gerçek bir izolasyon sağlamadığı, sadece uygulama katmanında bir koruma/filtre olduğu açıkça kabul edilir.
- Faz 2'de organization/legal entity/member/store ilişkisi ve `TenantContext` ya da `CurrentOrganization` mimari yaklaşımı tam olarak karara bağlanmadan global scope'lar uygulanmayacaktır.
- Çok kullanıcılı SaaS/firma yapısı ve roller için `user_id → legal_entity → organization` zinciri kanonik model olarak peşinen kabul edilmeyecektir. Nihai üyelik, organizasyon ve kaynak sahipliği sınırı Faz 2 tasarım kararında netleştirilecektir.
- Karar kriterleri arasına policy yetkilendirmesi, scoped repository/query builder kullanımları, job context propagation (arka plan işlerinde tenant bağlamının taşınması) ve çapraz-tenant negatif güvenlik testleri eklenmiştir.

## Alternatifler
- **Nihai SaaS Tenant Geçişi:** Faz 1'de yepyeni bir `organizations` ve `organization_user` (membership) tablosu ekleyerek tüm sistemi bu yapıya geçirmek. Bu yaklaşım, mevcut modüller üzerinde çok büyük veri modeli değişiklikleri (big-bang refactoring) gerektireceği için reddedilmiştir.
- **Yalnız store_id Bazlı İzolasyon:** Her şeyi basitçe `store_id` ile filtrelemek. Bu durum organizasyonlar arası ortak yetkilendirmeyi (Örn: admin ve support agent rolleri) kısıtlar.

## Sonuçlar ve trade-offlar
- **Artılar:** Mevcut sistemlerin kararlılığı bozulmadan güvenli bir geçiş planı hazırlanır. Faz 2'ye geçmeden önce tasarım netleştirilir.
- **Eksiler:** Faz 1 bittiğinde hala netleşmemiş organizasyonel yapılar bulunacaktır; veri partition analizi Faz 2'ye bırakılmıştır.

## Geriye uyumluluk
Mevcut `user_id` ve `legal_entity_id` alanlarıyla tam uyumluluk korunur. Geriye dönük veri senkronizasyonu için script/backfill planlanmalıdır.

## Güvenlik/KVKK etkisi
Tenant sınırının netleşmesi; policy, scoped sorgu ve job context kontrolleriyle birlikte cross-tenant veri sızıntısı riskini azaltacaktır. Negatif güvenlik testleri, uygulama katmanındaki izolasyon varsayımlarını sürekli doğrulayacaktır.

## İlgili ZCC gereksinimleri
- ZCC-016 (Veri güvenliği, KVKK ve rol bazlı erişim)

## Uygulama fazı
Faz 2
