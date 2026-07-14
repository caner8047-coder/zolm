# Dalga AR — Enterprise API Kanıt Paketi

**Tarih:** 2026-07-13  
**Durum:** ✅ Tamamlandı (KG01 Revizyonu Uygulandı)

---

## 1. Değiştirilen / Eklenen Dosyalar

| Dosya | Tür | Açıklama / KG01 Düzeltmesi |
|---|---|---|
| `database/migrations/2026_08_03_110000_create_support_api_tables.php` | Yeni | Temel DB tabloları. |
| `app/Models/SupportApiClient.php` | Yeni | API İstemcileri. |
| `app/Models/SupportApiToken.php` | Yeni | API Erişim Tokenları. |
| `app/Models/SupportApiAccessLog.php` | Yeni | API logları. |
| `app/Services/Support/CustomerCareEnterpriseApiService.php` | Yeni | **[KG01]** API reply gönderimi `SupportReplyService::sendAgentReply` standart outbox/dispatch zincirine bağlanarak bypass açık çıkışı tamamen engellendi. |
| `app/Http/Controllers/CustomerCare/EnterpriseApiController.php` | Yeni | **[KG01]** getMessages decrypt edilen gövdeyi PiiRedactor'den geçirerek döner. getConversations listenin tüm model alanlarını döndürmek yerine allowlist DTO'ya dönüştürür. |
| `app/Livewire/CustomerCare/Api.php` | Yeni | **[KG01]** Organizasyon dropdown'ı `getAccessibleOrganizations` ile tenant-scoped yapıldı. |
| `tests/Feature/CustomerCare/CustomerCareEnterpriseApiTest.php` | Yeni | **[KG01]** Disabled channel, unavailable capability, human ownership lock, master kill switch kısıtlama ve single outbox/dispatch standart başarılı gönderim testleri eklendi. |
| `docs/customer-care/enterprise-api-contract.md` | Yeni | API sözleşme belgesi. |
| `config/customer-care.php` | Güncellendi | Konfigürasyon dosyası. |
| `.env.example` | Güncellendi | Çevre değişkenleri kılavuzu. |
| `routes/web.php` | Güncellendi | Web rotaları. |
| `routes/api.php` | Güncellendi | API rotaları. |

---

## 2. Migration Listesi

| Migration | Tablo | Rollback |
|---|---|---|
| `2026_08_03_110000` | `support_api_clients`, `support_api_tokens`, `support_api_access_logs` | `Schema::dropIfExists` sıralı |

---

## 3. Route / Command / Scheduler

**Rotalar:**

| Rota | Adı | Middleware |
|---|---|---|
| `GET /customer-care/api` | `customer-care.api` | `customer-care.feature:enterprise_api_enabled` |
| `GET /api/customer-care/v1/conversations` | `customer-care.api.conversations` | inline token auth & scope check |
| `GET /api/customer-care/v1/conversations/{id}/messages` | `customer-care.api.messages` | inline token auth & scope check |
| `POST /api/customer-care/v1/conversations/{id}/reply` | `customer-care.api.reply` | inline token auth & scope check |
| `GET /api/customer-care/v1/analytics/summary` | `customer-care.api.analytics` | inline token auth & scope check |

---

## 4. Feature Flag Varsayılanları

```
CUSTOMER_CARE_ENTERPRISE_API_ENABLED=false
```

---

## 5. Tenant / KVKK / Fail-Closed Güvenlik Kanıtları

- **Hash'li Token Güvenliği:** API token plain-text olarak asla DB'de saklanmaz. Sadece SHA-256 hash'i saklanır. `token_hash_verification_works_and_plain_token_is_not_stored` ✅
- **Scope & Tenant Yetkilendirmesi:** Tokenın yetkili olmadığı scope veya mağazalara erişim engellenir. `scope_missing_returns_forbidden`, `cross_store_conversation_reading_is_blocked` ✅
- **Standart Outbound & Politika Denetimi:** API üzerinden gelen yanıtlar doğrudan DB'ye yazılmak yerine `SupportReplyService::sendAgentReply()` standart outbox/dispatch zincirinden geçer ve `SupportPolicyEngine` denetimine tabidir. **[KG01]** ✅
- **Human Ownership Lock Koruması:** Konuşma bir müşteri temsilcisi tarafından sahiplenilmiş durumdaysa (human lock) API üzerinden gönderilen yanıtlar engellenir (403). `reply_endpoint_blocks_when_human_ownership_lock_active` **[KG01]** ✅
- **PII-Safe Response & DTO:** API üzerinden dönen mesaj içerikleri `PiiRedactor` ile otomatik maskelenir. Konuşma listesi Eloquent modeli yerine allowlist DTO nesnesine indirgenir. `api_response_masks_pii_in_messages_list` **[KG01]** ✅
- **Revoked/Expired Token Engeli:** İptal edilen tokenlerin erişimi anında kesilir. `revoked_token_cannot_access` ✅
- **PII-Safe Access Log:** log kayıtları e-posta vb. hassas payload verilerini loglamaz, otomatik maskeler. `api_access_logs_do_not_leak_pii_or_secrets` ✅

---

## 6. Test Komutları ve Sonuçları

```bash
./vendor/bin/sail artisan test tests/Feature/CustomerCare/CustomerCareEnterpriseApiTest.php --no-coverage --compact
```

**Sonuç:** 13 passed / 29 assertions ✅

---

## 7. Kapsam Dışı

- Üçüncü taraf OAuth2 Authorization Server altyapısı
- API üzerinden webhook aboneliği oluşturma
- Çoklu IP adresi whitelist kuralları yönetimi
