# Dalga AU — Connector Certification Kanıt Paketi

**Tarih:** 2026-07-13  
**Durum:** ✅ Tamamlandı

---

## 1. Değiştirilen / Eklenen Dosyalar

| Dosya | Tür | Açıklama |
|---|---|---|
| `database/migrations/2026_08_04_120000_create_support_connector_certification_runs_table.php` | Yeni | Sertifikasyon çalışmaları ve tekil denetim log tabloları. |
| `app/Models/SupportConnectorCertificationRun.php` | Yeni | Sertifikasyon çalışması Eloquent modeli. |
| `app/Models/SupportConnectorCertificationCheck.php` | Yeni | Sertifikasyon tekil denetimi Eloquent modeli. |
| `app/Services/Support/CustomerCareConnectorCertificationService.php` | Yeni | Kanal sertifikasyon denetimleri ve webhook sandbox olay simülasyonu servis mantığı. |
| `app/Console/Commands/CustomerCareCertifyConnectorsCommand.php` | Yeni | Kanal sertifikasyon CLI komutu. |
| `app/Console/Commands/CustomerCareSimulateChannelEventCommand.php` | Yeni | Sandbox webhook simülasyon CLI komutu. |
| `app/Livewire/CustomerCare/Certification.php` | Yeni | Sertifikasyon Livewire component sınıfı (dropdown store-scoping dahil). |
| `resources/views/livewire/customer-care/certification.blade.php` | Yeni | ZOLM açık panel kurallarına uygun Blade görünümü. |
| `tests/Feature/CustomerCare/CustomerCareConnectorCertificationTest.php` | Yeni | Sertifikasyon özellik test paketi. |
| `docs/customer-care/connector-certification-runbook.md` | Yeni | Entegrasyonlar ve sandbox için asgari canlandırılma kriterlerini içeren runbook kılavuzu. |
| `config/customer-care.php` | Güncellendi | `connector_certification_enabled` özellik bayrağı eklendi. |
| `.env.example` | Güncellendi | `CUSTOMER_CARE_CONNECTOR_CERTIFICATION_ENABLED` bayrağı eklendi. |
| `routes/web.php` | Güncellendi | `/customer-care/certification` rotası eklendi. |

---

## 2. Migration Listesi

| Migration | Tablo | Rollback |
|---|---|---|
| `2026_08_04_120000` | `support_connector_certification_runs`, `support_connector_certification_checks` | `Schema::dropIfExists` sıralı |

---

## 3. Route / Command / Scheduler

**Rotalar:**

| Rota | Adı | Middleware |
|---|---|---|
| `GET /customer-care/certification` | `customer-care.certification` | `customer-care.feature:connector_certification_enabled` |

**Artisan komutları:**

| Komut | Açıklama |
|---|---|
| `customer-care:certify-connectors --store=ID --dry-run` | Mağazanın tüm aktif kanalları için entegrasyon sertifikasyon denetimlerini çalıştırır |
| `customer-care:simulate-channel-event --store=ID --channel=web_chat --fixture=path --dry-run` | Webhook olayını sandbox imza doğrulama kurallarıyla simüle eder |

---

## 4. Feature Flag Varsayılanları

```
CUSTOMER_CARE_CONNECTOR_CERTIFICATION_ENABLED=false
```

---

## 5. Tenant / KVKK / Fail-Closed Güvenlik Kanıtları

- **Web Chat HMAC Doğrulama Güvenliği:** Web Chat üzerinden gelen webhook ve inbound olaylarında payload JSON içeriği ve `signature` değeri, entegrasyon ayarlarındaki `webhook_secret` ile doğrulanır. İmzası geçersiz veya eksik olan tüm istekler fail-closed olarak engellenir. `sandbox_web_chat_simulation_verifies_signature_fail_closed` ✅
- **Kanal Bağlantısı Yoksa Devre Dışı (Fail-Closed):** Eğer bir mağazanın `IntegrationConnection` ayarı bulunmuyorsa veya aktif değilse kanal kapasitesi anında `unavailable` durumuna düşer ve gönderimler engellenir. `missing_connector_returns_unavailable_capabilities` ✅
- **Sertifikasyon Raporlarında PII/Secret Güvenliği:** Sertifikasyon detayları oluşturulurken `cc_` veya `plain_` önekli açık sırlar/tokenlar taranarak otomatik olarak maskelenir (`[TOKEN-MASKELENDİ]`). `certification_details_mask_secrets_and_pii` ✅

---

## 6. Test Komutları ve Sonuçları

```bash
./vendor/bin/sail artisan test tests/Feature/CustomerCare/CustomerCareConnectorCertificationTest.php --no-coverage --compact
```

**Sonuç:** 9 passed / 33 assertions ✅
