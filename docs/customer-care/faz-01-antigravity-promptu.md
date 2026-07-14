# Antigravity Promptu — Faz 1: ADR, Feature Flag ve Güvenli Modül İskeleti

Aşağıdaki metin Antigravity'ye tek görev olarak verilmelidir.

---

`/Volumes/TWINMOS/zolm` reposunda **Faz 1 — ZOLM AI Müşteri İletişim Merkezi ADR, feature flag ve güvenli modül iskeleti** görevini uygula.

## Zorunlu başlangıç

Hiçbir dosyayı değiştirmeden önce tamamen oku:

1. `AGENTS.md`
2. `docs/customer-care/urun-gereksinimleri.md`
3. `docs/customer-care/antigravity-yurutme-plani.md`
4. `docs/customer-care/00-mevcut-durum-dogrulama.md`
5. `docs/customer-care/faz-00-kabul-karari.md`

Ardından çalıştır:

```text
pwd
git rev-parse --show-toplevel
git branch --show-current
git status --short
git log -1 --oneline
```

Kirli çalışma ağacındaki kullanıcı değişikliklerine dokunma. Commit, push veya branch değişikliği yapma. Yalnız bu fazı uygula ve Faz 2'ye geçme.

## Değişmez kararlar

- Qsup yalnız benchmark'tır; hiçbir Qsup dependency/API kodu ekleme.
- `support_*` birleşik projection çekirdeğidir.
- `MarketplaceQuestion` ve `wa_*` kanal source-of-truth kayıtlarıdır.
- Yeni `care_conversations` veya `care_messages` oluşturma.
- Auto-reply ve bütün AI özellikleri varsayılan kapalıdır.
- Bu fazda migration veya veri modeli değişikliği yoktur.
- FakeAiProvider/provider binding, adapter, outbox ve mevcut iş akışlarına bu fazda dokunma.
- Menüye yeni bağlantı ekleme; modül kapalıyken kullanıcı arayüzünde görünmemelidir.

## 1. ADR dosyaları

`docs/customer-care/adr/` altında aşağıdaki ADR'leri oluştur:

### `001-support-projection-cekirdegi.md` — Accepted

- `support_*` birleşik conversation/message projection çekirdeği
- Kanal source-of-truth kayıtlarının korunması
- Idempotent projection ve reconciliation
- Big-bang migration ve paralel `care_*` tablolarının reddi

### `002-tenant-ve-organizasyon-siniri.md` — Proposed

- Mevcut `User → LegalEntity → MarketplaceStore` zinciri
- `store_id` partitionının bugünkü kullanımı ve açıkları
- Çok kullanıcılı firma, organization membership ve RBAC ihtiyacı
- Faz 2'de doğrulanacak migration/backfill/policy yaklaşımı
- Bu ADR'de migration yazma; öneri, alternatifler ve karar ölçütü üret

### `003-generic-outbound-dispatch.md` — Accepted

- `support_messages` iş mesajı kaydıdır
- Kanal gönderim denemesi ve retry ayrı generic dispatch/outbox lifecycle'ında tutulacaktır
- `wa_outbox` WhatsApp'a özel kalır
- Adapter kanal outbox/API ayrıntısını kapsüller
- İdempotency, delivery attempt, retry/backoff ve sahte başarı yasağı
- Uygulama Faz 3'e aittir; bu fazda tablo veya job oluşturma

### `004-customer-care-ai-provider.md` — Accepted

- Kanal bağımsız CustomerCare AI contractı
- Mevcut `AIService` ve WhatsApp providerlarının adapter arkasından kullanılması
- Aynı Gemini/OpenAI/Groq HTTP implementasyonunun tekrar yazılmaması
- Provider selection config tabanlı
- Production fail-closed; FakeAiProvider yalnız açık test/demo modunda
- Structured output, prompt version, source, token, cost ve latency sınırı
- Gerçek contract/DTO implementasyonu Faz 6'ya aittir

### `005-bilgi-merkezi-siniri.md` — Proposed

- `WaKnowledgeArticle` mevcut kanal kaynağıdır, otomatik generic canonical kabul edilmez
- Firma/mağaza kapsamı, global içerik, version, approval ve source ledger ihtiyaçları
- Compatibility adapter/projection ile yeni generic model seçeneklerini karşılaştır
- Nihai veri modeli Faz 7 öncesi kalite kapısında onaylanacaktır

### `006-human-ownership-state-machine.md` — Accepted

- AI active, copilot, human owned, resolved/released durumları
- İnsan devri en yüksek öncelikli kilit
- `resolve()` mevcut fonksiyonel temel ancak release/resolve kararları audit edilebilir ayrılmalı
- Yetki, optimistic concurrency ve AI'ın kendiliğinden geri alamaması
- Uygulama Faz 2–3'e aittir

Her ADR şu bölümleri içermeli:

```text
Başlık
Durum
Bağlam
Karar
Alternatifler
Sonuçlar ve trade-offlar
Geriye uyumluluk
Güvenlik/KVKK etkisi
İlgili ZCC gereksinimleri
Uygulama fazı
```

## 2. Güvenli config ve environment anahtarları

`config/customer-care.php` oluştur. En az şu ayarları içersin:

```text
enabled=false
inbox_enabled=false
ai_copilot_enabled=false
auto_reply_enabled=false
knowledge_enabled=false
analytics_enabled=false
demo_mode=false
default_automation_mode=manual
queue=default
```

Değerleri uygun `CUSTOMER_CARE_*` environment anahtarlarından oku. Boolean env değerlerinde mevcut proje convention'ını takip et. Güvenli varsayılanların tamamı kapalı olmalıdır.

`.env.example` içine açıklamalı olarak ekle:

```text
CUSTOMER_CARE_ENABLED=false
CUSTOMER_CARE_INBOX_ENABLED=false
CUSTOMER_CARE_AI_COPILOT_ENABLED=false
CUSTOMER_CARE_AUTO_REPLY_ENABLED=false
CUSTOMER_CARE_KNOWLEDGE_ENABLED=false
CUSTOMER_CARE_ANALYTICS_ENABLED=false
CUSTOMER_CARE_DEMO_MODE=false
CUSTOMER_CARE_DEFAULT_AUTOMATION_MODE=manual
CUSTOMER_CARE_QUEUE=default
```

Gerçek secret veya credential ekleme.

## 3. Feature middleware

`EnsureCustomerCareFeatureEnabled` middleware'i oluştur ve Laravel 12'nin mevcut `bootstrap/app.php` alias convention'ına göre `customer-care.feature` aliasıyla kaydet.

Kurallar:

- Master `enabled` false ise 404.
- Parametreyle verilen alt özellik false ise 404.
- Eksik config anahtarı güvenli biçimde false kabul edilir.
- Config'i runtime'da değiştirme veya mutate etme.

## 4. Minimal modül giriş sayfası

Route:

```text
GET /customer-care
name: customer-care.home
middleware: auth + customer-care.feature:inbox_enabled
```

Minimal Livewire 4 full-page component ve Blade view oluştur. Sayfa yalnız:

- `AI Müşteri İletişim Merkezi` başlığı
- Modülün kontrollü hazırlık aşamasında olduğunu belirten kısa açıklama
- `Manuel Mod` ve `Otomatik Yanıt Kapalı` güvenlik durumları

göstersin. Veritabanı sorgusu, AI çağrısı, metrik, demo yüzdesi veya haricî API çağrısı yapmasın.

UI başlamadan önce repo kökündeki `20.3 [UI8] - Venture CRM.fig`, `docs/zolm-kurumsal-acik-panel-sistemi.md` ve ilgili ZOLM referanslarını kontrol et. ZOLM Kurumsal Açık Panel Sistemi ve mobil responsive kurallarına uy. Koyu hero, gradient, glassmorphism ve aşırı oval kart kullanma.

Bu fazda sidebar/menu bağlantısı ekleme.

## 5. Testler

Dar kapsamlı feature testleri yaz:

- Config defaultlarının tamamı güvenli/kapalı.
- Master flag kapalıyken route 404.
- Master açık, inbox kapalıyken route 404.
- İki flag açık, unauthenticated kullanıcı auth davranışına göre engelleniyor/yönlendiriliyor.
- İki flag açık, authenticated kullanıcı minimal sayfayı görebiliyor.
- Render sırasında config mutasyonu yapılmıyor.
- `auto_reply_enabled` default false kalıyor.

Testlerde gerçek dış API veya AI çağrısı yapılmamalıdır.

## İzin verilen kapsam

Bu fazda yalnız aşağıdaki alanlarda değişiklik yapabilirsin:

- `docs/customer-care/adr/`
- `config/customer-care.php`
- `.env.example`
- `app/Http/Middleware/EnsureCustomerCareFeatureEnabled.php`
- `bootstrap/app.php` middleware aliası
- `routes/web.php` tek modül route'u
- `app/Livewire/CustomerCare/` minimal component
- `resources/views/livewire/customer-care/` minimal view
- `tests/Feature/CustomerCare/` dar kapsamlı testler

Bu liste dışındaki dosyaya ihtiyaç duyarsan değiştirme; blocker olarak raporla.

## Yasaklar

- Migration/model/factory/seeder oluşturma
- Mevcut Support/WhatsApp/Marketplace servislerini değiştirme
- Provider binding veya AIService değiştirme
- Adapter/outbox/job yazma
- Sidebar veya navigasyon değiştirme
- Auto-reply açma
- Gerçek müşteri verisini LLM'e gönderme
- Faz 2 çalışması yapma

## Doğrulama

En az şunları çalıştır:

```text
./vendor/bin/sail artisan test tests/Feature/CustomerCare
./vendor/bin/sail artisan route:list --name=customer-care
npm run build
git diff --check
```

Uygunsa ilgili mevcut feature flag/regresyon testlerini de dar kapsamlı çalıştır.

## Faz sonu teslimatı

Şunları raporla ve dur:

```text
git branch --show-current
git status --short
git diff --stat
değiştirilen/eklenen dosyalar
oluşturulan ADR'ler ve durumları
çalıştırılan testler ve sonuçları
route:list sonucu
build sonucu
manuel test adımları
bilinen eksikler/blockerlar
rollback yöntemi
```

Faz 2'ye geçme. Commit, push veya branch değiştirme.
