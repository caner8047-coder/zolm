# Faz 0 Kabul Kararı

**Karar:** `KABUL`  
**Faz 0 durumu:** Tamamlandı  
**Faz 1 durumu:** Açılabilir  
**İnceleyen:** Codex / Baş mühendis  
**Tarih:** 2026-07-11

## Kabul gerekçesi

- Antigravity yalnız izin verilen Faz 0 raporunu değiştirdi; uygulama koduna dokunmadı.
- Güncel branch, teknoloji, Support, WhatsApp, MarketplaceQuestion, AI, tenant ve test altyapısı gerçek koddan çıkarıldı.
- İlk rapordaki maddi hatalar iki kalite kapısıyla düzeltildi.
- `SupportChannelTest` bağımsız tekrar çalıştırıldı: 10 passed, 1 risky.
- Risky testin sıfır assertion nedeniyle güvenlik davranışını kanıtlamadığı rapora işlendi.
- FakeAiProvider fail-open, WhatsApp sahte başarı, Trendyol placeholder adapter, sabit güven ve tenant izolasyonu kritik risk olarak kaydedildi.
- `ZCC-001`–`ZCC-018` kapsam matrisi tamamlandı.

## Onaylanan mimari başlangıç kararları

1. `support_*` birleşik conversation/message projection çekirdeğidir.
2. `MarketplaceQuestion` ve `wa_*` kendi kanallarında source-of-truth olarak korunur.
3. MarketplaceQuestion → SupportConversation bağlantısı idempotent projection olacaktır.
4. Paralel `care_conversations` / `care_messages` oluşturulmayacaktır.
5. `wa_outbox` WhatsApp'a özel kalacaktır; generic dispatch ayrı ADR ile tasarlanacaktır.
6. CustomerCare AI contractı kanal bağımsız olacaktır; mevcut HTTP implementasyonları tekrar yazılmayacaktır.
7. FakeAiProvider yalnız açık test/demo modunda kullanılabilir; production davranışı fail-closed olacaktır.
8. Auto-reply kapalıdır; eşikler golden dataset ve shadow sonuçlarından önce belirlenmeyecektir.
9. Mevcut database queue MVP için korunabilir; driver değişimi ölçümle kararlaştırılacaktır.
10. Organization/RBAC ve bilgi merkezi sınırı Faz 1 ADR çıktısıdır.

## Faz 1 sınırı

Faz 1 yalnız aşağıdakileri kapsar:

- Mimari karar kayıtları
- Güvenli ve varsayılan kapalı feature flag/config
- Ayrı feature middleware
- Menüsüz ve verisiz minimal modül giriş sayfası
- Route/config/middleware render testleri

Faz 1'de migration, model, konuşma projection'ı, gerçek adapter bağlantısı, AI çağrısı, provider binding değişikliği, outbox, tenant migrationı veya otomatik cevap geliştirilmeyecektir.
