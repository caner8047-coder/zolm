# ADR 004: Customer Care AI Provider

## Durum
Accepted

## Bağlam
ZOLM uygulamasında `AIService` (rapor analizi için) ve `GeminiAiProvider` (WhatsApp AI chat için) şeklinde ayrı AI implementasyonları bulunmaktadır. Ancak, AI Müşteri İletişim Merkezi modülü için kanal bağımsız, DTO'lar ile beslenen ve yapılandırılmış çıktı (structured output) üreten ortak bir AI sağlayıcı contract yapısına ihtiyaç duyulmaktadır. Ayrıca, mevcut sistemde `AiProviderInterface`'in `AppServiceProvider` seviyesinde koşulsuz olarak `FakeAiProvider`'a bind edilmesi ve `GeminiAiProvider`'ın hata durumlarında FakeAiProvider'a fail-open olması, production ortamlarında sahte yanıtların müşteriye gitmesi gibi büyük bir risk barındırmaktadır.

## Karar
- Müşteri iletişimine özel, kanal bağımsız generic AI sözleşme ilkeleri **Faz 1 kapsamında mimari olarak kabul edilmiştir**.
- Sözleşme kodu, interface, DTO'lar ve provider adapter sınıflarının fiilen yazılması/oluşturulması **Faz 6** kapsamında gerçekleştirilecektir. Faz 1'de herhangi bir contract kodu yazılmayacaktır.
- AI sağlayıcı seçimi ve model parametreleri config tabanlı yönetilecektir.
- Production ortamlarında **fail-closed** ilkesi uygulanacaktır. Gerçek AI sağlayıcısı (Gemini/Groq/OpenAI) başarısız olursa veya anahtar eksikse sistem `FakeAiProvider`'a dönmeyecektir; hata fırlatılarak taslak mesaj başarısız statüsüne çekilecek veya konuşma insana eskalasyon (handoff) olarak yönlendirilecektir. `FakeAiProvider` yalnız test veya local geliştirme ortamlarında açık bir config seçimiyle (`CUSTOMER_CARE_DEMO_MODE=true` ve local environment) kullanılabilecektir.
- AI izlenebilirlik kayıtları için kanonik hedef olarak **`support_ai_runs`** tablosu ve gerekli kaynak/taslak (source/draft) ilişkileri Customer Care çekirdeğine eklenecektir (Faz 6).
- Mevcut `wa_ai_runs` tablosu, WhatsApp kaynak kaydı/uyumluluk katmanı olarak korunmaya devam edecek; generic ledger yerine geçirilmeyecek ve hemen kaldırılmayacaktır.

## Alternatifler
- **Mevcut AIService'i Genişletmek:** Mevcut `AIService` sınıfına müşteri sorularını cevaplama metotları eklemek. Bu durum, `AIService`'i çok fazla sorumlulukla doldurur (Single Responsibility kuralını bozar) ve structured output DTO'larını yönetmeyi zorlaştırır.
- **Fail-Open Davranışını Korumak:** AI çöktüğünde demo/sabit metinlerle müşteriye otomatik yanıt vermeye devam etmek. Bu yaklaşım, müşteriye yanlış ve ciddiyetsiz yanıtların gitmesine neden olacağı için ticari güvenilirlik açısından reddedilmiştir.

## Sonuçlar ve trade-offlar
- **Artılar:** AI sağlayıcıları kolayca değiştirilebilir (Gemini'den OpenAI'a geçiş); production ortamında kesin güvenlik ve güvenilirlik sağlanır; LLM maliyetleri net olarak takip edilebilir.
- **Eksiler:** Fail-closed mimarisi nedeniyle AI sağlayıcı kesintilerinde anlık otomatik yanıt kapasitesi duracak ve insan temsilcilerin üzerindeki operasyonel yük artacaktır.

## Geriye uyumluluk
Mevcut WhatsApp `GeminiAiProvider` ve `FakeAiProvider` sınıfları Faz 6'ya kadar mevcut haliyle çalışmaya devam edecek, yeni CustomerCare AI contractı Faz 6 kapsamında bu yapıları adapter arkasına alacaktır.

## Güvenlik/KVKK etkisi
Müşteri verilerinin harici LLM sağlayıcılarına gönderilmesinden önce PII (Kişisel Veri) temizliği ve masking kurallarının uygulanması zorunludur. Hukuki KVKK alt işleyici onayları tamamlanana kadar production ortamında gerçek veri LLM'e gönderilmeyecektir.

## İlgili ZCC gereksinimleri
- ZCC-003 (Güven, risk ve sessiz insan devri)
- ZCC-004 (Üç yanıt modu)
- ZCC-016 (Veri güvenliği, KVKK ve rol bazlı erişim)
- ZCC-018 (Türkçe öncelikli çok dilli çalışma)

## Uygulama fazı
Faz 6
