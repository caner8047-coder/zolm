# ADR 007: AI Shadow Mode, Golden Dataset ve Run Ledger Tasarımı

## Durum
Kabul Edildi (Pilot Öncesi Mimari Karar)

## Bağlam
ZOLM AI Müşteri İletişim Merkezi modülünde AI'ın otomatik yanıt gönderme özelliği (`automatic` mode) kritik riskler barındırmaktadır. AI modelinin yanlış bilgi (halüsinasyon) üretmesi, marka sesine uymayan tonlar kullanması ve cross-tenant bilgi sızıntısına yol açması engellenmelidir. Bu nedenle, otomatik yanıt özelliği üretime alınmadan önce shadow mode (gölge mod) ve golden dataset (altın test kümesi) süreçlerinden geçmelidir. Ayrıca her AI kararı ve çıktısı, `support_ai_runs` adında append-only bir ledger tablosunda denetlenmek üzere loglanmalıdır.

## Kararlar

### 1. Shadow Mode Çalışma Prensibi
- Pilot aşamasında `customer-care.auto_reply_enabled` varsayılan olarak `false` tutulacaktır.
- AI, sisteme gelen mesajlar için arka planda taslak cevap üretecek ancak bu cevap harici kanallara (WhatsApp, Trendyol) gönderilmeyecektir.
- Bu sayede temsilciler AI çıktılarını arayüzde inceleyerek doğrulayabilecek (Copilot modu) ve sistem performansı ölçülebilecektir.

### 2. Golden Dataset Değerlendirmesi
- En az 100 adet doğrulanmış gerçek müşteri senaryosu ve beklenen ideal yanıt şablonlarından oluşan bir "Golden Dataset" oluşturulacaktır.
- AI sürüm güncellemelerinde ve prompt değişikliklerinde bu dataset otomatik olarak test edilerek sapma oranları ve halüsinasyon riskleri ölçülecektir.

### 3. AI Run Ledger (`support_ai_runs`) Şeması
Her AI çağrısı için oluşturulacak append-only tablonun yapısı şu şekilde olacaktır:
- `id` (BigInt, PK)
- `store_id` (Int, FK - Tenant İzolasyonu)
- `conversation_id` (Int, FK)
- `prompt_template_key` (String)
- `prompt_raw` (Text)
- `response_raw` (Text)
- `confidence_score` (Int - Dinamik hesaplanan 0-100 arası değer)
- `sources_used_json` (JSON - Grounding için kullanılan tam kaynaklar/dokümanlar)
- `token_in`, `token_out` (Int - Maliyet takibi)
- `latency_ms` (Int)
- `status` (Enum: success, failed, skipped)
- `created_at` (Timestamp)

## Sonuçlar
- Otomatik yanıt sistemine geçiş güvenli hale gelir, körü körüne gönderim yapılması engellenir.
- Yapay zeka maliyeti ve hızı (latency) append-only ledger sayesinde şeffaf biçimde izlenir.
- Güven skoru modelin kendi beyanı yerine, kullanılan veri kaynaklarının tazeliği ve eşleşme oranlarına göre dinamik hesaplanır.

## KVKK Silme, Anonymization ve Audit Bütünlüğü Kararı
`support_ai_runs` tablosundaki denetim verilerinin KVKK ve GDPR saklama politikalarına uygun şekilde korunması amacıyla; store (`store_id`) veya conversation (`conversation_id`) silme girişimlerinde veritabanı yabancı anahtar kısıtlaması **RESTRICT** (engelleme) ile çalışacaktır. Bir mağaza veya konuşma silinmek istendiğinde, ilişkili yapay zeka denetim geçmişi var olduğu sürece işlem engellenecektir.
Eğer bir müşteri/mağaza KVKK kapsamında "silinme" (unutulma) hakkını talep ederse, cascade delete yerine bu log kayıtlarındaki kişisel veriler (`prompt_raw`, `response_raw`) redakte edilerek/anonimleştirilerek saklanacak, doğrudan fiziksel silme yapılmayacaktır.

