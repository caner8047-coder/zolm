# ZOLM AI Müşteri İletişim Merkezi — Güvenlik Tehdit Modeli

**Tarih:** 2026-07-13  
**Kapsam:** Dalga AP — Security Assurance

---

## 1. Varlıklar (Assets)

| Varlık | Açıklama |
|---|---|
| Müşteri mesajları | Şifreli `body_encrypted` alanında saklanır |
| AI/Prompt konfigürasyonu | `support_artifact_versions` (is_current kontrolü) |
| Webhook HMAC secret | `Crypt::encryptString` ile şifreli saklanır |
| Deney varyantları ve sonuçları | Store-scoped, PII redacted |
| Güvenlik kanıtları | `evidence_data_encrypted` alanında şifreli |
| Kullanıcı kimlik bilgileri | Laravel authentication katmanı |
| Görev ve onay akışları | `support_approval_requests`, audit trail |

---

## 2. Trust Boundary'ler

```
[Kullanıcı/Tarayıcı] → [HTTPS] → [Laravel + Livewire] → [MySQL]
[Harici kanal (Trendyol/WA/Meta)] → [HMAC-doğrulanmış webhook] → [Projection Service]
[AI Provider (Gemini)] → [API Key + budget guard] → [AI Orchestrator]
[Artisan CLI] → [system_actor + RBAC] → [Mutating commands]
```

---

## 3. Ana Tehditler ve Mitigasyonlar

### T1: IDOR (Insecure Direct Object Reference)
- **Risk:** Farklı mağazanın konuşma, mesaj veya snapshot verilerine erişim.
- **Mitigation:** `TenantContext::enforceStoreAccess()` her servis çağrısında zorunlu. Test: `cross_store_*` test senaryoları.

### T2: Prompt Injection
- **Risk:** Kullanıcı/müşteri girdisinin AI sistem talimatlarını manipüle etmesi.
- **Mitigation:** `SupportPolicyEngine` + `PromptInjectionDetector` her AI çalıştırma öncesi kontrol. Bilgi tabanına ham müşteri girdisi yazılmaz.

### T3: Data Leakage / PII Sızıntısı
- **Risk:** Müşteri e-postası, TCKN, telefon bilgisinin log, export veya dashboard'a sızması.
- **Mitigation:** `PiiRedactor`, `SupportSuccessNote::createRedacted()`, `SupportSecurityEvidenceItem::createEncrypted()`. Kanıt paketi raw payload içermez.

### T4: Fake Provider Success (Fail-Open)
- **Risk:** AI provider yanıt vermediğinde sistemin otomatik başarılı yanıt dönmesi.
- **Mitigation:** `CustomerCareAiOrchestrator` provider exception'larını propagate eder; `FakeCustomerCareAiAdapter` yalnızca test ortamında aktif. Provider key yoksa finding üretilir.

### T5: Webhook Spoofing
- **Risk:** Sahte kanal webhook'u kabul edilirse yanlış mesaj projeksiyonu.
- **Mitigation:** Her webhook adaptörü `HMAC-SHA256` imzası doğrular; imza eksik/geçersizse 401. Secret `Crypt::encryptString` ile saklanır.

### T6: Replay Attack
- **Risk:** Aynı webhook veya dispatch mesajının tekrar işlenmesi.
- **Mitigation:** `SupportDispatch.idempotency_key` unique constraint; `SupportProjectionService` harici ID ile idempotent upsert.

### T7: Secret Leakage
- **Risk:** Webhook secret veya API anahtarının log, export veya UI'a sızması.
- **Mitigation:** Tüm secret'lar `Crypt::encryptString`. Evidence pack raw secret içermez. Terminal çıktısında secret gösterilmez.

### T8: Cross-Tenant Analytics
- **Risk:** Bir mağaza analitiğinin başka mağaza verisini içermesi.
- **Mitigation:** `TenantContext` tüm analytics, success, experiment ve security servislerinde aktif.

### T9: Unsafe Export
- **Risk:** Excel/Markdown exportun XML injection veya encoding hatası içermesi.
- **Mitigation:** Evidence pack `str_replace(['|', "\n"])` ile markdown sanitize eder. Excel export `setCellValueExplicit` + `cleanString()`.

### T10: Automatic Reply Runaway
- **Risk:** Lansman onayı olmadan otomatik yanıtın tüm konuşmalara açılması.
- **Mitigation:** `CustomerCareLaunchService` fail-closed; feature flag varsayılanı `false`. Rollback otomatik AI dispatch'leri iptal eder.

---

## 4. Kanıt — Test Eşleşmeleri

| Tehdit | Test Kanıtı |
|---|---|
| T1 IDOR | `cross_store_snapshot_access_is_blocked`, `cross_store_audit_is_blocked`, `cross_store_experiment_is_blocked` |
| T3 PII | `pii_is_masked_in_success_notes`, `evidence_pack_does_not_contain_raw_secrets` |
| T4 Fail-Open | `audit_with_critical_findings_is_marked_critical` |
| T5 Webhook | WebChat/WhatsApp/Meta adapter HMAC testleri |
| T6 Replay | `outbox_enqueue_and_idempotency` |
| T7 Secret | `evidence_data_is_encrypted_in_database` |
| T10 Runaway | `cannot_transition_to_approved_without_governance_approval`, `rollback_disables_auto_modes_and_cancels_pending_ai_dispatches` |

---

> Bu doküman harici pentest yerine geçmez; iç güvenlik kanıt referansıdır.
