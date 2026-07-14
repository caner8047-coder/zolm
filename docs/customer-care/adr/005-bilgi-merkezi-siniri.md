# ADR 005: Bilgi Merkezi Sınırı

## Durum
Proposed

## Bağlam
WhatsApp modülü kapsamında `WaKnowledgeArticle` ve `WaKnowledgeArticleChunk` modelleri ile basit bir bilgi merkezi (knowledge base) mevcuttur. Ancak bu yapı, WhatsApp kanalının sınırları içinde tasarlanmış olup, çok kanallı bir asistanın (Trendyol, web widget vb.) kurumsal bilgi tabanı gereksinimlerini (versiyonlama, onay mekanizmaları, tenant izolasyonu, global/yerel makale ayrımı ve ledger bazlı değişiklik geçmişi) tam olarak karşılamamaktadır. `KnowledgeBaseService` içindeki arama algoritması da basitleştirilmiş bir keyword string match seviyesindedir ve relevance hesaplamaları eksiktir.

## Karar
- Mevcut `WaKnowledgeArticle` tablosu WhatsApp'a özel bir kanal kaynağı olarak korunacak; doğrudan kurumsal birleşik bilgi merkezi olarak kabul edilmeyecektir.
- Mimaride iki seçenek değerlendirilmektedir ve nihai karar sonraki fazlarda verilecektir:
  1. **Yeni generic `support_knowledge_*` çekirdeği oluşturulması:** Tamamen sıfırdan kurumsal çok kanallı gereksinimlere uygun tablolar tasarlanacak ve mevcut WhatsApp bilgi yapısından buraya bir uyumluluk/projection köprüsü kurulacaktır (Tercih edilen/önerilen yaklaşım).
  2. **Mevcut WhatsApp bilgi yapısının geçici bir adapter arkasında kullanılması:** Mevcut tabloların diğer kanallar için de bir adapter aracılığıyla sorgulanması. Bu yaklaşım veritabanı şemasında WhatsApp'a bağımlılık yaratsa da kısa vadede göç maliyetini düşürür.
- İlerideki fazda karar verilene kadar mevcut modeller ve servisler korunacaktır.
- Karar ölçütleri olarak:
  - Tenant kapsamı ve izolasyon güvenliği
  - Kaynak izlenebilirliği ve sürümleme (versioning)
  - Onay akışı (draft/published) yönetimi
  - Kanal bağımsızlığı
  - Geriye uyumluluk
  kabul edilmiştir.
- Nihai veri modeli ve migration yaklaşımı Faz 7 öncesindeki kalite kapısında onaylanacaktır.

## Alternatifler
- **Doğrudan WaKnowledgeArticle Kullanmak:** Tüm kanalları doğrudan bu tabloya bağlamak. Kolay bir başlangıç sunsa da, WhatsApp'a özel alanların (varsa template entegrasyonları vb.) diğer kanallarda kirlilik yaratmasına ve global/tenant izolasyonu yönetiminde karmaşıklığa yol açar.
- **Yepyeni support_knowledge Tabloları:** WhatsApp verilerini tamamen buraya göç ettirip eski tabloları silmek (big-bang migration). WhatsApp modülü aktif kullanıldığı için bu yaklaşım yüksek risk taşır ve reddedilmiştir.

## Sonuçlar ve trade-offlar
- **Artılar:** Aktif WhatsApp bilgi tabanı kesintiye uğramaz; yeni kanallar için esnek ve genişletilebilir bir bilgi yapısı tasarlanır; yetkilendirme ve onay süreçleri sisteme entegre edilir.
- **Eksiler:** İki farklı bilgi şemasının yönetimi veya veri taşıma senkronizasyonu ek geliştirme maliyeti getirecektir.

## Geriye uyumluluk
Mevcut `WaKnowledgeArticle` veritabanı şeması ve WhatsApp botunun makale okuma akışları geriye dönük olarak korunacaktır.

## Güvenlik/KVKK etkisi
Firma/Tenant verilerinin izolasyonu bilgi merkezinin en kritik güvenlik gereksinimidir. A Mağazasının AI asistanı, yanlışlıkla B Mağazasının gizli iade politikalarını veya fiyat kurallarını kaynak olarak okuyamamalıdır. İzolasyon anahtarı ve organizasyon/store ilişkisi ADR-002 kapsamında kararlaştırılacak; bilgi merkezi sorguları seçilen tenant context, policy ve scoped query kontrollerini zorunlu olarak kullanacaktır.

## İlgili ZCC gereksinimleri
- ZCC-006 (İnsan onaylı öğrenme merkezi)
- ZCC-007 (Marka sesi)
- ZCC-016 (Veri güvenliği, KVKK ve rol bazlı erişim)

## Uygulama fazı
Faz 7
