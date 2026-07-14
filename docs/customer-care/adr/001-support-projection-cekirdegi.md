# ADR 001: support_* Birleşik Projection Çekirdeği

## Durum
Accepted

## Bağlam
ZOLM uygulamasında Trendyol soru-cevap ve WhatsApp mesajlaşma özellikleri aktif olarak kullanılmaktadır. Müşteri iletişimini tek bir inbox üzerinden birleştirmek için yeni veri modellerine ihtiyaç duyulmaktadır. Ancak, mevcut verileri kopyalayan ve tekrar eden paralel `care_*` konuşma/mesaj tablolarının eklenmesi, çalışan sistemlerin geriye uyumluluğunu bozma ve veri senkronizasyonu karmaşası yaratma riski taşımaktadır. Halihazırda var olan `support_*` şeması ise birleşik konuşmaları modellemek için olgun bir temel sunmaktadır.

## Karar
- Yeni modülün bağımsız bir veri adası olmasını engellemek amacıyla **`support_*`** (`support_channels`, `support_conversations`, `support_messages` vb.) tabloları birleşik iletişim çekirdeğinin kanonik projection temeli olarak kabul edilmiştir.
- Mevcut `marketplace_questions` ve `wa_*` kanal source-of-truth kayıtları aynen korunacaktır.
- `MarketplaceQuestion` kayıtları ile `SupportConversation` arasındaki ilişki, zorunlu yabancı anahtar (foreign key) bağımlılıkları veya yıkıcı taşımalar yerine, deterministic external ID'ler (`trendyol_` veya `wa_` prefixli) ve source reference JSON yapıları üzerinden idempotent projection olarak kurgulanacaktır.
- Paralel `care_conversations` veya `care_messages` veri yapıları oluşturulması önerisi reddedilmiştir.

## Alternatifler
- **Yeni care_* Tabloları:** Tüm kanalları içeren yepyeni bir veri yapısı tasarlamak. Bu yaklaşım, mevcut `support_conversations` ve `wa_*` yapıları ile veri tekrarları yaratacak, veri tutarsızlığı risklerini artıracak ve çalışan kodlarda karmaşık refactoring gerektirecektir.
- **Doğrudan Polymorphic İlişki:** `support_conversations` tablosunu her kanala doğrudan polymorphic FK'lar ile bağlamak. Bu, veritabanı şemasını kanal tablolarına sıkı sıkıya bağlar ve veri tabanı genişletilebilirliğini zorlaştırır.

## Sonuçlar ve trade-offlar
- **Artılar:** Mevcut stabil veri modeli korunur; veri tekrarı ve projection çakışması riski azaltılır; Trendyol ve WhatsApp modülleri bağımsız olarak çalışmaya devam eder. Tenant izolasyonu bu ADR'den bağımsız güvenlik kontrolleriyle sağlanır.
- **Eksiler:** İki veri katmanı (source-of-truth ve birleşik projection) arasında veri tutarlılığını sağlamak için idempotent bir senkronizasyon/projection katmanı yazılması gerekmektedir (Faz 4).

## Geriye uyumluluk
Mevcut tablolar üzerinde destructive (yıkıcı) migration yapılmayacağı ve sütun silinmeyeceği için geriye uyumluluk tam olarak korunur.

## Güvenlik/KVKK etkisi
- Birleşik projection yaklaşımı veri tekrarlarını ve tutarsızlıklarını azaltır, ancak tenant sızıntısını tek başına engellemez. Tenant izolasyonu ve erişim yetkileri ayrıca enforce edilecektir.
- `support_messages` tablosundaki `body_encrypted` alanında Laravel model cast seviyesindeki `encrypted` şifrelemesi mutlak güvenlik sağlamaz, sadece olası veritabanı sızıntılarında veri ifşası riskini azaltır. Hassas verilerin maskelenmesi, şifreleme anahtarlarının rotasyonu, log redaksiyonu ve API yetkilendirme katmanları ayrıca uygulanmalıdır.

## İlgili ZCC gereksinimleri
- ZCC-001 (Katalog ve sipariş temelli cevap)
- ZCC-009 (Birleşik kanal deneyimi)

## Uygulama fazı
Faz 3 ve Faz 4

## Pilot Kapsamı Dışı Kararı (Projection Yaşam Döngüsü)
Mevcut Pilot/Faz-1 aşamasında, projection tetikleme işlemleri sadece inbound webhook/API olayları ve manual trigger akışlarında senkronize şekilde idempotent çalışacaktır. Otomatik event/job/backfill/cursor/recovery yaşam döngüsü mekanizmaları, pilot sonrası ve gelecek fazların (Dalga D) konusu olarak kapsam dışı bırakılmıştır.
