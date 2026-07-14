# ZOLM AI Müşteri İletişim Merkezi — Dalga D Raporu (Pilot Öncesi Sertleştirme)

Bu rapor, ZOLM AI Müşteri İletişim Merkezi modülü için "Dalga D — Pilot Öncesi Sertleştirme Dalgası" kapsamında çözülen tüm açık şartları, test/güvenlik kanıtlarını, güncellenen ADR listelerini ve schema durumlarını sunar.

---

## 1. Pilot Öncesi Sertleştirme Geliştirmeleri ve Dosya/Test Eşleşmesi

### [Şart 1] System Actor Sözleşmesinin Sertleştirilmesi
- **Uygulanan Dosyalar:**
  - [TenantContext.php](file:///Volumes/TWINMOS/zolm/app/Services/Support/TenantContext.php) (`getSystemActor()`)
  - [customer-care.php](file:///Volumes/TWINMOS/zolm/config/customer-care.php) (`system_actor_email`)
  - [.env.example](file:///Volumes/TWINMOS/zolm/.env.example) (`CUSTOMER_CARE_SYSTEM_ACTOR_EMAIL`)
- **Açıklama:** Factory ve kontrolsüz user fallback'leri production güvenliği nedeniyle kaldırıldı. System actor artık deterministik olarak config dosyasından yönetilmekte ve DB'de provision edilmemişse fail-closed prensibiyle `AuthorizationException` fırlatmaktadır.
- **Test:** [CustomerCareTenantSecurityTest.php](file:///Volumes/TWINMOS/zolm/tests/Feature/CustomerCare/CustomerCareTenantSecurityTest.php) (`test_system_actor_resolves_via_config_and_has_full_tenant_access`)

### [Şart 2] AI Ledger Kayıtlarının AI Akışına Bağlanması
- **Uygulanan Dosyalar:**
  - [GeminiCustomerCareAiAdapter.php](file:///Volumes/TWINMOS/zolm/app/Services/Support/AI/GeminiCustomerCareAiAdapter.php)
  - [FakeCustomerCareAiAdapter.php](file:///Volumes/TWINMOS/zolm/app/Services/Support/AI/FakeCustomerCareAiAdapter.php)
- **Açıklama:** Her iki yapay zeka adapter'ının yanıt üretme metotlarına (`generateAnswer`) `SupportAiRun` log yazımı entegre edildi. Başarılı AI çıktılarında latency, model, prompt, response ve confidence skoru veritabanındaki append-only ledger tablosuna yazılmaktadır. Olası API/bağlantı hatalarında da hata durumu `status = failed` olarak ledger'a kaydedilmektedir.
- **Test:** [CustomerCareAiTest.php](file:///Volumes/TWINMOS/zolm/tests/Feature/CustomerCare/CustomerCareAiTest.php) (`test_ai_adapter_creates_support_ai_run_ledger_records`)

### [Şart 3] Ledger Retention ve Cascade Politikası (Audit Protection)
- **Uygulanan Dosyalar:**
  - [2026_07_26_100000_create_support_dispatches_tables.php](file:///Volumes/TWINMOS/zolm/database/migrations/2026_07_26_100000_create_support_dispatches_tables.php)
  - [2026_07_26_120000_create_support_ai_runs_table.php](file:///Volumes/TWINMOS/zolm/database/migrations/2026_07_26_120000_create_support_ai_runs_table.php)
- **Açıklama:**
  - `support_dispatches` tablosundaki `support_channel_id`, `conversation_id` ve `message_id` yabancı anahtar ilişkileri cascade delete yerine `restrictOnDelete()` yapıldı.
  - `support_ai_runs` tablosundaki `store_id` ve `conversation_id` ilişkileri `restrictOnDelete()` yapıldı.
  - Bu sayede, ilişkili bir konuşma veya mağaza silinmek istendiğinde veritabanı yabancı anahtar kısıtı ile işlemi durdurarak audit ve AI runs geçmişinin kazara silinmesini engeller.
- **Test:** [CustomerCareTenantSecurityTest.php](file:///Volumes/TWINMOS/zolm/tests/Feature/CustomerCare/CustomerCareTenantSecurityTest.php) (`test_parent_deletion_fails_when_child_ledger_exists`)
- **Mimari Kararlar:**
  - [003-generic-outbound-dispatch.md](file:///Volumes/TWINMOS/zolm/docs/customer-care/adr/003-generic-outbound-dispatch.md) (Audit Retention ve Cascade Delete Önleme Kararı eklendi).
  - [007-ai-shadow-golden-eval-ledger.md](file:///Volumes/TWINMOS/zolm/docs/customer-care/adr/007-ai-shadow-golden-eval-ledger.md) (KVKK Silme, Anonymization ve Audit Bütünlüğü Kararı eklendi).

### [Şart 4] Knowledge/Brand Voice Güvenlik ve Durable Domain Audit
- **Uygulanan Dosyalar:**
  - [KnowledgeBaseService.php](file:///Volumes/TWINMOS/zolm/app/Services/Support/KnowledgeBaseService.php)
  - [BrandVoiceService.php](file:///Volumes/TWINMOS/zolm/app/Services/Support/BrandVoiceService.php)
  - [2026_07_26_130000_make_conversation_id_nullable_in_support_agent_actions_table.php](file:///Volumes/TWINMOS/zolm/database/migrations/2026_07_26_130000_make_conversation_id_nullable_in_support_agent_actions_table.php)
- **Açıklama:**
  - Türkçe prompt enjeksiyon ifadeleri (`talimatları unut`, `sen artık`, `ignore all`, `temsilci rolü`, `sistem ayarı`) filtre listesine eklenerek enjeksiyon saldırıları engellendi.
  - Marka sesi değişiklikleri sadece Log olarak kalmayıp, veritabanındaki durable audit tablosu olan `support_agent_actions` içerisine `brand_voice_updated` adıyla domain audit log olarak yazılacak şekilde güncellendi.
- **Testler:** [KnowledgeAndVoiceTest.php](file:///Volumes/TWINMOS/zolm/tests/Feature/CustomerCare/KnowledgeAndVoiceTest.php) (`test_prompt_injection_detection_blocks_unsafe_inputs`, `test_brand_voice_update_writes_durable_audit_log`)

### [Risky Test] WhatsApp Raw Payload Risky Uyarısının Giderilmesi
- **Uygulanan Dosya:** [SupportChannelTest.php](file:///Volumes/TWINMOS/zolm/tests/Feature/WhatsApp/SupportChannelTest.php)
  - `test_whatsapp_raw_payload_not_in_support_message` testi, boş küme üzerinde döngü çalıştığı için assertion üretmemekteydi. Test setup adımına `WaInboundMessage` kaydı eklenerek test yeşile çevrildi ve risky durum ortadan kaldırıldı.

---

## 2. Test Sonuçları ve Doğrulama
- Toplam test paketi başarı skoru: **77 passed, 0 risky, 265 assertions** (Dalga D kapsamında)
- `git diff --check`: Temiz.
- `npm run build`: Başarılı.
