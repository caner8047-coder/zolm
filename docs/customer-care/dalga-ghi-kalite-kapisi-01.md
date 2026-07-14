# ZOLM AI Müşteri İletişim Merkezi — Dalga G/H/I Kalite Kapısı 01

Tarih: 2026-07-12  
Karar: **Revizyon gerekli — G/H/I toplu kabul edilmedi**

## Baş mühendis özeti

Dalga G/H/I uygulaması genel olarak doğru yönde ilerlemiştir. Hedef test paketi, build ve full suite yeşildir. Ancak kalite kapısı yalnız test sayısına göre verilemez.

İki kabul engeli tespit edildi:

1. `policy_block` audit nedeni yanlış alana yazılıyor ve dashboard yanlış alanı okuyor. Bu, Dalga G'nin “audit edilebilir politika engeli” vaadini zayıflatıyor.
2. Pilot readiness servisi golden eval sonucunu gerçek veri/çalıştırma olmadan sabit `passed` gösteriyor. Bu, Dalga H'nin “pilot hazır mı?” kararını yanıltıcı hale getirebilir.

Ek olarak Dalga I adapter contract testleri çok yüzeysel kalmış; N11 ve Hepsiburada skeleton/fail-closed sinyali daha netleştirilmelidir.

## Doğrulanan başarılı kanıtlar

### Hedef paket

Komut:

```text
git diff --check
npm run build
./vendor/bin/sail artisan test tests/Feature/CustomerCare tests/Feature/WhatsApp/SupportChannelTest.php tests/Feature/MarketplaceQuestionsTest.php --no-coverage --compact
```

Sonuç:

```text
git diff --check: temiz
npm run build: başarılı
Tests: 94 passed (411 assertions)
Duration: 3.23s
```

### Full suite

Komut:

```text
./vendor/bin/sail artisan test --no-coverage --compact
```

Sonuç:

```text
Tests: 1530 passed (6397 assertions)
Duration: 84.24s
```

## Kabul engelleri

### P0-1 — Pilot readiness golden eval'i sabit `passed` gösteriyor

Dosya:

```text
app/Services/Support/CustomerCarePilotReadinessService.php
```

Mevcut davranış:

```php
$checks['golden_eval'] = [
    'status' => 'passed',
    'label' => 'Golden Dataset Değerlendirme Eşiği',
    'detail' => 'Başarılı (Skor >= 80)'
];
```

Bu gerçek eval sonucu okumuyor, son eval run'ını aramıyor ve eval hiç yapılmamış olsa bile mağazayı hazır gösterebiliyor.

Bu Dalga H için kabul engelidir çünkü readiness ekranı pilot açma kararında kullanılacak operasyon yüzeyi haline geliyor.

Zorunlu düzeltme:

- Golden eval hiç çalışmamışsa `failed` veya en azından `warning` dönmeli ve `ready=false` olmalıdır.
- Son eval sonucu varsa skor, tarih ve pass/fail durumu gerçek kayıttan okunmalıdır.
- Eğer mevcut `CustomerCareEvalService::runGoldenDatasetEval()` sonucu kalıcı ledger'a yazmıyorsa, minimum çözüm olarak `SupportAiRun`/eval kayıtlarından güvenilir bir sonucun nasıl temsil edileceği netleştirilmeli; fake `passed` kaldırılmalıdır.
- Test güncellenmeli:
  - eval yoksa readiness `ready=false`
  - eval başarısızsa readiness `ready=false`
  - eval >=80 ve diğer şartlar sağlanıyorsa readiness `ready=true`

### P0-2 — Policy block audit nedeni yanlış alana yazılıyor

Dosya:

```text
app/Services/Support/SupportReplyService.php
```

Mevcut kod:

```php
SupportAgentAction::create([
    'conversation_id' => $conversation->id,
    'user_id' => $userId,
    'action' => 'policy_block',
    'details' => $policyResult['reason']
]);
```

Fakat model ve tablo standardı:

```text
app/Models/SupportAgentAction.php
fillable: details_json
database: support_agent_actions.details_json
```

`details` fillable değildir ve tabloda canonical alan değildir. Bu yüzden policy block nedeni audit'te kaybolabilir.

Dashboard da aynı yanlış alanı okuyor:

```text
resources/views/livewire/customer-care/pilot-dashboard.blade.php
{{ $block->details }}
```

Zorunlu düzeltme:

- `details_json` kullanılmalı.
- Önerilen yapı:

```php
'details_json' => [
    'reason' => $policyResult['reason'],
    'channel' => $conversation->channel->key,
    'sender_type' => 'agent',
]
```

- Dashboard `details_json['reason']` göstermeli.
- Test yalnız `action=policy_block` aramamalı; reason'ın `details_json` içinde kalıcı yazıldığını doğrulamalı.

## P1 — Pilot öncesi sertleştirme

### P1-1 — N11 ve Hepsiburada skeleton adapter sinyali fazla iyimser

Dosyalar:

```text
app/Services/Support/N11SupportChannelAdapter.php
app/Services/Support/HepsiburadaSupportChannelAdapter.php
tests/Feature/CustomerCare/SupportChannelAdapterContractTest.php
```

N11 için `sendReply()` fail-closed dönüyor, fakat capabilities içinde `send_messages = available` bildiriliyor ve `getOutboundTargetStatus()` `sent` dönüyor.

Bu sahte başarı üretmiyor, ancak operasyon/readiness katmanında “gönderim yapılabilir” sinyali verebilir.

Zorunlu düzeltme:

- Gerçek cevap gönderimi entegre değilse `send_messages` capability `unavailable` veya `not_configured` olmalı.
- `canReply()` yalnız capability değil, adapter'ın gerçekten gönderim yapabilecek durumda olup olmadığını da dikkate almalı.
- `getOutboundTargetStatus()` skeleton/fail-closed adapter için `accepted/sent` gibi iyimser değer dönmemeli; gerekirse `failed`/`blocked` benzeri açık bir statü veya adapter contract içinde daha doğru bir semantik belirlenmeli.
- Contract testleri davranış testine dönüşmeli:
  - disabled channel reply engeller
  - unsupported capability reply engeller
  - N11 skeleton sendReply success=false döner
  - N11 skeleton canReply false veya not_configured sinyali verir
  - Hepsiburada gerçek connector yoksa aynı fail-closed standardı korunur

### P1-2 — Policy engine Türkçe normalize kapsamı dar

`kapida odeme` yakalanıyor ancak `kapıda ödeme` gibi Türkçe karakterli varyantlar açık kalabilir. Pilot öncesi normalizasyon önerilir:

- lower-case
- Türkçe karakter normalize
- çoklu boşluk temizliği
- `kapıda ödeme`, `kapida ödeme`, `kapıda odeme` gibi varyantları kapsama

Bu kabul engeli değildir; fakat kanal policy motorunun güvenilirliği için revizyon sırasında eklenmesi önerilir.

### P1-3 — Readiness AI provider health `env()` ile okunuyor

Readiness içinde doğrudan `env('GEMINI_API_KEY')` kullanılıyor. Config cache altında bu güvenilir değildir. Provider health, `config()` veya provider contract health yöntemiyle ölçülmelidir.

## Antigravity'ye verilecek revizyon talimatı

```text
/Volumes/TWINMOS/zolm reposunda Dalga G/H/I Kalite Kapısı 01 revizyonunu uygula.

Önce şu dosyayı tamamen oku:
/Volumes/TWINMOS/zolm/docs/customer-care/dalga-ghi-kalite-kapisi-01.md

Kapsam:
1. CustomerCarePilotReadinessService içindeki hardcoded golden_eval passed davranışını kaldır.
2. Golden eval yoksa veya başarısızsa readiness ready=false dönmeli.
3. Golden eval gerçek sonuca dayanmalı; skor/tarih/durum UI ve artisan çıktısında görünmeli.
4. policy_block audit kaydını details_json içine yaz.
5. PilotDashboard policy block nedenini details_json['reason'] üzerinden göstermeli.
6. SupportPolicyEngineTest, policy_block reason'ın details_json'a kalıcı yazıldığını assert etmeli.
7. N11 ve Hepsiburada skeleton/fail-closed adapter sinyallerini netleştir:
   - Gerçek gönderim yoksa send_messages available olmasın.
   - canReply gerçek gönderim yeteneği yoksa false dönsün.
   - sendReply success=false kalmalı.
8. SupportChannelAdapterContractTest yüzeysel array testinden davranış testine genişletilsin.
9. Policy engine Türkçe normalize edilmiş yasaklı ifade varyantlarını kapsasın.
10. Readiness AI provider health env() yerine config/provider sözleşmesiyle ölçülsün.

Yasaklar:
- Commit/push/branch değiştirme.
- Dalga J'ye geçme.
- Otomatik yanıtı varsayılan açık yapma.
- Gerçek dış API çağrısı yapma.
- Mevcut çalışan marketplace/WhatsApp akışlarını kırma.

Kanıt paketi:
- git diff --check
- npm run build
- CustomerCare hedef paketi
- WhatsApp SupportChannelTest
- MarketplaceQuestionsTest
- Full suite mümkünse
- route:list ve artisan command list kanıtı
- Güncellenmiş dalga-g/h/i kanıt paketleri
- git status --short
```

## Karar

Dalga G/H/I çalışması teknik olarak ilerlemiştir ve regresyon üretmemiştir; ancak pilot readiness ve policy audit güvenilirliği kabul eşiğinin altındadır.

**Dalga G/H/I kabul edilmedi. Revizyon sonrası tekrar incelenecek.**
