# ZOLM AI Müşteri İletişim Merkezi — Dalga D Kabul Kararı

Tarih: 2026-07-11  
Karar sahibi: Codex baş mühendis kontrolü  
Kapsam: Dalga D — Pilot Öncesi Sertleştirme

## Karar

**Dalga D teknik kapsamı kabul edildi.**

System actor sertleştirmesi, AI ledger bağlantısı, audit retention FK politikası, Knowledge/Brand Voice güvenliği ve WhatsApp risky test düzeltmesi ana hedefleri doğrulanmıştır.

Bu kabul **Dalga E/F kabulü**, **pilot route üretim açılışı**, **automatic reply canlı kullanımı** veya **genel production onayı** anlamına gelmez.

## Bağımsız doğrulama kanıtları

- Test paketi tekrar çalıştırıldı:
  - `tests/Feature/CustomerCare`
  - `tests/Feature/WhatsApp/SupportChannelTest.php`
  - `tests/Feature/MarketplaceQuestionsTest.php`
- Nihai tekrar sonucu: **77 passed, 0 risky, 265 assertions**.
- `git diff --check`: temiz.
- `npm run build`: başarılı.
- Migration status:
  - `2026_07_26_100000_create_support_dispatches_tables`: Ran
  - `2026_07_26_110000_add_ownership_status_to_support_conversations`: Ran
  - `2026_07_26_120000_create_support_ai_runs_table`: Ran
  - `2026_07_26_130000_make_conversation_id_nullable_in_support_agent_actions_table`: Ran
  - `2026_07_26_140000_add_shadow_match_score_to_support_ai_runs_table`: Ran
- Route listesinde:
  - `customer-care`
  - `customer-care/pilot`
- Scheduler listesinde:
  - `support-process-outbox`
  - `whatsapp-process-outbox`

## Doğrulanan DB FK davranışı

Canlı MySQL schema sorgusunda aşağıdaki delete rule değerleri doğrulandı:

- `support_dispatch_attempts.support_dispatch_id` → `support_dispatches.id`: **ON DELETE RESTRICT**
- `support_dispatches.conversation_id` → `support_conversations.id`: **ON DELETE RESTRICT**
- `support_dispatches.message_id` → `support_messages.id`: **ON DELETE RESTRICT**
- `support_dispatches.support_channel_id` → `support_channels.id`: **ON DELETE RESTRICT**
- `support_ai_runs.store_id` → `marketplace_stores.id`: **ON DELETE RESTRICT**
- `support_ai_runs.conversation_id` → `support_conversations.id`: **ON DELETE RESTRICT**
- `support_ai_runs.message_id` → `support_messages.id`: **ON DELETE SET NULL**

## Kabul edilen Dalga D maddeleri

1. `TenantContext::getSystemActor()` artık factory veya ilk kullanıcı fallback'i kullanmıyor.
2. System actor config e-postasına göre çözümleniyor ve provision edilmemişse fail-closed davranıyor.
3. Gemini ve Fake AI adapter'ları `SupportAiRun` ledger kaydı oluşturuyor.
4. Dispatch ve AI run audit geçmişi parent delete cascade ile silinmeyecek şekilde sertleştirildi.
5. Marka sesi güncellemeleri `support_agent_actions` üzerinden durable audit kaydı oluşturuyor.
6. Türkçe prompt-injection ifadeleri Knowledge/Brand Voice servislerinde engelleniyor.
7. WhatsApp raw payload testi artık assertion üretiyor; risky durum kalktı.

## Yönetimsel kapsam notu

Antigravity Dalga D kapsamında durmamış; repo içine Dalga E/F kapsamına benzeyen şu parçalar da girmiştir:

- `CustomerCareAiOrchestrator`
- `CustomerCareContextBuilder`
- `CustomerCareEvalService`
- `CustomerCareAutomationGate`
- `CustomerCarePilotGateTest`
- `CustomerCareAiOrchestratorTest`
- `CustomerCare\PilotDashboard`
- `/customer-care/pilot` route'u
- `shadow_match_score` migration'ı

Bu parçaların testleri şu an yeşildir; ancak **bu karar dosyası onları ürün kabulü olarak onaylamaz**. Bunlar Dalga E/F kalite kapısında ayrıca incelenmelidir.

## Dalga E/F öncesi zorunlu düzeltme notları

### 1. Pilot dashboard tenant güvenliği ayrıca sertleştirilmeli

`PilotDashboard` şu an `selectedStoreId` üzerinden ledger kayıtlarını okuyor ve aktif AI taslaklarını store scope olmadan çekiyor. Route varsayılan feature flag'lerle kapalı olsa da, `customer-care.enabled + inbox_enabled` açıldığında pilot ekranı da görünür hale geliyor.

Dalga E/F kabulünden önce:

- `/customer-care/pilot` ayrı feature flag ile korunmalı.
- Store seçimi authenticated user tenant sınırına bağlanmalı.
- AI runs ve active drafts sorguları store/user scope ile sınırlandırılmalı.
- Negatif tenant erişim testi eklenmeli.

### 2. Global `tests/TestCase.php` değişikliği izole edilmeli

System actor provision işlemi tüm test suite'in global `setUp()` akışına eklenmiş durumda. Bu, Customer Care dışındaki testlerde kullanıcı sayısı, ilk kullanıcı, email uniqueness veya rol varsayımlarını etkileyebilir.

Dalga E/F kabulünden önce:

- Customer Care testleri için trait/base helper tercih edilmeli veya
- Global etki bütün test suite üzerinde doğrulanmalı ve gerekçesi belgelendirilmeli.

### 3. Dalga raporları ayrıştırılmalı

Dalga D raporu `dalga-abc-revizyon-02-kanit-paketi.md` dosyasının üzerine yazılmış görünüyor. Bundan sonra her dalga ayrı rapor dosyasına yazılmalıdır:

- `dalga-d-kanit-paketi.md`
- `dalga-e-kanit-paketi.md`
- `dalga-f-kanit-paketi.md`

## Sonuç

Dalga D hedefleri bakımından kalite kapısı **geçti**.

Sonraki adım, doğrudan Dalga E'yi büyütmek değil; önce sızmış E/F parçalarını yukarıdaki üç notla hizalayıp sonra Dalga E kalite incelemesine geçmektir.

