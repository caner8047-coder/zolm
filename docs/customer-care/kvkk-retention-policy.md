# ZOLM AI Müşteri İletişim Merkezi — KVKK Veri Saklama ve Anonimleştirme Politikası

**Versiyon:** 1.0  
**Tarih:** 2026-07-12  
**Kapsam:** ZOLM Customer Care modülü — support_* tabloları, wa_* tabloları, eval ve dispatch ledger'ları

---

## 1. Genel İlkeler

- **Asgari Veri İlkesi:** Yalnız hizmet sunumu için zorunlu olan kişisel veriler işlenir.
- **Saklama Süresi Sınırlandırma:** Veriler belirlenen süreler sonunda anonymize edilir veya silinir.
- **Audit Bütünlüğü:** Denetim ve hukuki kayıtlar anonymize edilir; silinmez.
- **Store Kapsamı:** Her anonymization işlemi tek bir mağaza kapsamıyla sınırlıdır.
- **Güvenli Silme Ön Koşulu:** Anonymization öncesinde pilot kapatılmalıdır (circuit breaker enable).

---

## 2. Tablo Bazlı Saklama Süreleri

| Tablo | İçerik | Saklama Süresi | Anonymization Notu |
|---|---|---|---|
| `support_conversations` | Konuşma meta verisi | 2 yıl | `external_customer_id` alanı nullify; diğer meta korunur |
| `support_messages` | Mesaj gövdesi (PII içerebilir) | 1 yıl | `body_encrypted` → `[KVKK-SİLİNDİ]`; diğer meta korunur |
| `support_dispatches` | Gönderim meta verisi | 2 yıl | `payload_json` nullify |
| `support_dispatch_attempts` | Gönderim denemesi logu | 2 yıl | Audit bütünlüğü — **silinmez**, `error_message` redakte |
| `support_agent_actions` | Temsilci eylem logu | 3 yıl | `details_json` → PII alanları redakte; `action`, `user_id`, `created_at` korunur |
| `support_ai_runs` | AI gönderim çalıştırma logu | 1 yıl | `input_text`, `output_text` → redakte; `confidence_score`, `model`, `status` korunur |
| `support_ai_eval_runs` | Golden eval çalıştırma logu | 3 yıl | Audit bütünlüğü — **silinmez**; anonymize edilmez (PII içermez) |
| `support_ai_eval_case_results` | Eval vaka sonuçları | 3 yıl | `actual_output`, `expected_output` → redakte |
| `wa_contacts` | WhatsApp kişi PII | 1 yıl | `first_name`, `last_name` → `[KVKK-SİLİNDİ]`; `phone_e164_encrypted`, `phone_hash` nullify |
| `wa_consent_events` | Onay olayları | 5 yıl (hukuki) | **Silinmez**; KVKK ispat kaydıdır |
| `wa_suppressions` | Gönderim engel listesi | 5 yıl (hukuki) | **Silinmez**; optout kanıtıdır |
| `wa_inbound_messages` | WhatsApp gelen mesajlar | 1 yıl | `body` → redakte; `meta_message_id`, `message_type`, `received_at` korunur |
| `wa_outbox` | WhatsApp gönderim kuyruğu | 1 yıl | `body_text` → redakte; gönderim meta korunur |

---

## 3. Anonymization Kuralları

### 3.1 PII Redaksiyonu
- Telefon numaraları: `phone_e164_encrypted` alanı nullify, `phone_hash` nullify
- İsim/soyisim: `[KVKK-SİLİNDİ]` ile değiştir
- Mesaj gövdesi: `[KVKK-SİLİNDİ]` ile değiştir
- E-posta: `[KVKK-SİLİNDİ]` ile değiştir

### 3.2 Audit Ledger Korunması
Aşağıdaki alanlar **hiçbir zaman silinmez veya değiştirilmez**:
- `id`, `created_at`, `updated_at`
- `action` (eylem tipi)
- `user_id` (sisteme giriş yapan temsilci kimliği)
- `conversation_id`, `message_id` (ilişki anahtarları)
- `status`, `attempt_count` (dispatch durumu)
- `confidence_score`, `model`, `provider` (AI gönderim meta)
- `passed_gate`, `average_score` (eval sonucu)

### 3.3 Cascade Delete Önleme
`support_dispatch_attempts.support_dispatch_id` FK: `RESTRICT`  
→ Dispatch silinmeden önce tüm attempt'lerin manuel temizlenmesi gerekir.  
→ Bu kural accidental silmeyi engeller.

---

## 4. Anonymization İş Akışı

```
1. Pilot kapat (circuit breaker enable)
   → customer-care:circuit-breaker --store=ID --enable

2. Dry-run raporu al
   → customer-care:anonymize --store-id=ID

3. Raporu incele, onay ver

4. Gerçek anonymization çalıştır
   → customer-care:anonymize --store-id=ID --force

5. Anonymization logunu denetim dosyasına kaydet
```

---

## 5. Scheduler Politikası

> [!IMPORTANT]
> Otomatik veri silme/anonymization **varsayılan olarak KAPALIDIR**.
> Canlı silme/anonymization otomatik başlamamalıdır.
> Tüm retention işlemleri manuel onay gerektirir.

Retention scheduler eklenirse:
- Varsayılan: `--dry-run` modunda rapor-only çalışır
- Gerçek anonymization: manuel `--force` ile ayrıca tetiklenir
- Scheduler asla `--force` parametresiyle otomatik çalıştırılmaz

---

## 6. Audit Ledger'ın Silinmeme Gerekçesi

KVKK madde 7 kapsamında kişisel veri içermeyen denetim kayıtları (audit trail) hizmet kalitesi, hukuki uyum ve anlaşmazlık çözümü için saklanır. Bu kayıtlar:

- **Anonymize edilir** (PII içeren metin alanları redakte)
- **Silinmez** (ilişki anahtarları ve meta veriler korunur)
- **Zaman damgası ve aksiyon tipi** değiştirilemez

---

## 7. İlgili Dosyalar

| Dosya | Amaç |
|---|---|
| `app/Services/Support/CustomerCareAnonymizationService.php` | Anonymization motoru |
| `app/Console/Commands/CustomerCareAnonymizeCommand.php` | Artisan komutu |
| `app/Console/Commands/CircuitBreakerCommand.php` | Emergency stop + AI dispatch iptali |
| `app/Services/Support/Security/PiiRedactor.php` | PII maskeleme |

---

## 8. Sorumlu Taraflar

- **Teknik uygulama:** ZOLM geliştirme ekibi
- **KVKK sorumluluğu:** ZOLM veri işleme temsilcisi
- **Denetim onayı:** Pilot kapsamı ve saklama süreleri iç hukuk birimi onayına sunulmalıdır
