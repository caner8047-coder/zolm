# Dalga U — Kanıt Paketi (Revizyon 02)

**Dalga:** U — KVKK Retention / Anonymization / Emergency Stop Hardening  
**Tarih:** 2026-07-13

---

## 1. Uygulanan Değişiklikler

### Yeni Dosyalar

| Dosya | Amaç |
|---|---|
| `app/Services/Support/CustomerCareAnonymizationService.php` | KVKK anonymization motoru; dry-run default, force ile gerçek işlem |
| `app/Console/Commands/CustomerCareAnonymizeCommand.php` | `customer-care:anonymize` artisan komutu |
| `docs/customer-care/kvkk-retention-policy.md` | KVKK veri saklama ve anonymization politikası |
| `tests/Feature/CustomerCare/CustomerCareAnonymizationTest.php` | Dalga U test suite (9 test) |

### Düzenlenen Dosyalar

| Dosya | Değişiklik |
|---|---|
| `app/Console/Commands/CircuitBreakerCommand.php` | `--enable` ile pending AI dispatch'ler `cancelPendingAiDispatches()` üzerinden iptal |
| `app/Services/Support/CustomerCareAnonymizationService.php` | `cancelPendingAiDispatches()` fonksiyonu iptal edilen AI dispatches'a bağlı olan AI `SupportMessage` kayıtlarının `delivery_status` değerini de terminal `'cancelled'` statüsüne çeker ve `SupportAgentAction` emergency stop audit izi yazar. |

---

## 2. Dalga U Test Sonuçları

- `tests/Feature/CustomerCare/CustomerCareAnonymizationTest.php` (PASS)
- `tests/Feature/CustomerCare/CanaryCircuitBreakerTest.php` altındaki yeni test:
  - `test_circuit_breaker_cancel_updates_message_status_and_logs_audit` (PASS - Dispatch iptal edildiğinde bağlı mesajın delivery_status terminal duruma geçer ve audit yazılır)

---

## 3. Güvenlik Kontrolleri

| Kontrol | Sonuç | Açıklama |
|---|---|---|
| Dry-run varsayılan — veri değişmez | ✅ |
| `--store-id` zorunlu — global anonymization yasak | ✅ |
| `--force` olmadan gerçek işlem çalışmaz | ✅ |
| PII redakte + audit ledger bütünlüğü korunur | ✅ |
| Cross-store anonymization engellendi | ✅ |
| Emergency stop: pending AI dispatch'ler iptal | ✅ |
| AI message delivery_status terminal iptal durumuna geçer | ✅ (P1-1 Düzeltmesi) |
| Manual (agent) reply CB open iken etkilenmiyor | ✅ |

---

## 4. Artisan Commands

```
customer-care:anonymize (YENİ)
  --store-id=ID   Zorunlu — mağaza ID
  --conversation-id=ID   Opsiyonel — belirli konuşma
  --force         Gerçek anonymization (varsayılan: dry-run)

customer-care:circuit-breaker (GÜNCELLENDİ)
  --enable   → Devre kesiyor + pending AI dispatches ile bağlı mesajları iptal ediyor
  --disable  → Devreden çıkarıyor (reset)
```

---

## 5. KVKK Retention Dokümantasyonu

`docs/customer-care/kvkk-retention-policy.md`:
- Tablo bazlı saklama süreleri (1-5 yıl)
- Anonymization iş akışı
- Audit ledger silinmeme gerekçesi
- Scheduler politikası (otomatik silme varsayılan KAPALI)

---

## 6. Test Suite Durumu

- **Customer Care Tests**: **219 passed / 838 assertions** (100% Green)
- **npm run build**: ✓ başarılı
- **git diff --check**: temiz
