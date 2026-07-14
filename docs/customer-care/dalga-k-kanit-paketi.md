# Dalga K Kanıt Paketi — Temsilci Çalışma Ekranı (Inbox)

Bu doküman, Dalga K kapsamında gerçekleştirilen gerçek operasyon Inbox ekranı ve müşteri temsilcisi araçlarının doğrulama adımlarını ve kanıtlarını içerir.

## 1. Uygulanan Özellikler ve Kapsam
- **Açık Panel Arayüzü:** ZOLM Kurumsal Açık Panel Sistemi kurallarına tam uyumlu, üç bölmeli (Split-Pane) Inbox ekranı tasarlanmıştır (`inbox.blade.php`):
  - **Sol Panel:** Konuşma listesi, arama motoru, durum filtreleri.
  - **Orta Panel:** Seçili konuşma detayları, geçmiş mesaj akışı (Gelen/Giden/Draft).
  - **Sağ Panel:** Müşteri/Sipariş özeti, AI mod kontrolleri (Manual/Copilot/Automatic) ve atama aksiyonları.
- **Sahiplik Yönetimi (Claim/Release):** Temsilciler konuşmaları kendi üzerlerine atayabilir (`claim`) veya tekrar AI havuzuna bırakabilir (`release`).
- **Çözümleme Yönetimi (Resolve/Reopen):** Tamamlanan konuşmalar çözüldü (`resolved`) olarak işaretlenebilir veya tekrar açılabilir.
- **Yapay Zeka Yardımcısı (Copilot):** Konuşma Copilot modundayken temsilci tek tıkla "AI Taslağı Oluştur" butonunu kullanarak Gemini'den taslak yanıt alabilir, üzerinde düzenleme yaptıktan sonra gönderebilir.
- **Güvenlik Geçişleri (Automatic Mode):** Bir konuşmayı otomatik moda geçirmek istendiğinde, sistem o mağaza için veritabanında geçerli ve başarılı bir Golden Dataset değerlendirmesinin bulunmasını şart koşar.

## 2. Test Sonuçları (CustomerCareInboxTest)
İlgili test paketi (`tests/Feature/CustomerCare/CustomerCareInboxTest.php`) yazılmış ve başarıyla geçmiştir.

```bash
./vendor/bin/sail artisan test tests/Feature/CustomerCare/CustomerCareInboxTest.php --compact
```

### Geçen Test Senaryoları
1. `test_it_throws_404_if_feature_flag_is_disabled`: `customer-care.inbox_enabled` bayrağı kapalıyken Inbox sayfasına erişimin engellendiği (404) doğrulanmıştır.
2. `test_unauthorized_user_cannot_view_conversation`: Temsilcinin yetkili olmadığı başka bir mağazaya ait konuşmayı görmesinin engellendiği doğrulanmıştır.
3. `test_claim_and_release_actions`: Konuşmanın temsilci tarafından sahiplenilmesi ve sahipliğin AI'a geri bırakılması adımlarının DB'ye işlendiği doğrulanmıştır.
4. `test_resolve_and_reopen_actions`: Konuşmanın kapatılması ve yeniden açılması süreçleri doğrulanmıştır.
5. `test_agent_reply_policy_block`: Temsilcinin yazdığı mesajın policy engine (örneğin "kapıda ödeme" yasağı) tarafından engellendiği ve `support_agent_actions` tablosuna `policy_block` olarak kaydedildiği doğrulanmıştır.
6. `test_agent_reply_success`: Politika ihlali olmayan temiz temsilci mesajının başarıyla hedefe gönderildiği doğrulanmıştır.
7. `test_copilot_ai_draft_generation`: Copilot modunda AI taslağının üretilip mesaj kutusuna yüklendiği doğrulanmıştır.
8. `test_switching_to_automatic_mode_requires_passing_eval_gate`: Başarılı bir golden eval kaydı yokken mağazanın otomatik yanıta geçişinin sistem tarafından reddedildiği doğrulanmıştır.

---
**Durum:** Başarılı (Wave K Teslime Hazır)
