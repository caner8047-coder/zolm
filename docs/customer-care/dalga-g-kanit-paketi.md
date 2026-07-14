# ZOLM AI Müşteri İletişim Merkezi — Dalga G Raporu (Kanal Politika Motoru)

Bu rapor, ZOLM AI Müşteri İletişim Merkezi modülü için **Dalga G — Kanal Politika Motoru ve Gönderim Öncesi Guard** kapsamında tamamlanan geliştirme adımlarını, test/güvenlik kanıtlarını ve audit log yapısını sunar.

---

## 1. Dalga G Kapsamında Çözülen Geliştirmeler ve Dosya/Test Eşleşmesi

### [Şart 1] Deterministik Kanal Politika Motorunun Kurulması
- **Uygulanan Dosya:**
  - [SupportPolicyEngine.php](file:///Volumes/TWINMOS/zolm/app/Services/Support/Policy/SupportPolicyEngine.php) [NEW]
- **Açıklama:**
  - Trendyol, Hepsiburada, N11 ve WhatsApp kanalları için ilk politika profilleri tanımlandı.
  - Karakter limitleri (Trendyol=4000, HB/N11=2000, WhatsApp=1000) kontrol edilir.
  - Haricî link (URL), e-posta, telefon numarası paylaşımı engellenir.
  - Haricî rakip pazaryeri/ödeme yönlendirmeleri (`n11`, `kapida odeme`, `iban` vb.) yasaklanır.
  - Kişisel veri (T.C. Kimlik) ve kesin iade/para iadesi vaadi içeren taahhütler ("kesinlikle iade", "yarın kapınızda" vb.) engellenir.
  - WhatsApp için template placeholder doldurma doğrulaması (`{{1}}` vb.) yapılır.

### [Şart 2] Gönderim Öncesi Guard Entegrasyonları
- **Uygulanan Dosya:**
  - [SupportReplyService.php](file:///Volumes/TWINMOS/zolm/app/Services/Support/SupportReplyService.php)
- **Açıklama:**
  - `sendAiReply()` ve `sendAgentReply()` metotlarının başına politika kontrolü eklendi.
  - AI için politika ihlali fail-closed çalışarak `SupportMessage` ve dispatch oluşturmadan `success=false` ve hata nedenini döner.
  - İnsan için politika ihlali gönderimi durdurur, arayüzde gösterilmek üzere hata mesajı döner ve `SupportAgentAction` tablosuna `policy_block` action tipinde audit log kaydeder.

---

## 2. Git Durumu ve Değişiklik İstatistikleri

### `git status --short` (Dalga G Değişiklikleri)
```text
 M app/Services/Support/SupportReplyService.php
?? app/Services/Support/Policy/SupportPolicyEngine.php
?? tests/Feature/CustomerCare/SupportPolicyEngineTest.php
```

---

## 3. Test Sonuçları (90 Passed, 313 assertions)

Müşteri İletişim Merkezi, Politika Motoru, WhatsApp ve Marketplace Questions hedef testlerinin tamamı yeşildir:

```text
docker exec zolm-laravel.test-1 php artisan test tests/Feature/CustomerCare tests/Feature/WhatsApp/SupportChannelTest.php tests/Feature/MarketplaceQuestionsTest.php --no-coverage

   PASS  Tests\Feature\CustomerCare\SupportPolicyEngineTest
  ✓ ai reply with links or contacts blocked                              1.00s  
  ✓ ai reply exceeding character limit blocked                           0.02s  
  ✓ human reply policy violation blocked and audited                     0.02s  
  ✓ clean reply passes successfully                                      0.02s  

  Tests:    90 passed (313 assertions)
  Duration: 3.04s
```
- git diff --check: **TEMİZ**
- npm run build: **BAŞARILI**
