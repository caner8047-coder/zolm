# ZOLM AI Müşteri İletişim Merkezi — Dalga G/H/I Kabul Kararı

Tarih: 2026-07-12  
Karar sahibi: Codex baş mühendis kontrolü  
Kapsam: Dalga G — Kanal Politika Motoru, Dalga H — Pilot Operasyon Merkezi, Dalga I — Kanal Adapter Sertifikasyonu  
Karar: **Dalga G/H/I kalite kapısı kabul edildi**

## Baş mühendis özeti

Dalga G/H/I Kalite Kapısı 01 revizyonunda önceki P0/P1 kabul engelleri giderildi.

Özellikle şu üç kritik risk kapatılmıştır:

1. Pilot readiness içindeki golden dataset sonucu artık hardcoded `passed` değildir; son golden eval sonucu üzerinden dinamik değerlendirme yapılır.
2. Politika engel audit kaydı canonical `support_agent_actions.details_json` alanına yazılır ve dashboard aynı alanı okur.
3. N11 ve Hepsiburada adapter'ları canlı gönderim desteği varmış gibi davranmaz; `send_messages` capability `unavailable`, `canReply()` `false`, outbound hedef durumu `failed` döner.

Bu kabul, N11 veya Hepsiburada için gerçek cevap gönderiminin canlıya hazır olduğu anlamına gelmez. Tam tersine, bu iki kanal için doğru davranış şu aşamada **fail-closed skeleton** olarak kabul edilmiştir.

## Kabul edilen ana düzeltmeler

### 1. Dynamic golden eval readiness

Dosyalar:

```text
app/Services/Support/AI/CustomerCareEvalService.php
app/Services/Support/CustomerCarePilotReadinessService.php
app/Livewire/CustomerCare/PilotDashboard.php
tests/Feature/CustomerCare/CustomerCarePilotReadinessTest.php
```

`CustomerCareEvalService` içine `saveGoldenEval()` ve `getLatestGoldenEval()` metotları eklendi. Pilot dashboard golden dataset çalıştırdığında sonucu kaydeder. Readiness servisi artık bu kaydı okuyarak:

- Sonuç yoksa `golden_eval = failed` ve `ready = false`
- Skor 80 altındaysa `golden_eval = failed` ve `ready = false`
- Skor 80 ve üzeriyse `golden_eval = passed`

davranışını gösterir.

### 2. Policy block audit alanı düzeltildi

Dosyalar:

```text
app/Services/Support/SupportReplyService.php
resources/views/livewire/customer-care/pilot-dashboard.blade.php
tests/Feature/CustomerCare/SupportPolicyEngineTest.php
```

Temsilci mesajı policy guard tarafından engellendiğinde `SupportAgentAction` kaydı artık `details_json` içine yazılır:

```php
'details_json' => [
    'reason' => $policyResult['reason'],
    'channel' => $conversation->channel->key,
    'sender_type' => 'agent',
]
```

Dashboard da engelleme nedenini `details_json['reason']` üzerinden gösterir.

### 3. Türkçe karakter ve boşluk normalizasyonu eklendi

Dosya:

```text
app/Services/Support/Policy/SupportPolicyEngine.php
```

Politika motoru artık `kapıda ödeme`, `kapida ödeme`, `kapida   ödeme` gibi Türkçe karakter/boşluk varyasyonlarını normalize edip engeller. Bu, pazaryeri kurallarında en riskli kaçaklardan birini kapatır.

### 4. Dummy adapter sahte başarı sinyalleri kapatıldı

Dosyalar:

```text
app/Services/Support/N11SupportChannelAdapter.php
app/Services/Support/HepsiburadaSupportChannelAdapter.php
tests/Feature/CustomerCare/SupportChannelAdapterContractTest.php
```

N11 ve Hepsiburada adapter'ları şu aşamada yalnız interface sözleşmesini dolduran güvenli skeleton'lardır:

- `send_messages`: `unavailable`
- `canReply()`: `false`
- `sendReply()`: `success=false`
- `getOutboundTargetStatus()`: `failed`

Bu karar, canlı müşteri mesajının desteklenmeyen kanallara yanlışlıkla gönderilmesini engeller.

### 5. Gemini config erişimi config cache uyumlu hale getirildi

Dosyalar:

```text
config/services.php
app/Services/Support/AI/GeminiCustomerCareAiAdapter.php
app/Services/WhatsApp/GeminiAiProvider.php
```

Gemini anahtar ve model bilgileri `services.gemini` altında tanımlandı. CustomerCare AI adapter'ı bu config üzerinden çalışır ve eksik anahtarda production tarafında fail-closed davranışını korur.

## Bağımsız doğrulama kanıtları

### Whitespace / diff kontrolü

Komut:

```text
git diff --check
```

Sonuç:

```text
temiz
```

### Hedef test paketi

Komut:

```text
./vendor/bin/sail artisan test tests/Feature/CustomerCare tests/Feature/WhatsApp/SupportChannelTest.php tests/Feature/MarketplaceQuestionsTest.php --no-coverage --compact
```

Sonuç:

```text
Tests: 100 passed (444 assertions)
Duration: 3.02s
```

### Frontend build

Komut:

```text
npm run build
```

Sonuç:

```text
vite build: başarılı
✓ built in 2.35s
```

### Full test suite

Komut:

```text
./vendor/bin/sail artisan test --no-coverage --compact
```

Sonuç:

```text
Tests: 1536 passed (6430 assertions)
Duration: 81.79s
```

## Kalan P2 notları

Bunlar Dalga G/H/I kabulünü engellemez; sonraki dalgalarda sertleştirme maddesi olarak ele alınmalıdır:

1. Golden eval sonucu şu an cache-backed tutuluyor. Pilot readiness için yeterli; ancak uzun vadede kalıcı denetim izi istenirse `support_ai_runs` veya ayrı bir eval ledger tablosuna taşınmalı.
2. AI policy block akışı fail-closed dönüyor fakat temsilci policy block gibi ayrı audit action üretmiyor. Operasyon metriği istenirse AI policy block ledger'ı eklenmeli.
3. `docs/customer-care/dalga-g-kanit-paketi.md`, `dalga-h-kanit-paketi.md`, `dalga-i-kanit-paketi.md` dosyaları ilk teslim sayılarıyla duruyor. Bu kabul dosyası revizyon-01 için güncel source-of-truth olarak kabul edilmiştir.
4. Eski WhatsApp `AiProviderInterface` binding'i hâlâ legacy alanda `FakeAiProvider`'a bağlıdır. CustomerCare AI contract'ı bundan bağımsız çalıştığı için bu kalite kapısını bloklamaz; fakat WhatsApp legacy AI üretim davranışı ayrı bir sertleştirme konusu olarak kalır.

## Son karar

Dalga G/H/I kalite kapısı **geçti**.

**Dalga G/H/I kabul edildi. Sıradaki dalga setine geçilebilir.**
