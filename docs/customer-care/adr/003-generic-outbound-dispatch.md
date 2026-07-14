# ADR 003: Generic Outbound Dispatch

## Durum
Accepted

## Bağlam
Mevcut durumda, `SupportReplyService` outbound (dışa giden) temsilci mesajlarını doğrudan istek akışı içinde (request lifecycle) senkron HTTP çağrıları ile göndermektedir. Gönderim sırasında oluşabilecek kanal API kesintileri, timeout durumları veya rate limit aşımı hataları mesajların kaybolmasına ya da kullanıcının ekranında gecikmelere sebep olmaktadır. Ayrıca `wa_outbox` tablosu ve ilgili `OutboxService` sadece WhatsApp kanalına özel kısıtlar ve parametreler içermekte, Trendyol gibi pazaryerlerinde doğrudan kullanılamamaktadır.

## Karar
- **`support_messages`** tablosu, müşteri ve temsilci mesajlarının sadece kanonik iş kaydıdır; doğrudan delivery queue veya outbox görevi görmez.
- Kanallar arası asenkron gönderim yaşam döngüsünü yönetmek için yeni ve generic bir **`support_dispatches`** tablosu oluşturulacaktır.
- Tüm gönderim denemesi geçmişi (delivery attempts) append-only bir **`support_dispatch_attempts`** tablosunda saklanacaktır. Bu yapı denetim (audit) gereksinimlerini karşılamak için zorunludur.
- `idempotency_key`, `status` (pending, sending, sent, failed), `attempt_count`, `retry_at` (sonraki deneme zamanı), `channel_message_id` (provider external ID) ve `last_error` alanları dispatch katmanına (`support_dispatches`) ait olacaktır.
- Mevcut `wa_outbox` yapısı WhatsApp adapter'ının kendi iç teslimat detayı olarak kalacaktır; generic çekirdeğe dönüştürülmeyecektir.
- Bu mimarinin veritabanı şeması ve kod uygulaması Faz 3 kapsamında yapılacaktır. Faz 1'de herhangi bir model veya migration kodu yazılmayacaktır.

## Alternatifler
- **`wa_outbox` Tablosunu Genelleştirmek:** `wa_outbox` tablosuna nullable Trendyol/Hepsiburada kolonları eklemek. Bu yöntem, WhatsApp'a özel şablon parametreleri ve meta değişkenleri nedeniyle veritabanı şemasında gereksiz kirliliğe yol açtığı için reddedilmiştir.
- **Senkron Gönderimde Kalmak:** İstek akışı içinde doğrudan API çağrıları yapıp hataları kullanıcıya anında göstermek. Bu yaklaşım, zayıf ağ bağlantılarında veya kanal API kesintilerinde mesaj kaybına yol açtığı için production standartlarına uygun değildir.

## Sonuçlar ve trade-offlar
- **Artılar:** Harici API kesintilerinden bağımsız, yüksek erişilebilir gönderim altyapısı kurulur; hata durumlarında audit logları üzerinden tam izlenebilirlik sağlanır.
- **Eksiler:** Kuyruk ve dispatch mekanizmasının eklenmesiyle veritabanı tablo sayısı ve durum makinesi karmaşıklığı artacaktır.

## Geriye uyumluluk
Mevcut senkron `SupportReplyService` ve `wa_outbox` akışları, yeni generic dispatch katmanı Faz 3'te kurulana kadar çalışmaya devam edecektir.

## Güvenlik/KVKK etkisi
Kuyrukta bekleyen ham payload verilerinde kişisel verilerin (PII) korunması, log dosyalarında maskelenmesi ve yetkisiz erişimlerin engellenmesi sağlanmalıdır.

## İlgili ZCC gereksinimleri
- ZCC-009 (Birleşik kanal deneyimi)
- ZCC-017 (Yanlış cevap, geri alma ve düzeltme yaşam döngüsü)

## Uygulama fazı
Faz 3

## Audit Retention ve Cascade Delete Önleme Kararı
Audit/denetim bütünlüğünü korumak ve KVKK saklama politikalarına uyum sağlamak amacıyla, `support_dispatch_attempts` tablosundaki yabancı anahtar (`support_dispatch_id`) silinme politikası `cascade` yerine `restrict` (engelleme) olarak ayarlanmıştır. Bu sayede, ilişkili bir `support_dispatches` kaydı silinmeye çalışılsa dahi veritabanı seviyesinde işlem durdurularak denetim loglarının kazara silinmesi engellenir. Veri temizleme ve anonimleştirme işlemleri ayrı KVKK arşivleme politikalarıyla yürütülecektir.
