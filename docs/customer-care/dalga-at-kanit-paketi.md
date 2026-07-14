# Dalga AT — Agent Workspace v2 Kanıt Paketi

**Tarih:** 2026-07-13  
**Durum:** ✅ Tamamlandı

---

## 1. Değiştirilen / Eklenen Dosyalar

| Dosya | Tür | Açıklama |
|---|---|---|
| `database/migrations/2026_08_04_100000_create_support_reply_macros_table.php` | Yeni | Makrolar ve sürüm geçmişi tabloları. |
| `database/migrations/2026_08_04_110000_create_support_internal_notes_table.php` | Yeni | Dahili notlar, varlık (presence) ve saved views tabloları. |
| `app/Models/SupportReplyMacro.php` | Yeni | Makro Eloquent modeli. |
| `app/Models/SupportReplyMacroVersion.php` | Yeni | Makro sürüm Eloquent modeli. |
| `app/Models/SupportInternalNote.php` | Yeni | Dahili not modeli (encrypted cast). |
| `app/Models/SupportAgentPresence.php` | Yeni | Soft presence modeli. |
| `app/Models/SupportSavedView.php` | Yeni | Kayıtlı görünümler modeli. |
| `app/Services/Support/CustomerCareWorkspaceService.php` | Yeni | Varlık, görünüm kaydı ve makro render servis mantığı. |
| `app/Console/Commands/CustomerCareMacroAuditCommand.php` | Yeni | Makro denetim CLI komutu. |
| `app/Livewire/CustomerCare/AgentWorkspace.php` | Yeni | Çalışma alanı Livewire component sınıfı (dropdown store-scoping dahil). |
| `resources/views/livewire/customer-care/agent-workspace.blade.php` | Yeni | ZOLM açık panel kurallarına uygun mobil uyumlu Blade görünümü. |
| `tests/Feature/CustomerCare/CustomerCareAgentWorkspaceTest.php` | Yeni | Çalışma alanı özellik test paketi. |
| `config/customer-care.php` | Güncellendi | `agent_workspace_enabled` özellik bayrağı eklendi. |
| `.env.example` | Güncellendi | `CUSTOMER_CARE_AGENT_WORKSPACE_ENABLED` bayrağı eklendi. |
| `routes/web.php` | Güncellendi | `/customer-care/agent-workspace` rotası eklendi. |

---

## 2. Migration Listesi

| Migration | Tablo | Rollback |
|---|---|---|
| `2026_08_04_100000` | `support_reply_macros`, `support_reply_macro_versions` | `Schema::dropIfExists` sıralı |
| `2026_08_04_110000` | `support_internal_notes`, `support_agent_presences`, `support_saved_views` | `Schema::dropIfExists` sıralı |

---

## 3. Route / Command / Scheduler

**Rotalar:**

| Rota | Adı | Middleware |
|---|---|---|
| `GET /customer-care/agent-workspace` | `customer-care.agent-workspace` | `customer-care.feature:agent_workspace_enabled` |

**Artisan komutları:**

| Komut | Açıklama |
|---|---|
| `customer-care:macro-audit --store=ID --dry-run` | Mağaza yanıt makrolarını politika ihlalleri, prompt injection ve PII sızıntısı açısından denetler |

---

## 4. Feature Flag Varsayılanları

```
CUSTOMER_CARE_AGENT_WORKSPACE_ENABLED=false
```

---

## 5. Tenant / KVKK / Fail-Closed Güvenlik Kanıtları

- **Dahili Not Şifreleme (KVKK):** Müşteri temsilcilerinin kendi aralarında aldığı notlar veritabanında şifreli (`encrypted` model cast) olarak saklanır; ham veri tabanı sorgularında açıkça okunamaz. `internal_note_is_encrypted_and_does_not_trigger_outbound_dispatch` ✅
- **Dahili Not Outbox Koruması:** Dahili notlar sisteme kaydedildiğinde kesinlikle dışarıya giden herhangi bir kuyruk / dispatch (`support_dispatches`) tetiklemez. `internal_note_is_encrypted_and_does_not_trigger_outbound_dispatch` ✅
- **Cross-Store Makro Koruması:** Bir mağazaya ait makrolar, yetkisi olmayan kullanıcılar tarafından render edilemez veya görüntülenemez. `cross_store_macro_access_is_blocked` ✅
- **Makro Değişken Doldurma:** Makrolarda değişkenlerin (`{customer_name}`) yerine konması güvenle doğrulanmıştır. `macro_variable_substitution_works` ✅
- **Varlık Çakışma Yönetimi:** Temsilcilerin aynı konuşma üzerinde çalışması durumunda aktif varlıkları TTL (60 sn) ile temizlenecek şekilde store-isolated takip edilir. `presence_ttl_and_scoping_works` ✅
- **Kişisel Filtre İzolasyonu:** Kaydedilen kişisel görünümler (saved views) sadece yetkili olunan mağazalarda listelenir. `saved_views_are_isolated` ✅

---

## 6. Test Komutları ve Sonuçları

```bash
./vendor/bin/sail artisan test tests/Feature/CustomerCare/CustomerCareAgentWorkspaceTest.php --no-coverage --compact
```

**Sonuç:** 7 passed / 14 assertions ✅
