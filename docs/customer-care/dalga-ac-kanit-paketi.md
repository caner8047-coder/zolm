# Dalga AC — Kanıt Paketi
## Ekip Yönlendirme, Kuyruklar, Mesai ve SLA Escalation

**Tarih:** 2026-07-13  
**Durum:** Kalite Kapısı 01 Revizyonu Tamamlandı

---

## 1. Amaç

Dalga AC; konuşmaları kurallara göre ekiplere yönlendirir, temsilci claim/release sürecini optimistic lock ile korur, SLA ihlallerini tarayıp eskalasyon oluşturur.  
Mesai dışı otomatik yanıt global automation gate'te fail-closed çalışır.

---

## 2. Değişen Dosyalar

| Dosya | Değişiklik |
|---|---|
| [`CustomerCareRoutingService.php`](../../../app/Services/Support/CustomerCareRoutingService.php) | P1-3: claim/release tenant/store guard, `actorCanAccessStore()` eklendi |
| [`CustomerCareAutomationGate.php`](../../../app/Services/Support/AI/CustomerCareAutomationGate.php) | P1-4: business hours fail-closed gate eklendi |
| [`config/customer-care.php`](../../../config/customer-care.php) | `business_hours_auto_reply_enabled` ve `google_reviews_auto_reply_enabled` flag'leri eklendi |
| [`CustomerCareRoutingTest.php`](../../../tests/Feature/CustomerCare/CustomerCareRoutingTest.php) | 10 test (routing, concurrency, SLA, P1-3 tenant guard × 3, P1-4 business hours × 3, null rule) |
| [`CustomerCareCheckSlaEscalationsCommand.php`](../../../app/Console/Commands/CustomerCareCheckSlaEscalationsCommand.php) | SLA escalation artisan komutu |

---

## 3. Migration Listesi

```
2026_07_29_100000_create_support_teams_tables.php
  → support_teams (id, store_id, name)
  → support_team_members (id, support_team_id, user_id)
  → support_routing_rules (id, store_id, support_team_id, trigger_type, trigger_value, priority, is_active)
  → support_conversations.support_team_id (FK)
```

---

## 4. Test İsimleri (10 test / 20 assertion)

```
✓ conversation_routed_based_on_channel_rule
✓ concurrency_lock_prevent_double_claims
✓ sla_breach_escalates_and_creates_audit_log
✓ cross_store_user_cannot_claim_conversation        ← P1-3
✓ cross_store_user_cannot_release_conversation      ← P1-3
✓ admin_user_can_claim_any_store_conversation       ← P1-3 admin bypass
✓ business_hours_gate_blocks_automatic_reply_outside_hours   ← P1-4
✓ business_hours_gate_allows_during_working_hours_with_flag_off ← P1-4
✓ business_hours_gate_blocks_weekend_outside_allowlist       ← P1-4
✓ store_without_matching_rule_returns_null_team
```

---

## 5. Artisan Komutları

```bash
# SLA ihlalleri kontrolü (tüm mağazalar)
php artisan customer-care:run-sla-escalations

# Scheduler (her 15 dakika)
*/15 * * * *  php artisan customer-care:run-sla-escalations
```

---

## 6. Feature Flag Varsayılanları (Hepsi KAPALI)

| Flag | ENV | Varsayılan |
|---|---|---|
| `business_hours_auto_reply_enabled` | `CUSTOMER_CARE_BUSINESS_HOURS_AUTO_REPLY` | `false` |
| `google_reviews_auto_reply_enabled` | `CUSTOMER_CARE_GOOGLE_REVIEWS_AUTO_REPLY` | `false` |

---

## 7. Kalite Kapısı 01 — P0/P1 Düzeltmeleri

### P1-3 ✅ — Tenant/Store Claim Guard (Servis Seviyesi)
- `claim()` ve `release()` metodları servis seviyesinde `actorCanAccessStore()` ile actor'ın store'una sahip olup olmadığını doğrular.
- Admin/superadmin rolündeki kullanıcılar CLI/background path için bypass edilir.
- Yetkisiz erişim `Log::warning()` ile kayıt altına alınır.
- Hem UI hem CLI doğrudan service kullanımında güvenlik sağlanır.

### P1-4 ✅ — Business Hours Fail-Closed (Automation Gate)
- `CustomerCareAutomationGate::canAutomate()` içinde 4.5. kontrol olarak eklendi.
- `customer-care.business_hours_auto_reply_enabled = false` (varsayılan) ise mesai dışında (09:00-18:00 ve haftasonu) otomatik yanıt **engellenir**.
- Manuel/temsilci yanıtı bu kontrol tarafından etkilenmez.
- Routing rule'daki `business_hours` trigger kural motoru içinde kalmaya devam eder; automation gate ondan bağımsız çalışır.

---

## 8. Güvenlik Özeti

| Risk | Düzeltme |
|---|---|
| Cross-tenant conversation claim | `actorCanAccessStore()` store_id guard |
| Mesai dışı otomatik yanıt | `business_hours_auto_reply_enabled = false` varsayılan |
| Aynı konuşmayı çift claim | `lockForUpdate()` optimistic lock |
| SLA ihlallerinin sessiz geçmesi | `SupportAgentAction` audit log + priority=high |

---

## 9. Bilinen Eksikler / Rollback Notu

- Team membership kontrolü (belirli bir teamın üyesi olma) henüz uygulanmadı; ADR: Pilot aşamasında store-level guard yeterli kabul edildi, team-level claim v2'de planlanıyor.
- Rollback: `CustomerCareRoutingService` ve `CustomerCareAutomationGate`'i önceki commit'e geri al; config key'leri kaldır.
